<?php

namespace App\Services\GoogleMaps;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\ScrapeJob;
use App\Models\Setting;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\DuplicateGuard;
use App\Services\LeadAssignmentService;
use Illuminate\Support\Facades\DB;

class MapsLeadImporter
{
    public function __construct(
        private GoogleMapsPlacesClient $maps,
        private DuplicateGuard $duplicates,
        private LeadAssignmentService $assignment,
        private ActivityLogger $logger,
    ) {
    }

    public function search(array $input, User $actor): array
    {
        $this->assertDailyCap($actor);

        $maxPages = max(1, min(3, Setting::getInt('scrape_max_pages', 3)));
        $maxRadius = Setting::getInt('scrape_max_radius', 50000);
        $radius = max(50, min((int) ($input['radius'] ?? 3000), $maxRadius));
        $keyword = trim((string) ($input['keyword'] ?? 'gym'));
        $type = $input['type'] ?? 'gym';
        $mode = ($input['mode'] ?? 'nearby') === 'text' ? 'text' : 'nearby';

        $geo = $this->resolveLocation($input);

        $job = ScrapeJob::query()->create([
            'user_id' => $actor->id,
            'query' => $mode === 'text' ? trim($keyword.' '.$geo['name']) : $keyword,
            'location_label' => $geo['address'] ?: $geo['name'],
            'place_id' => $geo['place_id'],
            'latitude' => $geo['lat'],
            'longitude' => $geo['lng'],
            'radius' => $radius,
            'keyword' => $keyword,
            'type' => $type,
            'mode' => $mode,
            'status' => 'running',
        ]);

        try {
            $result = $mode === 'text'
                ? $this->maps->textSearch(trim($keyword.' in '.$geo['name']), null, $geo['lat'], $geo['lng'], $radius)
                : $this->maps->nearbySearch($geo['lat'], $geo['lng'], $radius, $keyword, $type ?: null, null);

            $list = $this->annotate($result['items']);
            $token = $result['next_page_token'] ?? null;
            $hasMore = (bool) $token && $maxPages > 1;

            $job->update([
                'status' => 'previewed',
                'results_found' => count($list),
                'duplicate_count' => collect($list)->where('is_duplicate', true)->count(),
                'meta' => [
                    'next_page_token' => $token,
                    'pages_loaded' => 1,
                    'max_pages' => $maxPages,
                    'seen_ids' => collect($list)->pluck('google_place_id')->filter()->values()->all(),
                ],
            ]);

            $this->logger->log(
                'scrape.search',
                "Scraped {$job->results_found} places near {$job->location_label}.",
                null,
                ['job_id' => $job->id]
            );

            return [
                'job_id' => $job->id,
                'location' => $geo,
                'items' => $list,
                'found' => count($list),
                'duplicates' => $job->duplicate_count,
                'has_more' => $hasMore,
                'pages_loaded' => 1,
                'max_pages' => $maxPages,
            ];
        } catch (\Throwable $e) {
            $job->update([
                'status' => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 500),
            ]);
            throw $e;
        }
    }

    public function more(ScrapeJob $job, User $actor): array
    {
        if ((int) $job->user_id !== (int) $actor->id) {
            throw new GoogleMapsException('This scrape session belongs to another user.');
        }

        $meta = $job->meta ?? [];
        $token = $meta['next_page_token'] ?? null;
        $pages = (int) ($meta['pages_loaded'] ?? 1);
        $maxPages = (int) ($meta['max_pages'] ?? 3);
        $seen = $meta['seen_ids'] ?? [];

        if (! $token || $pages >= $maxPages) {
            return [
                'items' => [],
                'has_more' => false,
                'found' => $job->results_found,
                'duplicates' => $job->duplicate_count,
                'pages_loaded' => $pages,
                'max_pages' => $maxPages,
            ];
        }

        usleep(2100000);

        $result = $job->mode === 'text'
            ? $this->maps->textSearch((string) $job->query, $token, $job->latitude, $job->longitude, $job->radius)
            : $this->maps->nearbySearch((float) $job->latitude, (float) $job->longitude, (int) $job->radius, (string) $job->keyword, $job->type, $token);

        $fresh = [];
        foreach ($this->annotate($result['items']) as $row) {
            $id = $row['google_place_id'] ?? null;
            if (! $id || in_array($id, $seen, true)) {
                continue;
            }
            $seen[] = $id;
            $fresh[] = $row;
        }

        $pages++;
        $next = $result['next_page_token'] ?? null;
        $hasMore = (bool) $next && $pages < $maxPages;

        $job->update([
            'results_found' => $job->results_found + count($fresh),
            'duplicate_count' => $job->duplicate_count + collect($fresh)->where('is_duplicate', true)->count(),
            'meta' => [
                'next_page_token' => $next,
                'pages_loaded' => $pages,
                'max_pages' => $maxPages,
                'seen_ids' => $seen,
            ],
        ]);

        return [
            'items' => $fresh,
            'has_more' => $hasMore,
            'found' => $job->results_found,
            'duplicates' => $job->duplicate_count,
            'pages_loaded' => $pages,
            'max_pages' => $maxPages,
        ];
    }

    private function annotate(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            if (empty($row['google_place_id'])) {
                continue;
            }
            $dup = $this->duplicates->check($row);
            $row['is_duplicate'] = $dup['duplicate'];
            $row['duplicate_reason'] = $dup['reason'];
            $row['existing_lead_id'] = $dup['lead']?->id;
            $items[] = $row;
        }

        return $items;
    }

    public function enrich(array $placeIds): array
    {
        $placeIds = array_values(array_unique(array_filter($placeIds)));
        $max = (int) config('gymlead.defaults.max_selected_assign', 100);

        if (count($placeIds) > $max) {
            throw new GoogleMapsException("You can enrich at most {$max} places at once.");
        }

        $out = [];
        foreach ($placeIds as $id) {
            $out[] = $this->maps->placeDetails((string) $id);
        }

        return $out;
    }

    public function saveAndAssign(array $places, User $staff, User $actor, int $jobId): array
    {
        $job = ScrapeJob::query()->where('id', $jobId)->where('user_id', $actor->id)->first();
        if (! $job) {
            throw new GoogleMapsException('Scrape session expired. Run the search again.');
        }

        $enrich = Setting::getBool('enrich_on_assign', true);
        $saved = [];
        $duplicates = [];
        $errors = [];
        $skippedNoPhone = [];
        $payloads = [];

        foreach ($places as $place) {
            $placeId = $place['google_place_id'] ?? null;
            if (! $placeId) {
                $errors[] = 'Missing place id.';
                continue;
            }

            $payload = $place;
            if ($enrich) {
                try {
                    $payload = array_merge($place, $this->maps->placeDetails($placeId));
                } catch (\Throwable $e) {
                    $errors[] = ($place['name'] ?? $placeId).': '.$e->getMessage();
                }
            }

            if (! $this->hasPhone($payload['phone'] ?? null)) {
                $skippedNoPhone[] = [
                    'name' => $payload['name'] ?? '',
                    'reason' => 'no_phone',
                ];
                continue;
            }

            $payloads[] = $payload;
        }

        DB::transaction(function () use ($payloads, $actor, $job, &$saved, &$duplicates) {
            foreach ($payloads as $payload) {
                $check = $this->duplicates->check($payload);
                if ($check['duplicate']) {
                    $duplicates[] = [
                        'name' => $payload['name'] ?? '',
                        'reason' => $check['reason'],
                        'lead_id' => $check['lead']?->id,
                    ];
                    continue;
                }

                $lead = Lead::query()->create([
                    'google_place_id' => $payload['google_place_id'] ?? null,
                    'name' => mb_substr((string) ($payload['name'] ?? 'Unknown'), 0, 191),
                    'phone' => $payload['phone'] ?? null,
                    'email' => $payload['email'] ?? null,
                    'website' => $payload['website'] ?? null,
                    'address' => $payload['address'] ?? null,
                    'city' => $payload['city'] ?? null,
                    'state' => $payload['state'] ?? null,
                    'country' => $payload['country'] ?? null,
                    'postal_code' => $payload['postal_code'] ?? null,
                    'latitude' => $payload['latitude'] ?? null,
                    'longitude' => $payload['longitude'] ?? null,
                    'rating' => $payload['rating'] ?? null,
                    'reviews_count' => $payload['reviews_count'] ?? 0,
                    'category' => $payload['category'] ?? null,
                    'business_status' => $payload['business_status'] ?? null,
                    'google_maps_url' => $payload['google_maps_url'] ?? null,
                    'source' => LeadSource::GoogleMaps->value,
                    'status' => LeadStatus::New->value,
                    'raw_payload' => $payload['raw_payload'] ?? null,
                    'created_by' => $actor->id,
                    'scrape_job_id' => $job->id,
                ]);

                $saved[] = $lead;
            }
        });

        $assignedCount = 0;
        if ($saved) {
            $result = $this->assignment->assign(
                collect($saved)->pluck('id')->all(),
                $staff,
                $actor
            );
            $assignedCount = $result['assigned'];
        }

        $job->update([
            'status' => 'completed',
            'saved_count' => count($saved),
            'duplicate_count' => $job->duplicate_count + count($duplicates),
            'assigned_count' => $assignedCount,
        ]);

        $this->logger->log(
            'scrape.assign',
            'Saved '.count($saved).' Google Maps leads and assigned '.$assignedCount.' to '.$staff->name.'.',
            null,
            ['job_id' => $job->id, 'duplicates' => count($duplicates)]
        );

        return [
            'saved' => count($saved),
            'assigned' => $assignedCount,
            'duplicates' => $duplicates,
            'skipped_no_phone' => $skippedNoPhone,
            'errors' => $errors,
            'lead_ids' => collect($saved)->pluck('id')->all(),
        ];
    }

    private function hasPhone(?string $phone): bool
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return strlen($digits) >= 7;
    }

    private function resolveLocation(array $input): array
    {
        if (! empty($input['place_id'])) {
            return $this->maps->placeGeometry((string) $input['place_id']);
        }

        if (! empty($input['lat']) && ! empty($input['lng'])) {
            return [
                'place_id' => $input['place_id'] ?? null,
                'name' => $input['location'] ?? 'Custom pin',
                'address' => $input['location'] ?? '',
                'lat' => (float) $input['lat'],
                'lng' => (float) $input['lng'],
            ];
        }

        $location = trim((string) ($input['location'] ?? ''));
        if ($location === '') {
            throw new GoogleMapsException('Select or type a location.');
        }

        return $this->maps->geocode($location);
    }

    private function assertDailyCap(User $actor): void
    {
        $cap = Setting::getInt('scrape_max_per_day', 20);
        if ($cap <= 0) {
            return;
        }

        $used = ScrapeJob::query()
            ->where('user_id', $actor->id)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        if ($used >= $cap) {
            throw new GoogleMapsException("Daily scrape limit reached ({$cap}). Change it in Settings if needed.");
        }
    }
}
