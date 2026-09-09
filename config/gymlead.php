<?php

return [
    'name' => env('APP_NAME', 'GymLead CRM'),

    /*
    | Fallback only. Production key must be set from Admin → Settings.
    | The live scraper always prefers the encrypted database value.
    */
    'google_maps_api_key' => env('GOOGLE_MAPS_API_KEY', ''),

    'defaults' => [
        'scrape_max_pages' => 3,
        'scrape_max_per_day' => 20,
        'scrape_default_radius' => 3000,
        'scrape_max_radius' => 50000,
        'scrape_default_keyword' => 'gym',
        'leads_per_page' => 15,
        'staff_can_export' => false,
        'duplicate_check_place_id' => true,
        'duplicate_check_phone' => true,
        'duplicate_check_name_address' => true,
        'enrich_on_assign' => true,
        'max_import_rows' => 2000,
        'max_selected_assign' => 100,
    ],

    'place_types' => [
        'gym' => 'Gym',
        'health' => 'Health',
        'spa' => 'Spa',
        'stadium' => 'Stadium',
        'school' => 'School / Academy',
        'store' => 'Store',
        'establishment' => 'Establishment',
    ],

    'lead_statuses' => [
        'new' => 'New',
        'contacted' => 'Contacted',
        'follow_up' => 'Follow Up',
        'meeting_scheduled' => 'Meeting scheduled',
        'interested' => 'Interested',
        'not_interested' => 'Not Interested',
        'converted' => 'Converted',
        'invalid' => 'Invalid',
        'duplicate' => 'Duplicate',
    ],
];
