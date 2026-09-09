<?php

namespace App\Enums;

enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case FollowUp = 'follow_up';
    case MeetingScheduled = 'meeting_scheduled';
    case Interested = 'interested';
    case NotInterested = 'not_interested';
    case Converted = 'converted';
    case Invalid = 'invalid';
    case Duplicate = 'duplicate';

    public function label(): string
    {
        return config('gymlead.lead_statuses')[$this->value] ?? $this->value;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::New => 'badge-new',
            self::Contacted => 'badge-contacted',
            self::FollowUp => 'badge-follow',
            self::MeetingScheduled => 'badge-meeting',
            self::Interested => 'badge-interested',
            self::NotInterested => 'badge-not',
            self::Converted => 'badge-converted',
            self::Invalid => 'badge-invalid',
            self::Duplicate => 'badge-duplicate',
        };
    }
}
