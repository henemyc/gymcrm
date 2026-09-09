<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Setting;
use App\Support\PhoneNormalizer;

class DuplicateGuard
{
    /**
     * @return array{duplicate: bool, reason: ?string, lead: ?Lead}
     */
    public function check(array $payload): array
    {
        $checkPlace = Setting::getBool('duplicate_check_place_id', true);
        $checkPhone = Setting::getBool('duplicate_check_phone', true);
        $checkName = Setting::getBool('duplicate_check_name_address', true);

        if ($checkPlace && ! empty($payload['google_place_id'])) {
            $lead = Lead::withTrashed()->where('google_place_id', $payload['google_place_id'])->first();
            if ($lead) {
                return ['duplicate' => true, 'reason' => 'google_place_id', 'lead' => $lead];
            }
        }

        $phone = PhoneNormalizer::normalize($payload['phone'] ?? null);
        if ($checkPhone && $phone) {
            $lead = Lead::withTrashed()->where('phone_normalized', $phone)->first();
            if ($lead) {
                return ['duplicate' => true, 'reason' => 'phone', 'lead' => $lead];
            }
        }

        if ($checkName && ! empty($payload['name'])) {
            $hash = Lead::makeDuplicateHash(
                $payload['name'] ?? null,
                $payload['address'] ?? null,
                $payload['latitude'] ?? null,
                $payload['longitude'] ?? null
            );
            if ($hash) {
                $lead = Lead::withTrashed()->where('duplicate_hash', $hash)->first();
                if ($lead) {
                    return ['duplicate' => true, 'reason' => 'name_address', 'lead' => $lead];
                }
            }
        }

        return ['duplicate' => false, 'reason' => null, 'lead' => null];
    }
}
