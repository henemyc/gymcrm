<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Setting;
use App\Models\User;
use App\Services\CsvLeadService;
use Illuminate\Http\Request;

class ImportExportController extends Controller
{
    public function importForm(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('leads.import', [
            'staff' => User::staff()->active()->orderBy('name')->get(),
        ]);
    }

    public function import(Request $request, CsvLeadService $csv)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $result = $csv->import(
            $request->file('file'),
            $request->user(),
            $request->filled('assigned_to') ? (int) $request->assigned_to : null
        );

        return response()->json([
            'ok' => true,
            'message' => "Imported {$result['created']} lead(s). {$result['duplicates']} duplicates skipped.",
            'result' => $result,
        ]);
    }

    public function template(CsvLeadService $csv)
    {
        return $csv->template();
    }

    public function export(Request $request, CsvLeadService $csv)
    {
        $user = $request->user();
        if (! $user->isAdmin() && ! Setting::getBool('staff_can_export', false)) {
            abort(403, 'Export is disabled for staff.');
        }

        $query = Lead::query()->ownedBy($user);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($user->isAdmin() && $request->filled('assigned_to')) {
            $query->where('assigned_to', (int) $request->get('assigned_to'));
        }

        return $csv->export($query);
    }

    public function exportSelected(Request $request, CsvLeadService $csv)
    {
        $user = $request->user();
        if (! $user->isAdmin() && ! Setting::getBool('staff_can_export', false)) {
            abort(403, 'Export is disabled for staff.');
        }

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        $query = Lead::query()->whereIn('id', $data['ids'])->ownedBy($user);

        return $csv->export($query);
    }
}
