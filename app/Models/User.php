<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'is_active',
        'daily_lead_limit',
        'last_login_at',
        'last_login_ip',
        'last_seen_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'is_active' => 'boolean',
        'daily_lead_limit' => 'integer',
    ];

    public function setPasswordAttribute($value): void
    {
        if (! filled($value)) {
            return;
        }

        $value = (string) $value;
        $already = str_starts_with($value, '$2y$')
            || str_starts_with($value, '$2a$')
            || str_starts_with($value, '$argon');

        $this->attributes['password'] = $already ? $value : Hash::make($value);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin->value;
    }

    public function isStaff(): bool
    {
        return $this->role === UserRole::Staff->value;
    }

    public function assignedLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'assigned_to');
    }

    public function createdLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'created_by');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function scrapeJobs(): HasMany
    {
        return $this->hasMany(ScrapeJob::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeStaff($query)
    {
        return $query->where('role', UserRole::Staff->value);
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->gt(now()->subMinutes(2));
    }

    public function lastOnlineLabel(): string
    {
        if ($this->isOnline()) {
            return 'Online now';
        }
        if ($this->last_seen_at) {
            return $this->last_seen_at->diffForHumans();
        }
        if ($this->last_login_at) {
            return 'Last login '.$this->last_login_at->diffForHumans();
        }

        return 'Never';
    }
}
