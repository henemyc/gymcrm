<?php

namespace App\Services\GoogleMaps;

use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class GoogleMapsPlacesClient
{
    private const BASE = 'https://maps.googleapis.com/maps/api/place';
    private const GEOCODE = 'https://maps.googleapis.com/maps/api/geocode/json';

    public function apiKey(): string
    {
        $key = Setting::googleMapsApiKey();

        if ($key === '') {
            throw new GoogleMapsException('Google Maps API key is not configured. Add it under Settings.');
        }

        return $key;
    }

    public function ping(): array
    {
        $response = $this->http()->get(self::GEOCODE, [
            'address' => 'New Delhi',
            'key' => $this->apiKey(),
        ]);

        $json = $response->json() ?? [];
        $status = $json['status'] ?? 'UNKNOWN';

        if ($status === 'OK' || $status === 'ZERO_RESULTS') {
            return ['ok' => true, 'status' => $status, 'message' => 'API key is valid.'];
        }

        return [
            'ok' => false,
            'status' => $status,
            'message' => $json['error_message'] ?? 'Google rejected the API key or request.',
        ];
    }

    public function autocomplete(string $input): array
    {
        $this->throttle('autocomplete');

        $json = $this->get(self::BASE.'/autocomplete/json', [
            'input' => mb_substr($input, 0, 120),
            'types' => 'geocode',
        ]);

        $status = $json['status'] ?? 'UNKNOWN';
        if (! in_array($status, ['OK', 'ZERO_RESULTS'], true)) {
            throw new GoogleMapsException($this->errorMessage($json));
        }

        $predictions = [];
        foreach ($json['predictions'] ?? [] as $row) {
            $predictions[] = [
                'place_id' => $row['place_id'] ?? null,
                'description' => $row['description'] ?? '',
            ];
        }

        return $predictions;
    }

    public function placeGeometry(string $placeId): array
    {
        $json = $this->get(self::BASE.'/details/json', [
            'place_id' => $placeId,
            'fields' => 'place_id,name,formatted_address,geometry',
        ]);

        $this->assertOk($json);
        $result = $json['result'] ?? [];
        $loc = $result['geometry']['location'] ?? [];

        if (! isset($loc['lat'], $loc['lng'])) {
            throw new GoogleMapsException('Selected location has no coordinates.');
        }

        return [
            'place_id' => $result['place_id'] ?? $placeId,
            'name' => $result['name'] ?? '',
            'address' => $result['formatted_address'] ?? '',
            'lat' => (float) $loc['lat'],
            'lng' => (float) $loc['lng'],
        ];
    }

    public function geocode(string $address): array
    {
        $this->throttle('geocode');

        $response = $this->http()->get(self::GEOCODE, [
            'address' => mb_substr($address, 0, 200),
            'key' => $this->apiKey(),
        ]);

        $json = $response->json() ?? [];
        $this->assertOk($json);
        $first = $json['results'][0] ?? null;
        if (! $first) {
            throw new GoogleMapsException('Location not found.');
        }

        $loc = $first['geometry']['location'] ?? [];

        return [
            'place_id' => $first['place_id'] ?? null,
            'name' => $first['formatted_address'] ?? $address,
            'address' => $first['formatted_address'] ?? $address,
            'lat' => (float) $loc['lat'],
            'lng' => (float) $loc['lng'],
        ];
    }

    public function nearbySearch(float $lat, float $lng, int $radius, string $keyword, ?string $type = null, ?string $pageToken = null): array
    {
        $this->throttle('search');

        $params = [
            'location' => $lat.','.$lng,
            'radius' => max(50, min($radius, 50000)),
        ];

        if ($pageToken) {
            $params = ['pagetoken' => $pageToken];
        } else {
            if ($keyword !== '') {
                $params['keyword'] = $keyword;
            }
            if ($type) {
                $params['type'] = $type;
            }
        }

        $json = $this->get(self::BASE.'/nearbysearch/json', $params);

        return $this->normalizeList($json);
    }

    public function textSearch(string $query, ?string $pageToken = null, ?float $lat = null, ?float $lng = null, ?int $radius = null): array
    {
        $this->throttle('search');

        $params = [];
        if ($pageToken) {
            $params['pagetoken'] = $pageToken;
        } else {
            $params['query'] = mb_substr($query, 0, 200);
            if ($lat !== null && $lng !== null) {
                $params['location'] = $lat.','.$lng;
                if ($radius) {
                    $params['radius'] = $radius;
                }
            }
        }

        $json = $this->get(self::BASE.'/textsearch/json', $params);

        return $this->normalizeList($json);
    }

    public function placeDetails(string $placeId): array
    {
        $this->throttle('details');

        $json = $this->get(self::BASE.'/details/json', [
            'place_id' => $placeId,
            'fields' => implode(',', [
                'place_id',
                'name',
                'formatted_address',
                'address_component',
                'geometry',
                'formatted_phone_number',
                'international_phone_number',
                'website',
                'url',
                'rating',
                'user_ratings_total',
                'types',
                'business_status',
                'opening_hours',
            ]),
        ]);

        $this->assertOk($json);

        return $this->normalizeDetails($json['result'] ?? []);
    }

    private function normalizeList(array $json): array
    {
        $status = $json['status'] ?? 'UNKNOWN';
        if (! in_array($status, ['OK', 'ZERO_RESULTS'], true)) {
            throw new GoogleMapsException($this->errorMessage($json));
        }

        $items = [];
        foreach ($json['results'] ?? [] as $row) {
            $loc = $row['geometry']['location'] ?? [];
            $items[] = [
                'google_place_id' => $row['place_id'] ?? null,
                'name' => $row['name'] ?? '',
                'address' => $row['vicinity'] ?? ($row['formatted_address'] ?? ''),
                'latitude' => isset($loc['lat']) ? (float) $loc['lat'] : null,
                'longitude' => isset($loc['lng']) ? (float) $loc['lng'] : null,
                'rating' => $row['rating'] ?? null,
                'reviews_count' => $row['user_ratings_total'] ?? 0,
                'category' => isset($row['types'][0]) ? (string) $row['types'][0] : null,
                'business_status' => $row['business_status'] ?? null,
                'phone' => null,
                'website' => null,
                'google_maps_url' => isset($row['place_id'])
                    ? 'https://www.google.com/maps/place/?q=place_id:'.$row['place_id']
                    : null,
            ];
        }

        return [
            'status' => $status,
            'items' => $items,
            'next_page_token' => $json['next_page_token'] ?? null,
        ];
    }

    private function normalizeDetails(array $row): array
    {
        $loc = $row['geometry']['location'] ?? [];
        $components = $row['address_components'] ?? [];

        $pick = function (array $types) use ($components): ?string {
            foreach ($components as $c) {
                $cTypes = $c['types'] ?? [];
                foreach ($types as $t) {
                    if (in_array($t, $cTypes, true)) {
                        return $c['long_name'] ?? null;
                    }
                }
            }

            return null;
        };

        return [
            'google_place_id' => $row['place_id'] ?? null,
            'name' => $row['name'] ?? '',
            'phone' => $row['international_phone_number'] ?? ($row['formatted_phone_number'] ?? null),
            'website' => $row['website'] ?? null,
            'address' => $row['formatted_address'] ?? null,
            'city' => $pick(['locality', 'administrative_area_level_2']),
            'state' => $pick(['administrative_area_level_1']),
            'country' => $pick(['country']),
            'postal_code' => $pick(['postal_code']),
            'latitude' => isset($loc['lat']) ? (float) $loc['lat'] : null,
            'longitude' => isset($loc['lng']) ? (float) $loc['lng'] : null,
            'rating' => $row['rating'] ?? null,
            'reviews_count' => $row['user_ratings_total'] ?? 0,
            'category' => isset($row['types'][0]) ? (string) $row['types'][0] : null,
            'business_status' => $row['business_status'] ?? null,
            'google_maps_url' => $row['url'] ?? (isset($row['place_id'])
                ? 'https://www.google.com/maps/place/?q=place_id:'.$row['place_id']
                : null),
            'raw_payload' => $row,
        ];
    }

    private function get(string $url, array $params): array
    {
        $params['key'] = $this->apiKey();
        $response = $this->http()->get($url, $params);

        if (! $response->successful()) {
            throw new GoogleMapsException('Google Maps request failed (HTTP '.$response->status().').');
        }

        return $response->json() ?? [];
    }

    private function http(): PendingRequest
    {
        return Http::timeout(20)->retry(1, 500)->acceptJson();
    }

    private function assertOk(array $json): void
    {
        $status = $json['status'] ?? 'UNKNOWN';
        if ($status !== 'OK') {
            throw new GoogleMapsException($this->errorMessage($json));
        }
    }

    private function errorMessage(array $json): string
    {
        $status = $json['status'] ?? 'UNKNOWN';
        $extra = $json['error_message'] ?? '';

        return trim("Google Maps API error: {$status}. {$extra}");
    }

    private function throttle(string $bucket): void
    {
        $key = 'gmaps:'.$bucket.':'.(auth()->id() ?: 'guest');
        $max = match ($bucket) {
            'search' => 10,
            'details' => 60,
            default => 30,
        };

        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw new GoogleMapsException('Too many Google Maps requests. Please wait a minute and try again.');
        }

        RateLimiter::hit($key, 60);
    }
}
