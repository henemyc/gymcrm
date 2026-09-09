<?php

namespace App\Models;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Support\PhoneNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'google_place_id',
        'name',
        'phone',
        'phone_normalized',
        'email',
        'website',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'latitude',
        'longitude',
        'rating',
        'reviews_count',
        'category',
        'business_status',
        'google_maps_url',
        'source',
        'status',
        'duplicate_hash',
        'assigned_to',
        'assigned_by',
        'assigned_at',
        'follow_up_at',
        'meeting_at',
        'meeting_with',
        'meeting_notes',
        'last_contacted_at',
        'notes',
        'raw_payload',
        'created_by',
        'scrape_job_id',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'rating' => 'float',
        'reviews_count' => 'integer',
        'assigned_at' => 'datetime',
        'follow_up_at' => 'datetime',
        'meeting_at' => 'datetime',
        'last_contacted_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (Lead $lead) {
            $lead->phone_normalized = PhoneNormalizer::normalize($lead->phone);
            $lead->duplicate_hash = static::makeDuplicateHash($lead->name, $lead->address, $lead->latitude, $lead->longitude);
        });
    }

    public static function makeDuplicateHash(?string $name, ?string $address, mixed $lat, mixed $lng): ?string
    {
        $name = mb_strtolower(trim((string) $name));
        $address = mb_strtolower(trim((string) $address));

        if ($name === '') {
            return null;
        }

        $latKey = is_numeric($lat) ? round((float) $lat, 4) : '';
        $lngKey = is_numeric($lng) ? round((float) $lng, 4) : '';

        return hash('sha256', $name.'|'.$address.'|'.$latKey.'|'.$lngKey);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scrapeJob(): BelongsTo
    {
        return $this->belongsTo(ScrapeJob::class);
    }

    public function meetingPerson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'meeting_with');
    }

    public function leadNotes(): HasMany
    {
        return $this->hasMany(LeadNote::class)->latest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function statusEnum(): LeadStatus
    {
        return LeadStatus::tryFrom($this->status) ?? LeadStatus::New;
    }

    public function sourceEnum(): LeadSource
    {
        return LeadSource::tryFrom($this->source) ?? LeadSource::Manual;
    }

    public function scopeOwnedBy($query, User $user)
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where('assigned_to', $user->id);
    }

    public function canBeViewedBy(User $user): bool
    {
        return $user->isAdmin() || (int) $this->assigned_to === (int) $user->id;
    }
}
