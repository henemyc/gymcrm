<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    public function log(string $action, string $description, ?Lead $lead = null, array $meta = [], ?User $user = null): Activity
    {
        $actor = $user ?? Auth::user();

        return Activity::query()->create([
            'user_id' => $actor?->id,
            'lead_id' => $lead?->id,
            'action' => $action,
            'description' => mb_substr($description, 0, 500),
            'ip_address' => request()?->ip(),
            'meta' => $meta ?: null,
        ]);
    }
}
