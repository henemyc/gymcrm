<?php

namespace App\Enums;

enum LeadSource: string
{
    case GoogleMaps = 'google_maps';
    case Manual = 'manual';
    case Import = 'import';

    public function label(): string
    {
        return match ($this) {
            self::GoogleMaps => 'Google Maps',
            self::Manual => 'Manual',
            self::Import => 'CSV Import',
        };
    }
}
