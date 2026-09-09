<?php

namespace App\Support;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\LeadNote;

class LeadWorkflow
{
    public const OUTCOMES = [
        'no_answer' => 'No answer',
        'callback' => 'Call back',
        'meeting' => 'Meeting',
        'won' => 'Won',
        'lost' => 'Lost',
    ];

    public static function stage(?string $status): string
    {
        return match ($status) {
            LeadStatus::New->value => 'new',
            LeadStatus::Contacted->value, LeadStatus::FollowUp->value, LeadStatus::Interested->value => 'talk',
            LeadStatus::MeetingScheduled->value => 'meeting',
            LeadStatus::Converted->value => 'won',
            LeadStatus::NotInterested->value, LeadStatus::Invalid->value => 'lost',
            LeadStatus::Duplicate->value => 'duplicate',
            default => 'talk',
        };
    }

    public static function stageLabel(?string $status): string
    {
        return match (self::stage($status)) {
            'new' => 'New',
            'talk' => 'In talk',
            'meeting' => 'Meeting',
            'won' => 'Won',
            'lost' => 'Lost',
            'duplicate' => 'Duplicate',
            default => 'In talk',
        };
    }

    public static function stageBadge(?string $status): string
    {
        return match (self::stage($status)) {
            'new' => 'badge-new',
            'talk' => 'badge-contacted',
            'meeting' => 'badge-meeting',
            'won' => 'badge-converted',
            'lost' => 'badge-not',
            'duplicate' => 'badge-duplicate',
            default => 'badge-contacted',
        };
    }

    public static function statusesForStage(string $stage): array
    {
        return match ($stage) {
            'new' => [LeadStatus::New->value],
            'talk' => [
                LeadStatus::Contacted->value,
                LeadStatus::FollowUp->value,
                LeadStatus::Interested->value,
            ],
            'meeting' => [LeadStatus::MeetingScheduled->value],
            'won' => [LeadStatus::Converted->value],
            'lost' => [LeadStatus::NotInterested->value, LeadStatus::Invalid->value],
            'duplicate' => [LeadStatus::Duplicate->value],
            default => [],
        };
    }

    public static function applyOutcome(Lead $lead, string $outcome, array $extra = [], ?int $userId = null): void
    {
        $note = trim((string) ($extra['note'] ?? ''));

        if ($outcome === 'no_answer') {
            $lead->status = LeadStatus::Contacted->value;
            $lead->follow_up_at = now()->addDay()->startOfDay();
            $lead->last_contacted_at = now();
            $note = $note !== '' ? $note : 'No answer. Call back tomorrow.';
        } elseif ($outcome === 'callback') {
            $lead->status = LeadStatus::FollowUp->value;
            $lead->follow_up_at = ! empty($extra['follow_up_at'])
                ? $extra['follow_up_at']
                : now()->addDay()->startOfDay();
            $lead->last_contacted_at = now();
            $note = $note !== '' ? $note : 'Asked to call back.';
        } elseif ($outcome === 'meeting') {
            $lead->status = LeadStatus::MeetingScheduled->value;
            $when = $extra['meeting_at'] ?? now()->addDay()->setTime(11, 0);
            $lead->meeting_at = $when;
            $lead->follow_up_at = $when;
            $lead->meeting_notes = $extra['meeting_notes'] ?? $lead->meeting_notes;
            $lead->last_contacted_at = now();
            $note = $note !== '' ? $note : 'Meeting booked.';
        } elseif ($outcome === 'won') {
            $lead->status = LeadStatus::Converted->value;
            $lead->last_contacted_at = now();
            $note = $note !== '' ? $note : 'Converted.';
        } elseif ($outcome === 'lost') {
            $lead->status = LeadStatus::NotInterested->value;
            $lead->last_contacted_at = now();
            $note = $note !== '' ? $note : 'Not interested / lost.';
        }

        $lead->save();

        if ($note !== '' && $userId) {
            LeadNote::query()->create([
                'lead_id' => $lead->id,
                'user_id' => $userId,
                'body' => $note,
            ]);
        }
    }
}
