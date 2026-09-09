<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadAssignmentService
{
    public function __construct(
        private ActivityLogger $logger
    ) {
    }

    /**
     * @param  array<int>  $leadIds
     */
    public function assign(array $leadIds, User $staff, User $actor, bool $allowReassign = true): array
    {
        if (! $staff->isStaff() || ! $staff->is_active) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Leads can only be assigned to an active staff member.',
            ]);
        }

        $max = (int) config('gymlead.defaults.max_selected_assign', 100);
        $leadIds = array_values(array_unique(array_map('intval', $leadIds)));

        if (count($leadIds) === 0) {
            throw ValidationException::withMessages(['ids' => 'Select at least one lead.']);
        }

        if (count($leadIds) > $max) {
            throw ValidationException::withMessages(['ids' => "You can assign at most {$max} leads at once."]);
        }

        if ($staff->daily_lead_limit > 0) {
            $today = Lead::query()
                ->where('assigned_to', $staff->id)
                ->whereDate('assigned_at', now()->toDateString())
                ->count();
            $incoming = count($leadIds);
            if (($today + $incoming) > $staff->daily_lead_limit) {
                $left = max(0, $staff->daily_lead_limit - $today);
                throw ValidationException::withMessages([
                    'assigned_to' => "{$staff->name} can take {$left} more lead(s) today (cap {$staff->daily_lead_limit}).",
                ]);
            }
        }

        $assigned = 0;
        $skipped = 0;

        DB::transaction(function () use ($leadIds, $staff, $actor, $allowReassign, &$assigned, &$skipped) {
            $leads = Lead::query()->whereIn('id', $leadIds)->lockForUpdate()->get();

            foreach ($leads as $lead) {
                if ($lead->assigned_to && (int) $lead->assigned_to === (int) $staff->id) {
                    $skipped++;
                    continue;
                }

                if ($lead->assigned_to && ! $allowReassign && ! $actor->isAdmin()) {
                    $skipped++;
                    continue;
                }

                $previous = $lead->assigned_to;
                $lead->assigned_to = $staff->id;
                $lead->assigned_by = $actor->id;
                $lead->assigned_at = Carbon::now();
                $lead->save();

                $this->logger->log(
                    'lead.assigned',
                    "Assigned “{$lead->name}” to {$staff->name}.",
                    $lead,
                    ['from' => $previous, 'to' => $staff->id],
                    $actor
                );
                $assigned++;
            }
        });

        return ['assigned' => $assigned, 'skipped' => $skipped];
    }

    public function staffOptions(): Collection
    {
        return User::query()->active()->staff()->orderBy('name')->get(['id', 'name', 'email']);
    }
}
