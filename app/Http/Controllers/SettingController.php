<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\GoogleMaps\GoogleMapsException;
use App\Services\GoogleMaps\GoogleMapsPlacesClient;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $key = Setting::googleMapsApiKey();

        return view('settings.index', [
            'settings' => [
                'google_maps_api_key_set' => $key !== '',
                'google_maps_api_key_masked' => $this->mask($key),
                'scrape_max_pages' => Setting::getInt('scrape_max_pages', 3),
                'scrape_max_per_day' => Setting::getInt('scrape_max_per_day', 20),
                'scrape_default_radius' => Setting::getInt('scrape_default_radius', 3000),
                'scrape_max_radius' => Setting::getInt('scrape_max_radius', 50000),
                'scrape_default_keyword' => Setting::get('scrape_default_keyword', 'gym'),
                'leads_per_page' => Setting::getInt('leads_per_page', 15),
                'staff_can_export' => Setting::getBool('staff_can_export', false),
                'duplicate_check_place_id' => Setting::getBool('duplicate_check_place_id', true),
                'duplicate_check_phone' => Setting::getBool('duplicate_check_phone', true),
                'duplicate_check_name_address' => Setting::getBool('duplicate_check_name_address', true),
                'enrich_on_assign' => Setting::getBool('enrich_on_assign', true),
                'max_import_rows' => Setting::getInt('max_import_rows', 2000),
                'company_name' => Setting::get('company_name', 'GymLead CRM'),
            ],
        ]);
    }

    public function update(Request $request, ActivityLogger $logger)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:120'],
            'google_maps_api_key' => ['nullable', 'string', 'max:200'],
            'scrape_max_pages' => ['required', 'integer', 'min:1', 'max:3'],
            'scrape_max_per_day' => ['required', 'integer', 'min:1', 'max:200'],
            'scrape_default_radius' => ['required', 'integer', 'min:50', 'max:50000'],
            'scrape_max_radius' => ['required', 'integer', 'min:100', 'max:50000'],
            'scrape_default_keyword' => ['required', 'string', 'max:80'],
            'leads_per_page' => ['required', 'integer', 'min:10', 'max:50'],
            'max_import_rows' => ['required', 'integer', 'min:10', 'max:10000'],
            'staff_can_export' => ['sometimes', 'boolean'],
            'duplicate_check_place_id' => ['sometimes', 'boolean'],
            'duplicate_check_phone' => ['sometimes', 'boolean'],
            'duplicate_check_name_address' => ['sometimes', 'boolean'],
            'enrich_on_assign' => ['sometimes', 'boolean'],
        ]);

        Setting::put('company_name', $data['company_name']);

        if (filled($data['google_maps_api_key'] ?? null) && ! str_contains($data['google_maps_api_key'], '•')) {
            Setting::put('google_maps_api_key', trim($data['google_maps_api_key']), true);
        }

        foreach ([
            'scrape_max_pages',
            'scrape_max_per_day',
            'scrape_default_radius',
            'scrape_max_radius',
            'scrape_default_keyword',
            'leads_per_page',
            'max_import_rows',
        ] as $key) {
            Setting::put($key, (string) $data[$key]);
        }

        foreach ([
            'staff_can_export',
            'duplicate_check_place_id',
            'duplicate_check_phone',
            'duplicate_check_name_address',
            'enrich_on_assign',
        ] as $boolKey) {
            Setting::put($boolKey, $request->boolean($boolKey) ? '1' : '0');
        }

        $logger->log('settings.updated', 'Updated application settings.');

        return response()->json(['ok' => true, 'message' => 'Settings saved.']);
    }

    public function testMaps(GoogleMapsPlacesClient $maps)
    {
        try {
            $result = $maps->ping();
        } catch (GoogleMapsException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json($result + ['ok' => $result['ok']]);
    }

    private function mask(string $key): string
    {
        if ($key === '') {
            return '';
        }
        $len = strlen($key);
        if ($len < 8) {
            return str_repeat('•', $len);
        }

        return substr($key, 0, 4).str_repeat('•', max(6, $len - 8)).substr($key, -4);
    }
}
