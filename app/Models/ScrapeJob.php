<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScrapeJob extends Model
{
    protected $fillable = [
        'user_id',
        'query',
        'location_label',
        'place_id',
        'latitude',
        'longitude',
        'radius',
        'keyword',
        'type',
        'mode',
        'status',
        'results_found',
        'saved_count',
        'duplicate_count',
        'assigned_count',
        'error_message',
        'meta',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'radius' => 'integer',
        'results_found' => 'integer',
        'saved_count' => 'integer',
        'duplicate_count' => 'integer',
        'assigned_count' => 'integer',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
