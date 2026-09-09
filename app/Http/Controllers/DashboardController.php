<?php

namespace App\Http\Controllers;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\ScrapeJob;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        return redirect()->route('today');
    }

    public function today(Request $request)
    {
        return view('dashboard.index', [
            'screen' => 'today',
            'isAdmin' => $request->user()->isAdmin(),
            'staff' => $request->user()->isAdmin()
                ? User::staff()->active()->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }

    public function todayData(Request $request)
    {
        $user = $request->user();
        $base = Lead::query()->with('assignee')->ownedBy($user);
        $lost = ['converted', 'not_interested', 'invalid', 'duplicate'];
        $today = now()->toDateString();

        $callNow = (clone $base)
            ->whereNotIn('status', $lost)
            ->where(function ($q) use ($today) {
                $q->where('status', 'new')
                    ->orWhere(function ($q) use ($today) {
                        $q->whereNotNull('follow_up_at')->whereDate('follow_up_at', '<=', $today);
                    });
            })
            ->orderByRaw('CASE WHEN follow_up_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('follow_up_at')
            ->limit(40)
            ->get();

        $meetings = (clone $base)
            ->where(function ($q) use ($today) {
                $q->where('status', 'meeting_scheduled');
                if (Schema::hasColumn('leads', 'meeting_at')) {
                    $q->orWhereDate('meeting_at', $today);
                }
            })
            ->whereNotIn('status', ['converted', 'not_interested', 'invalid'])
            ->limit(40)
            ->get();

        $unassigned = $user->isAdmin()
            ? Lead::query()->whereNull('assigned_to')->whereNotIn('status', $lost)->latest()->limit(40)->get()
            : collect();

        $map = function (Lead $l) {
            $digits = preg_replace('/\D+/', '', (string) $l->phone);
            if (strlen($digits) === 10) {
                $digits = '91'.$digits;
            }
            $tel = strlen($digits) >= 10 ? 'tel:+'.$digits : null;
            $wa = strlen($digits) >= 10 ? 'https://wa.me/'.$digits : null;
            $stage = $this->stageOf($l->status);

            return [
                'id' => $l->id,
                'name' => $l->name,
                'phone' => $l->phone,
                'city' => $l->city,
                'status' => $l->status,
                'stage' => $stage,
                'stage_label' => $this->stageLabel($l->status),
                'badge' => $this->stageBadge($l->status),
                'assignee' => $l->assignee?->name,
                'follow_up_at' => optional($l->follow_up_at)->format('d M'),
                'meeting_at' => optional($l->meeting_at)->format('d M H:i'),
                'tel' => $tel,
                'wa' => $wa,
                'url' => route('leads.show', $l),
            ];
        };

        return response()->json([
            'ok' => true,
            'counts' => [
                'call_now' => $callNow->count(),
                'meetings' => $meetings->count(),
                'unassigned' => $user->isAdmin() ? Lead::query()->whereNull('assigned_to')->whereNotIn('status', $lost)->count() : 0,
                'won_today' => (clone $base)->where('status', 'converted')->whereDate('updated_at', $today)->count(),
            ],
            'call_now' => $callNow->map($map)->values(),
            'meetings' => $meetings->map($map)->values(),
            'unassigned' => $unassigned->map($map)->values(),
        ]);
    }

    public function leadsPage(Request $request)
    {
        return view('dashboard.index', [
            'screen' => 'leads',
            'isAdmin' => $request->user()->isAdmin(),
        ]);
    }

    public function leadsJson(Request $request)
    {
        $user = $request->user();
        $per = min(50, max(10, (int) $request->get('per_page', 25)));
        $query = Lead::query()->with('assignee')->ownedBy($user);

        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('city', 'like', '%'.$search.'%');
            });
        }

        $stageMap = [
            'new' => ['new'],
            'talk' => ['contacted', 'follow_up', 'interested'],
            'meeting' => ['meeting_scheduled'],
            'won' => ['converted'],
            'lost' => ['not_interested', 'invalid'],
        ];
        if ($stage = $request->get('stage')) {
            if (! empty($stageMap[$stage])) {
                $query->whereIn('status', $stageMap[$stage]);
            }
        }
        if ($user->isAdmin() && $request->filled('assigned_to')) {
            if ($request->get('assigned_to') === 'unassigned') {
                $query->whereNull('assigned_to');
            } else {
                $query->where('assigned_to', (int) $request->get('assigned_to'));
            }
        }
        if ($request->boolean('follow_up')) {
            $query->whereNotNull('follow_up_at')->whereDate('follow_up_at', '<=', now()->toDateString());
        }

        $sort = in_array($request->get('sort'), ['name', 'created_at', 'status', 'follow_up_at'], true)
            ? $request->get('sort') : 'created_at';
        $dir = $request->get('dir') === 'asc' ? 'asc' : 'desc';
        $page = $query->orderBy($sort, $dir)->paginate($per);

        $map = function (Lead $l) {
            $digits = preg_replace('/\D+/', '', (string) $l->phone) ?: '';
            if (strlen($digits) === 10) {
                $digits = '91'.$digits;
            }

            return [
                'id' => $l->id,
                'name' => $l->name,
                'phone' => $l->phone,
                'email' => $l->email,
                'city' => $l->city,
                'status' => $l->status,
                'status_label' => $this->stageLabel($l->status),
                'badge' => $this->stageBadge($l->status),
                'assignee' => $l->assignee?->name,
                'follow_up_at' => optional($l->follow_up_at)->format('d M Y'),
                'created_at' => optional($l->created_at)->format('d M Y'),
                'tel' => strlen($digits) >= 10 ? 'tel:+'.$digits : null,
                'wa' => strlen($digits) >= 10 ? 'https://wa.me/'.$digits : null,
                'url' => route('leads.show', $l),
            ];
        };

        return response()->json([
            'ok' => true,
            'data' => collect($page->items())->map($map)->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'per_page' => $page->perPage(),
            ],
        ]);
    }

    public function reports(Request $request)
    {
        return view('dashboard.index', [
            'screen' => 'reports',
            'boot' => $this->reportPayload($request),
            'isAdmin' => $request->user()->isAdmin(),
            'staff' => collect(),
        ]);
    }

    public function reportData(Request $request)
    {
        return response()->json($this->reportPayload($request));
    }

    public function stats(Request $request)
    {
        $user = $request->user();
        $base = Lead::query()->ownedBy($user);
        $byStatus = (clone $base)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $pipeline = [];
        foreach (LeadStatus::cases() as $status) {
            $pipeline[] = [
                'key' => $status->value,
                'label' => $status->label(),
                'count' => (int) ($byStatus[$status->value] ?? 0),
            ];
        }

        return response()->json([
            'ok' => true,
            'totals' => [
                'leads' => (clone $base)->count(),
                'new' => (clone $base)->where('status', 'new')->count(),
                'converted' => (clone $base)->where('status', 'converted')->count(),
                'unassigned' => $user->isAdmin() ? Lead::query()->whereNull('assigned_to')->count() : 0,
                'staff' => $user->isAdmin() ? User::staff()->active()->count() : 0,
                'today' => (clone $base)->whereDate('created_at', now()->toDateString())->count(),
            ],
            'pipeline' => $pipeline,
            'recent' => [],
            'follow_ups' => [],
        ]);
    }

    private function reportPayload(Request $request): array
    {
        $user = $request->user();
        $range = (string) $request->input('range', '30');
        $from = match ($range) {
            '7' => now()->subDays(7)->startOfDay(),
            '90' => now()->subDays(90)->startOfDay(),
            'all' => null,
            default => now()->subDays(30)->startOfDay(),
        };

        $query = Lead::query()->ownedBy($user);
        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        $leads = $query->get();

        $staff = $user->isAdmin()
            ? User::query()->where('role', 'staff')->orderBy('name')->get(['id', 'name'])
            : collect([$user]);

        $byId = [];
        foreach ($staff as $s) {
            $byId[$s->id] = $this->emptyStaffRow($s->id, $s->name);
        }
        if ($user->isAdmin()) {
            $byId[0] = $this->emptyStaffRow(0, 'Unassigned');
        }

        $statusCounts = [];
        foreach (LeadStatus::cases() as $st) {
            $statusCounts[$st->value] = 0;
        }
        $sourceCounts = [];
        foreach (LeadSource::cases() as $src) {
            $sourceCounts[$src->value] = 0;
        }

        $start = $from ? $from->copy() : now()->subDays(30)->startOfDay();
        if ($start->lt(now()->subDays(90))) {
            $start = now()->subDays(90)->startOfDay();
        }
        $days = [];
        for ($d = $start->copy(); $d->lte(now()->startOfDay()); $d->addDay()) {
            $days[] = $d->format('Y-m-d');
        }
        $trendConverted = array_fill_keys($days, 0);
        $trendMeetings = array_fill_keys($days, 0);
        $trendNew = array_fill_keys($days, 0);

        foreach ($leads as $lead) {
            $ownerId = $lead->assigned_to ? (int) $lead->assigned_to : 0;
            if (! isset($byId[$ownerId])) {
                if (! $user->isAdmin()) {
                    continue;
                }
                $byId[$ownerId] = $this->emptyStaffRow($ownerId, 'Unknown');
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
            if ($status === 'converted') {
                $row['converted']++;
            }
            if ($status === 'meeting_scheduled' || $lead->meeting_at) {
                $row['meetings']++;
            }
            if ($status === 'interested') {
                $row['interested']++;
            }
            if ($status === 'contacted') {
                $row['contacted']++;
            }
            $day = optional($lead->created_at)->format('Y-m-d');
            if ($day && array_key_exists($day, $trendNew)) {
                $trendNew[$day]++;
                if ($status === 'converted') {
                    $trendConverted[$day]++;
                }
                if ($status === 'meeting_scheduled') {
                    $trendMeetings[$day]++;
                }
            }
            unset($row);
        }

        $rows = array_values($byId);
        foreach ($rows as &$row) {
            $row['conversion_rate'] = $row['leads'] > 0 ? round(($row['converted'] / $row['leads']) * 100, 1) : 0;
            $row['score'] = ($row['converted'] * 5) + ($row['meetings'] * 3) + ($row['interested'] * 2) + $row['contacted'];
            $row['follow_ups_due'] = $row['follow_ups_due'] ?? 0;
        }
        unset($row);
        usort($rows, fn ($a, $b) => $b['score'] <=> $a['score']);
        $rank = 1;
        foreach ($rows as &$row) {
            $row['rank'] = $row['id'] === 0 ? null : $rank++;
        }
        unset($row);
        $named = array_values(array_filter($rows, fn ($r) => $r['id'] !== 0));

        $pipeline = [];
        foreach (LeadStatus::cases() as $st) {
            $pipeline[] = ['key' => $st->value, 'label' => $st->label(), 'count' => (int) ($statusCounts[$st->value] ?? 0)];
        }
        $sources = [];
        foreach (LeadSource::cases() as $src) {
            $sources[] = ['key' => $src->value, 'label' => $src->label(), 'count' => (int) ($sourceCounts[$src->value] ?? 0)];
        }

        $total = $leads->count();
        $converted = (int) $leads->where('status', 'converted')->count();
        $meetings = (int) $leads->where('status', 'meeting_scheduled')->count();

        return [
            'ok' => true,
            'is_admin' => $user->isAdmin(),
            'range' => $range,
            'kpis' => [
                'leads' => $total,
                'converted' => $converted,
                'meetings' => $meetings,
                'conversion_rate' => $total ? round(($converted / $total) * 100, 1) : 0,
                'interested' => (int) ($statusCounts['interested'] ?? 0),
                'follow_ups_due' => 0,
            ],
            'top' => $named[0] ?? null,
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

    private function emptyStaffRow(int $id, string $name): array
    {
        return [
            'id' => $id, 'name' => $name, 'leads' => 0, 'converted' => 0, 'meetings' => 0,
            'interested' => 0, 'contacted' => 0, 'follow_ups_due' => 0, 'conversion_rate' => 0, 'score' => 0,
        ];
    }

    private function stageOf(?string $status): string
    {
        return match ($status) {
            'new' => 'new',
            'contacted', 'follow_up', 'interested' => 'talk',
            'meeting_scheduled' => 'meeting',
            'converted' => 'won',
            'not_interested', 'invalid' => 'lost',
            'duplicate' => 'duplicate',
            default => 'talk',
        };
    }

    private function stageLabel(?string $status): string
    {
        return match ($this->stageOf($status)) {
            'new' => 'New',
            'talk' => 'In talk',
            'meeting' => 'Meeting',
            'won' => 'Won',
            'lost' => 'Lost',
            default => 'In talk',
        };
    }

    private function stageBadge(?string $status): string
    {
        return match ($this->stageOf($status)) {
            'new' => 'badge-new',
            'talk' => 'badge-contacted',
            'meeting' => 'badge-meeting',
            'won' => 'badge-converted',
            'lost' => 'badge-not',
            default => 'badge-contacted',
        };
    }
}
