<?php

namespace App\Http\Controllers;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Http\Requests\StoreLeadRequest;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\Setting;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\DuplicateGuard;
use App\Services\LeadAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeadController extends Controller
{
    public function __construct(
        private ActivityLogger $logger,
        private DuplicateGuard $duplicates,
        private LeadAssignmentService $assignment,
    ) {
    }

    public function index(Request $request)
    {
        $staff = $request->user()->isAdmin()
            ? User::staff()->active()->orderBy('name')->get()
            : collect();

        return view('dashboard.index', [
            'screen' => 'leads',
            'isAdmin' => $request->user()->isAdmin(),
        ]);
    }

    public function datatable(Request $request)
    {
        $user = $request->user();
        $perPage = (int) $request->get('per_page', 15);
        $perPage = min(50, max(10, $perPage ?: 15));

        $query = Lead::query()->with('assignee')->ownedBy($user);

        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('city', 'like', '%'.$search.'%')
                    ->orWhere('address', 'like', '%'.$search.'%');
            });
        }

        if ($request->get('queue') === 'call') {
            $query->whereNotIn('status', [
                LeadStatus::Converted->value, LeadStatus::NotInterested->value, LeadStatus::Invalid->value, LeadStatus::Duplicate->value,
            ])->where(function ($q) {
                $q->where('status', LeadStatus::New->value)
                    ->orWhere(function ($q) {
                        $q->whereNotNull('follow_up_at')->whereDate('follow_up_at', '<=', now()->toDateString());
                    });
            });
        } elseif ($stage = $request->get('stage')) {
            $map = [
                'talk' => ['contacted', 'follow_up', 'interested'],
                'meeting' => ['meeting_scheduled'],
                'won' => ['converted'],
                'lost' => ['not_interested', 'invalid'],
                'new' => ['new'],
            ];
            if (! empty($map[$stage])) {
                $query->whereIn('status', $map[$stage]);
            }
        } elseif ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($source = $request->get('source')) {
            $query->where('source', $source);
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

        $sort = in_array($request->get('sort'), ['created_at', 'name', 'status', 'rating', 'follow_up_at'], true)
            ? $request->get('sort')
            : 'created_at';
        $dir = $request->get('dir') === 'asc' ? 'asc' : 'desc';

        $paginator = $query->orderBy($sort, $dir)->paginate($perPage);

        $scope = Lead::query()->ownedBy($user);

        return response()->json([
            'ok' => true,
            'data' => collect($paginator->items())->map(function (Lead $lead) {
                return $this->serialize($lead);
            }),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'sort' => $sort,
                'dir' => $dir,
            ],
            'stats' => [
                'total' => (clone $scope)->count(),
                'call_now' => (clone $scope)->whereNotIn('status', [
                    LeadStatus::Converted->value, LeadStatus::NotInterested->value, LeadStatus::Invalid->value, LeadStatus::Duplicate->value,
                ])->where(function ($q) {
                    $q->where('status', LeadStatus::New->value)
                        ->orWhere(function ($q) {
                            $q->whereNotNull('follow_up_at')->whereDate('follow_up_at', '<=', now()->toDateString());
                        });
                })->count(),
                'talk' => (clone $scope)->whereIn('status', ['contacted', 'follow_up', 'interested'])->count(),
                'meeting' => (clone $scope)->where('status', LeadStatus::MeetingScheduled->value)->count(),
                'won' => (clone $scope)->where('status', LeadStatus::Converted->value)->count(),
                'lost' => (clone $scope)->whereIn('status', ['not_interested', 'invalid'])->count(),
                'unassigned' => $user->isAdmin() ? Lead::query()->whereNull('assigned_to')->count() : 0,
            ],
        ]);
    }

    public function create(Request $request)
    {
        return redirect()->route('leads.index', ['new' => 1]);
    }

    public function store(StoreLeadRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();

        $check = $this->duplicates->check($data);
        if ($check['duplicate']) {
            throw ValidationException::withMessages([
                'name' => 'Duplicate lead ('.$check['reason'].'). Existing #'.$check['lead']?->id,
            ]);
        }

        if (! $user->isAdmin()) {
            $data['assigned_to'] = $user->id;
        }

        if (! empty($data['assigned_to'])) {
            $staff = User::query()->find($data['assigned_to']);
            if (! $staff || (! $staff->isStaff() && ! $staff->isAdmin())) {
                throw ValidationException::withMessages(['assigned_to' => 'Invalid assignee.']);
            }
            $data['assigned_by'] = $user->id;
            $data['assigned_at'] = now();
        }

        $lead = Lead::query()->create([
            ...$data,
            'source' => LeadSource::Manual->value,
            'status' => $data['status'] ?? LeadStatus::New->value,
            'created_by' => $user->id,
        ]);

        $this->logger->log('lead.created', "Created lead “{$lead->name}”.", $lead);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => 'Lead created.', 'id' => $lead->id]);
        }

        return redirect()->route('leads.show', $lead)->with('success', 'Lead created.');
    }

    public function show(Request $request, Lead $lead)
    {
        $this->authorizeLead($request, $lead);
        $lead->load(['assignee', 'assigner', 'creator', 'leadNotes.user']);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'lead' => $this->serialize($lead)]);
        }

        return view('leads.show', [
            'lead' => $lead,
            'statuses' => LeadStatus::cases(),
            'staff' => $request->user()->isAdmin() ? User::staff()->active()->orderBy('name')->get() : collect(),
            'meetingPeople' => User::query()->active()->orderBy('name')->get(['id', 'name', 'role']),
        ]);
    }

    public function edit(Request $request, Lead $lead)
    {
        $this->authorizeLead($request, $lead);

        return view('leads.form', [
            'lead' => $lead,
            'staff' => $request->user()->isAdmin() ? User::staff()->active()->orderBy('name')->get() : collect(),
            'statuses' => LeadStatus::cases(),
        ]);
    }

    public function update(StoreLeadRequest $request, Lead $lead)
    {
        $this->authorizeLead($request, $lead);
        $data = $request->validated();
        $user = $request->user();

        if (! $user->isAdmin()) {
            unset($data['assigned_to']);
        }

        $lead->update($data);
        $this->logger->log('lead.updated', "Updated lead “{$lead->name}”.", $lead);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => 'Lead updated.']);
        }

        return redirect()->route('leads.show', $lead)->with('success', 'Lead updated.');
    }

    public function updateStatus(Request $request, Lead $lead)
    {
        $this->authorizeLead($request, $lead);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_column(LeadStatus::cases(), 'value'))],
            'follow_up_at' => ['nullable', 'date'],
            'meeting_at' => ['nullable', 'date'],
            'meeting_with' => ['nullable', 'integer', 'exists:users,id'],
            'meeting_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($data['status'] === LeadStatus::MeetingScheduled->value && empty($data['meeting_at'])) {
            throw ValidationException::withMessages([
                'meeting_at' => 'Pick a meeting date.',
            ]);
        }

        $lead->status = $data['status'];
        if (array_key_exists('follow_up_at', $data)) {
            $lead->follow_up_at = $data['follow_up_at'];
        }
        if ($data['status'] === LeadStatus::MeetingScheduled->value) {
            $lead->meeting_at = $data['meeting_at'] ?? null;
            $lead->meeting_with = $data['meeting_with'] ?? null;
            $lead->meeting_notes = $data['meeting_notes'] ?? null;
            if ($lead->meeting_at && ! $lead->follow_up_at) {
                $lead->follow_up_at = $lead->meeting_at;
            }
        }
        if (in_array($data['status'], [
            LeadStatus::Contacted->value,
            LeadStatus::FollowUp->value,
            LeadStatus::MeetingScheduled->value,
            LeadStatus::Interested->value,
            LeadStatus::Converted->value,
        ], true)) {
            $lead->last_contacted_at = now();
        }
        $lead->save();

        $this->logger->log('lead.status', "Set status of “{$lead->name}” to {$lead->status}.", $lead);

        return response()->json(['ok' => true, 'message' => 'Status updated.', 'lead' => $this->serialize($lead->fresh('assignee'))]);
    }

    public function outcome(Request $request, Lead $lead)
    {
        $this->authorizeLead($request, $lead);

        $data = $request->validate([
            'outcome' => ['required', Rule::in(['no_answer', 'callback', 'meeting', 'won', 'lost'])],
            'note' => ['nullable', 'string', 'max:2000'],
            'follow_up_at' => ['nullable', 'date'],
            'meeting_at' => ['nullable', 'date'],
            'meeting_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $labels = [
            'no_answer' => 'No answer',
            'callback' => 'Call back',
            'meeting' => 'Meeting',
            'won' => 'Won',
            'lost' => 'Lost',
        ];
        $o = $data['outcome'];
        $note = trim((string) ($data['note'] ?? ''));
        if ($o === 'no_answer') {
            $lead->status = 'contacted';
            $lead->follow_up_at = now()->addDay()->startOfDay();
            $lead->last_contacted_at = now();
            $note = $note !== '' ? $note : 'No answer. Call back tomorrow.';
        } elseif ($o === 'callback') {
            $lead->status = 'follow_up';
            $lead->follow_up_at = $data['follow_up_at'] ?? now()->addDay()->startOfDay();
            $lead->last_contacted_at = now();
            $note = $note !== '' ? $note : 'Asked to call back.';
        } elseif ($o === 'meeting') {
            $when = $data['meeting_at'] ?? now()->addDay()->setTime(11, 0);
            $lead->status = 'meeting_scheduled';
            $lead->meeting_at = $when;
            $lead->follow_up_at = $when;
            $lead->last_contacted_at = now();
            $note = $note !== '' ? $note : 'Meeting booked.';
        } elseif ($o === 'won') {
            $lead->status = 'converted';
            $lead->last_contacted_at = now();
            $note = $note !== '' ? $note : 'Converted.';
        } else {
            $lead->status = 'not_interested';
            $lead->last_contacted_at = now();
            $note = $note !== '' ? $note : 'Not interested / lost.';
        }
        $lead->save();
        if ($note !== '') {
            LeadNote::query()->create([
                'lead_id' => $lead->id,
                'user_id' => $request->user()->id,
                'body' => $note,
            ]);
        }
        $this->logger->log('lead.outcome', 'Logged “'.$o.'” on “'.$lead->name.'”.', $lead);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'message' => $labels[$o].' saved.',
                'lead' => $this->serialize($lead->fresh('assignee')),
            ]);
        }

        return back()->with('success', $labels[$o].' saved.');
    }

    public function storeNote(Request $request, Lead $lead)
    {
        $this->authorizeLead($request, $lead);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $note = LeadNote::query()->create([
            'lead_id' => $lead->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        $this->logger->log('lead.note', "Added a note on “{$lead->name}”.", $lead);

        return response()->json([
            'ok' => true,
            'message' => 'Note saved.',
            'note' => [
                'id' => $note->id,
                'body' => $note->body,
                'user' => $request->user()->name,
                'created_at' => $note->created_at->diffForHumans(),
            ],
        ]);
    }

    public function destroy(Request $request, Lead $lead)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $name = $lead->name;
        $lead->delete();
        $this->logger->log('lead.deleted', "Deleted lead “{$name}”.");

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => 'Lead deleted.']);
        }

        return redirect()->route('leads.index')->with('success', 'Lead deleted.');
    }

    public function bulkAssign(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'exists:leads,id'],
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
        ]);

        $staff = User::query()->findOrFail($data['assigned_to']);
        $result = $this->assignment->assign($data['ids'], $staff, $request->user());

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'message' => "Assigned {$result['assigned']} lead(s) to {$staff->name}.",
                'result' => $result,
            ]);
        }

        return back()->with('success', "Assigned {$result['assigned']} lead(s) to {$staff->name}.");
    }

    public function bulkStatus(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
            'status' => ['required', Rule::in(array_column(LeadStatus::cases(), 'value'))],
            'meeting_at' => ['nullable', 'date'],
            'meeting_with' => ['nullable', 'integer', 'exists:users,id'],
            'meeting_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($data['status'] === LeadStatus::MeetingScheduled->value && empty($data['meeting_at'])) {
            throw ValidationException::withMessages([
                'meeting_at' => 'Pick a meeting date.',
            ]);
        }

        $payload = ['status' => $data['status']];
        if ($data['status'] === LeadStatus::MeetingScheduled->value) {
            $payload['meeting_at'] = $data['meeting_at'] ?? null;
            $payload['meeting_with'] = $data['meeting_with'] ?? null;
            $payload['meeting_notes'] = $data['meeting_notes'] ?? null;
            $payload['follow_up_at'] = $data['meeting_at'] ?? null;
        }

        $query = Lead::query()->whereIn('id', $data['ids'])->ownedBy($request->user());
        $count = $query->update($payload);

        $this->logger->log('leads.bulk_status', "Bulk status → {$data['status']} on {$count} leads.");

        return response()->json(['ok' => true, 'message' => "Updated {$count} lead(s)."]);
    }

    public function bulkDelete(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
        ]);

        $count = Lead::query()->whereIn('id', $data['ids'])->delete();
        $this->logger->log('leads.bulk_delete', "Bulk deleted {$count} leads.");

        return response()->json(['ok' => true, 'message' => "Deleted {$count} lead(s)."]);
    }

    private function authorizeLead(Request $request, Lead $lead): void
    {
        abort_unless($lead->canBeViewedBy($request->user()), 403);
    }

    private function serialize(Lead $lead): array
    {
        $digits = preg_replace('/\D+/', '', (string) $lead->phone) ?: '';
        if (strlen($digits) === 10) {
            $digits = '91'.$digits;
        }
        $label = match ($lead->status) {
            'new' => 'New',
            'contacted', 'follow_up', 'interested' => 'In talk',
            'meeting_scheduled' => 'Meeting',
            'converted' => 'Won',
            'not_interested', 'invalid' => 'Lost',
            'duplicate' => 'Duplicate',
            default => $lead->status,
        };
        $badge = match ($lead->status) {
            'new' => 'badge-new',
            'contacted', 'follow_up', 'interested' => 'badge-contacted',
            'meeting_scheduled' => 'badge-meeting',
            'converted' => 'badge-converted',
            'not_interested', 'invalid' => 'badge-not',
            default => 'badge-contacted',
        };

        return [
            'id' => $lead->id,
            'name' => $lead->name,
            'phone' => $lead->phone,
            'tel' => strlen($digits) >= 10 ? 'tel:+'.$digits : null,
            'wa' => strlen($digits) >= 10 ? 'https://wa.me/'.$digits : null,
            'email' => $lead->email,
            'website' => $lead->website,
            'city' => $lead->city,
            'state' => $lead->state,
            'country' => $lead->country,
            'address' => $lead->address,
            'category' => $lead->category,
            'notes' => $lead->notes,
            'status' => $lead->status,
            'status_label' => $label,
            'badge' => $badge,
            'source' => $lead->source,
            'source_label' => $lead->sourceEnum()->label(),
            'assigned_to' => $lead->assigned_to,
            'assignee' => $lead->assignee?->name,
            'follow_up_at' => optional($lead->follow_up_at)->format('Y-m-d'),
            'created_at' => optional($lead->created_at)->format('d M Y'),
            'google_maps_url' => $lead->google_maps_url,
            'url' => route('leads.show', $lead),
        ];
    }
}
