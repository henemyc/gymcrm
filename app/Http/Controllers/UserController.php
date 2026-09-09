<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(private ActivityLogger $logger)
    {
    }

    public function index()
    {
        return view('users.index', [
            'roles' => UserRole::cases(),
        ]);
    }

    public function datatable(Request $request)
    {
        $query = User::query()->withCount('assignedLeads');

        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        if ($role = $request->get('role')) {
            $query->where('role', $role);
        }

        $perPage = min(50, max(10, (int) $request->get('per_page', 15)));
        $paginator = $query->latest()->paginate($perPage);

        return response()->json([
            'ok' => true,
            'data' => collect($paginator->items())->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'role' => $u->role,
                'is_active' => $u->is_active,
                'daily_lead_limit' => $u->daily_lead_limit,
                'assigned_leads_count' => $u->assigned_leads_count,
                'is_online' => $u->isOnline(),
                'last_online' => $u->lastOnlineLabel(),
                'last_login_at' => optional($u->last_login_at)->diffForHumans(),
            ]),
            'stats' => [
                'total' => User::query()->count(),
                'staff' => User::staff()->count(),
                'admins' => User::query()->where('role', UserRole::Admin->value)->count(),
                'online' => User::query()->where('last_seen_at', '>=', now()->subMinutes(2))->count(),
                'disabled' => User::query()->where('is_active', false)->count(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
            ],
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['daily_lead_limit'] = $data['daily_lead_limit'] ?? 0;

        $user = User::query()->create($data);
        $this->logger->log('user.created', "Created user {$user->name} ({$user->role}).");

        return response()->json(['ok' => true, 'message' => 'User created.', 'id' => $user->id]);
    }

    public function show(User $user)
    {
        $user->loadCount('assignedLeads');

        return response()->json([
            'ok' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'daily_lead_limit' => $user->daily_lead_limit,
            ],
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in([UserRole::Admin->value, UserRole::Staff->value])],
            'daily_lead_limit' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
            'password' => ['nullable', 'string', 'min:8', 'max:72'],
        ]);

        if ($user->id === $request->user()->id && $data['role'] !== UserRole::Admin->value) {
            return response()->json(['ok' => false, 'message' => 'You cannot demote your own admin account.'], 422);
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $data['is_active'] = $request->boolean('is_active', $user->is_active);
        $user->update($data);

        $this->logger->log('user.updated', "Updated user {$user->name}.");

        return response()->json(['ok' => true, 'message' => 'User updated.']);
    }

    public function toggle(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['ok' => false, 'message' => 'You cannot disable your own account.'], 422);
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        $this->logger->log('user.toggle', ($user->is_active ? 'Enabled' : 'Disabled')." user {$user->name}.");

        return response()->json([
            'ok' => true,
            'message' => $user->is_active ? 'User enabled.' : 'User disabled.',
            'is_active' => $user->is_active,
        ]);
    }

    public function resetPassword(Request $request, User $user)
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:72'],
        ]);

        $user->update(['password' => $data['password']]);
        $this->logger->log('user.password', "Reset password for {$user->name}.");

        return response()->json(['ok' => true, 'message' => 'Password reset.']);
    }
}
