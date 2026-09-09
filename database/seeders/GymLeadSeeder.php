<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class GymLeadSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@gymlead.test'],
            [
                'name' => 'Gym Admin',
                'password' => 'password',
                'role' => UserRole::Admin->value,
                'is_active' => true,
                'phone' => '9999999999',
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'staff@gymlead.test'],
            [
                'name' => 'Sales Staff',
                'password' => 'password',
                'role' => UserRole::Staff->value,
                'is_active' => true,
                'phone' => '8888888888',
                'daily_lead_limit' => 50,
            ]
        );

        $defaults = config('gymlead.defaults');
        foreach ($defaults as $key => $value) {
            if (Setting::query()->where('key', $key)->exists()) {
                continue;
            }
            $store = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            Setting::put($key, $store, false);
        }

        if (! Setting::query()->where('key', 'company_name')->exists()) {
            Setting::put('company_name', 'GymLead CRM');
        }
    }
}
