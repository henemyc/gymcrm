<?php

namespace App\Http\Controllers;

use App\Models\ScrapeJob;
use App\Models\Setting;
use App\Models\User;
use App\Services\GoogleMaps\GoogleMapsException;
use App\Services\GoogleMaps\GoogleMapsPlacesClient;
use App\Services\GoogleMaps\MapsLeadImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ScraperController extends Controller
{
    public function index()
    {
        $hasKey = Setting::googleMapsApiKey() !== '';

        return view('scraper.index', [
            'hasKey' => $hasKey,
            'staff' => User::staff()->active()->orderBy('name')->get(),
            'types' => config('gymlead.place_types'),
            'defaults' => [
                'keyword' => Setting::get('scrape_default_keyword', 'gym'),
                'radius' => Setting::getInt('scrape_default_radius', 3000),
                'max_radius' => Setting::getInt('scrape_max_radius', 50000),
                'max_pages' => Setting::getInt('scrape_max_pages', 3),
            ],
        ]);
    }

    public function autocomplete(Request $request, GoogleMapsPlacesClient $maps)
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        try {
            $predictions = $maps->autocomplete($data['q']);
        } catch (GoogleMapsException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'predictions' => $predictions]);
    }

    public function search(Request $request, MapsLeadImporter $importer)
    {
        $this->throttle($request, 'scraper-search', 8);

        $data = $request->validate([
            'location' => ['nullable', 'string', 'max:200'],
            'place_id' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'keyword' => ['required', 'string', 'max:80'],
            'type' => ['nullable', 'string', 'max:40'],
            'radius' => ['required', 'integer', 'min:50', 'max:50000'],
            'mode' => ['nullable', 'in:nearby,text'],
        ]);

        if (empty($data['place_id']) && empty($data['location']) && (empty($data['lat']) || empty($data['lng']))) {
            throw ValidationException::withMessages([
                'location' => 'Search and select a location first.',
            ]);
        }

        try {
            $result = $importer->search($data, $request->user());
        } catch (GoogleMapsException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true] + $result);
    }

    public function more(Request $request, MapsLeadImporter $importer)
    {
        $this->throttle($request, 'scraper-more', 8);

        $data = $request->validate([
            'job_id' => ['required', 'integer', 'exists:scrape_jobs,id'],
        ]);

        $job = ScrapeJob::query()->findOrFail($data['job_id']);

        try {
            $result = $importer->more($job, $request->user());
        } catch (GoogleMapsException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true] + $result);
    }

    public function enrich(Request $request, MapsLeadImporter $importer)
    {
        $this->throttle($request, 'scraper-enrich', 10);

        $data = $request->validate([
            'place_ids' => ['required', 'array', 'min:1', 'max:100'],
            'place_ids.*' => ['string', 'max:255'],
        ]);

        try {
            $items = $importer->enrich($data['place_ids']);
        } catch (GoogleMapsException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'items' => $items]);
    }

    public function assign(Request $request, MapsLeadImporter $importer)
    {
        $this->throttle($request, 'scraper-assign', 6);

        $data = $request->validate([
            'job_id' => ['required', 'integer', 'exists:scrape_jobs,id'],
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
            'places' => ['required', 'array', 'min:1', 'max:100'],
            'places.*.google_place_id' => ['required', 'string', 'max:255'],
            'places.*.name' => ['nullable', 'string', 'max:191'],
            'places.*.address' => ['nullable', 'string', 'max:500'],
            'places.*.latitude' => ['nullable', 'numeric'],
            'places.*.longitude' => ['nullable', 'numeric'],
            'places.*.rating' => ['nullable', 'numeric'],
            'places.*.reviews_count' => ['nullable', 'integer'],
            'places.*.category' => ['nullable', 'string', 'max:120'],
            'places.*.business_status' => ['nullable', 'string', 'max:80'],
            'places.*.google_maps_url' => ['nullable', 'string', 'max:500'],
            'places.*.phone' => ['nullable', 'string', 'max:40'],
            'places.*.website' => ['nullable', 'string', 'max:255'],
        ]);

        $staff = User::query()->findOrFail($data['assigned_to']);

        try {
            $result = $importer->saveAndAssign($data['places'], $staff, $request->user(), (int) $data['job_id']);
        } catch (GoogleMapsException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (ValidationException $e) {
            throw $e;
        }

        $skipPhone = count($result['skipped_no_phone'] ?? []);
        $message = "Saved {$result['saved']} lead(s), assigned {$result['assigned']} to {$staff->name}.";
        if ($skipPhone) {
            $message .= " {$skipPhone} skipped (no phone).";
        }
        if ($result['duplicates']) {
            $message .= ' '.count($result['duplicates']).' duplicates skipped.';
        }

        return response()->json([
            'ok' => true,
            'message' => $message,
            'result' => $result + ['staff_name' => $staff->name],
        ]);
    }

    public function jobs(Request $request)
    {
        $perPage = min(50, max(10, (int) $request->get('per_page', 10)));
        $paginator = ScrapeJob::query()->with('user:id,name')->latest()->paginate($perPage);

        return response()->json([
            'ok' => true,
            'jobs' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
            ],
        ]);
    }

    private function throttle(Request $request, string $name, int $max): void
    {
        $key = $name.'|'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, $max)) {
            abort(429, 'Slow down — too many scraper requests.');
        }
        RateLimiter::hit($key, 60);
    }
}
