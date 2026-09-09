<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index()
    {
        return view('activity.index');
    }

    public function datatable(Request $request)
    {
        $query = Activity::query()->with(['user:id,name', 'lead:id,name']);

        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        $perPage = min(50, max(10, (int) $request->get('per_page', 20)));
        $paginator = $query->latest()->paginate($perPage);

        return response()->json([
            'ok' => true,
            'data' => collect($paginator->items())->map(fn (Activity $a) => [
                'id' => $a->id,
                'action' => $a->action,
                'description' => $a->description,
                'user' => $a->user?->name,
                'lead' => $a->lead?->name,
                'lead_id' => $a->lead_id,
                'ip' => $a->ip_address,
                'created_at' => optional($a->created_at)->format('d M Y H:i'),
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
            ],
        ]);
    }
}
