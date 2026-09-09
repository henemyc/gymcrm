<?php

namespace App\Http\Controllers;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        return view('reports.index', [
            'boot' => $this->payload($request),
        ]);
    }

    public function data(Request $request)
    {
        return response()->json($this->payload($request));
    }

    private function payload(Request $request): array
    {
        $user = $request->user();
        $range = (string) $request->input('range', '30');
        $from = $this->fromDate($range);

        $query = Lead::query()->ownedBy($user);
        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        $columns = ['id', 'assigned_to', 'status', 'source', 'created_at', 'follow_up_at'];
        if (Schema::hasColumn('leads', 'meeting_at')) {
            $columns[] = 'meeting_at';
        }

        $leads = $query->get($columns);

        $staff = $user->isAdmin()
            ? User::query()->where('role', 'staff')->orderBy('name')->get(['id', 'name', 'is_active'])
            : collect([$user]);

        $byId = [];
        foreach ($staff as $s) {
            $byId[$s->id] = $this->emptyRow($s->id, $s->name, (bool) $s->is_active);
        }
        if ($user->isAdmin()) {
            $byId[0] = $this->emptyRow(0, 'Unassigned', true);
        }

        $statusCounts = [];
        foreach (LeadStatus::cases() as $st) {
            $statusCounts[$st->value] = 0;
        }
        $sourceCounts = [];
        foreach (LeadSource::cases() as $src) {
            $sourceCounts[$src->value] = 0;
        }

        $days = $this->dayKeys($from);
        $trendConverted = array_fill_keys($days, 0);
        $trendMeetings = array_fill_keys($days, 0);
        $trendNew = array_fill_keys($days, 0);
        $hasMeeting = in_array('meeting_at', $columns, true);

        foreach ($leads as $lead) {
            $ownerId = $lead->assigned_to ? (int) $lead->assigned_to : 0;
            if (! isset($byId[$ownerId])) {
                if (! $user->isAdmin()) {
                    continue;
                }
                $byId[$ownerId] = $this->emptyRow($ownerId, 'Unknown', false);
            }

            $row = &$byId[$ownerId];
            $row['leads']++;
            $status = (string) $lead->status;
            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            }
            $src = (string) ($lead->source ?: 'manual');
            if (isset($sourceCounts[$src])) {
                $sourceCounts[$src]++;
            }

            $isConverted = $status === LeadStatus::Converted->value;
            $isMeeting = $status === LeadStatus::MeetingScheduled->value
                || ($hasMeeting && $lead->meeting_at);

            if ($isConverted) {
                $row['converted']++;
            }
            if ($isMeeting) {
                $row['meetings']++;
            }
            if ($status === LeadStatus::Interested->value) {
                $row['interested']++;
            }
            if ($status === LeadStatus::Contacted->value) {
                $row['contacted']++;
            }
            if ($lead->follow_up_at && $lead->follow_up_at->lte(now()->endOfDay())
                && ! in_array($status, [LeadStatus::Converted->value, LeadStatus::NotInterested->value, LeadStatus::Invalid->value], true)) {
                $row['follow_ups_due']++;
            }

            $day = optional($lead->created_at)->format('Y-m-d');
            if ($day && array_key_exists($day, $trendNew)) {
                $trendNew[$day]++;
                if ($isConverted) {
                    $trendConverted[$day]++;
                }
                if ($isMeeting) {
                    $trendMeetings[$day]++;
                }
            }
            unset($row);
        }

        $rows = array_values($byId);
        foreach ($rows as &$row) {
            $row['conversion_rate'] = $row['leads'] > 0
                ? round(($row['converted'] / $row['leads']) * 100, 1)
                : 0.0;
            $row['score'] = ($row['converted'] * 5) + ($row['meetings'] * 3) + ($row['interested'] * 2) + $row['contacted'];
        }
        unset($row);

        usort($rows, function ($a, $b) {
            if ($b['score'] === $a['score']) {
                return $b['converted'] <=> $a['converted'];
            }

            return $b['score'] <=> $a['score'];
        });

        $rank = 1;
        foreach ($rows as &$row) {
            $row['rank'] = $row['id'] === 0 ? null : $rank;
            if ($row['id'] !== 0) {
                $rank++;
            }
        }
        unset($row);

        $named = array_values(array_filter($rows, fn ($r) => $r['id'] !== 0));
        $top = $named[0] ?? null;

        $pipeline = [];
        foreach (LeadStatus::cases() as $st) {
            $pipeline[] = [
                'key' => $st->value,
                'label' => $st->label(),
                'count' => (int) ($statusCounts[$st->value] ?? 0),
            ];
        }
        $sources = [];
        foreach (LeadSource::cases() as $src) {
            $sources[] = [
                'key' => $src->value,
                'label' => $src->label(),
                'count' => (int) ($sourceCounts[$src->value] ?? 0),
            ];
        }

        $total = $leads->count();
        $converted = (int) $leads->where('status', LeadStatus::Converted->value)->count();
        $meetings = (int) $leads->filter(function ($l) use ($hasMeeting) {
            return $l->status === LeadStatus::MeetingScheduled->value || ($hasMeeting && $l->meeting_at);
        })->count();

        return [
            'ok' => true,
            'is_admin' => $user->isAdmin(),
            'range' => $range,
            'from' => $from?->toDateString(),
            'kpis' => [
                'leads' => $total,
                'converted' => $converted,
                'meetings' => $meetings,
                'conversion_rate' => $total ? round(($converted / $total) * 100, 1) : 0,
                'interested' => (int) ($statusCounts[LeadStatus::Interested->value] ?? 0),
                'follow_ups_due' => array_sum(array_column($rows, 'follow_ups_due')),
            ],
            'top' => $top,
            'staff' => $named,
            'rows' => $rows,
            'pipeline' => $pipeline,
            'sources' => $sources,
            'trend' => [
                'labels' => array_map(fn ($d) => Carbon::parse($d)->format('d M'), $days),
                'new' => array_values($trendNew),
                'converted' => array_values($trendConverted),
                'meetings' => array_values($trendMeetings),
            ],
        ];
    }

    private function emptyRow(int $id, string $name, bool $active): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'active' => $active,
            'leads' => 0,
            'converted' => 0,
            'meetings' => 0,
            'interested' => 0,
            'contacted' => 0,
            'follow_ups_due' => 0,
            'conversion_rate' => 0,
            'score' => 0,
        ];
    }

    private function fromDate(string $range): ?Carbon
    {
        return match ($range) {
            '7' => now()->subDays(7)->startOfDay(),
            '90' => now()->subDays(90)->startOfDay(),
            'all' => null,
            default => now()->subDays(30)->startOfDay(),
        };
    }

    private function dayKeys(?Carbon $from): array
    {
        $start = $from ? $from->copy() : now()->subDays(30)->startOfDay();
        $end = now()->startOfDay();
        if ($start->lt(now()->subDays(90))) {
            $start = now()->subDays(90)->startOfDay();
        }
        $keys = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $keys[] = $d->format('Y-m-d');
        }

        return $keys;
    }
}
