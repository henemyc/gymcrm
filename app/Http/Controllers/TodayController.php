<?php

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use App\Support\LeadWorkflow;
use App\Support\PhoneNormalizer;
use Illuminate\Http\Request;

class TodayController extends Controller
{
    public function index(Request $request)
    {
        return view('today.index', [
            'isAdmin' => $request->user()->isAdmin(),
            'staff' => $request->user()->isAdmin()
                ? User::staff()->active()->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }

    public function data(Request $request)
    {
        $user = $request->user();
        $base = Lead::query()->with('assignee')->ownedBy($user);

        $lost = [LeadStatus::Converted->value, LeadStatus::NotInterested->value, LeadStatus::Invalid->value, LeadStatus::Duplicate->value];
        $today = now()->toDateString();

        $callNow = (clone $base)
            ->whereNotIn('status', $lost)
            ->where(function ($q) use ($today) {
                $q->where('status', LeadStatus::New->value)
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
                $q->where('status', LeadStatus::MeetingScheduled->value);
                if (Schema::hasColumn('leads', 'meeting_at')) {
                    $q->orWhereDate('meeting_at', $today);
                }
            })
            ->whereNotIn('status', [LeadStatus::Converted->value, LeadStatus::NotInterested->value, LeadStatus::Invalid->value])
            ->orderBy('meeting_at')
            ->limit(40)
            ->get();

        $unassigned = $user->isAdmin()
            ? Lead::query()->whereNull('assigned_to')->whereNotIn('status', $lost)->latest()->limit(40)->get()
            : collect();

        $map = fn (Lead $l) => $this->card($l);

        return response()->json([
            'ok' => true,
            'counts' => [
                'call_now' => $callNow->count(),
                'meetings' => $meetings->count(),
                'unassigned' => $user->isAdmin() ? Lead::query()->whereNull('assigned_to')->whereNotIn('status', $lost)->count() : 0,
                'won_today' => (clone $base)->where('status', LeadStatus::Converted->value)->whereDate('updated_at', $today)->count(),
            ],
            'call_now' => $callNow->map($map)->values(),
            'meetings' => $meetings->map($map)->values(),
            'unassigned' => $unassigned->map($map)->values(),
        ]);
    }

    private function card(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'name' => $lead->name,
            'phone' => $lead->phone,
            'city' => $lead->city,
            'status' => $lead->status,
            'stage' => LeadWorkflow::stage($lead->status),
            'stage_label' => LeadWorkflow::stageLabel($lead->status),
            'badge' => LeadWorkflow::stageBadge($lead->status),
            'assignee' => $lead->assignee?->name,
            'follow_up_at' => optional($lead->follow_up_at)->format('d M'),
            'meeting_at' => optional($lead->meeting_at)->format('d M H:i'),
            'tel' => PhoneNormalizer::telHref($lead->phone),
            'wa' => PhoneNormalizer::whatsappHref($lead->phone),
            'url' => route('leads.show', $lead),
        ];
    }
}
