<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'is_encrypted',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember('setting.'.$key, 60, function () use ($key, $default) {
            $row = static::query()->where('key', $key)->first();

            if (! $row) {
                return $default;
            }

            if ($row->is_encrypted && $row->value) {
                try {
                    return Crypt::decryptString($row->value);
                } catch (\Throwable) {
                    return $default;
                }
            }

            return $row->value ?? $default;
        });
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = static::get($key, $default ? '1' : '0');

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function getInt(string $key, int $default = 0): int
    {
        return (int) static::get($key, $default);
    }

    public static function put(string $key, mixed $value, bool $encrypted = false): void
    {
        $store = $encrypted && filled($value) ? Crypt::encryptString((string) $value) : (string) $value;

        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $store, 'is_encrypted' => $encrypted]
        );

        Cache::forget('setting.'.$key);
    }

    public static function googleMapsApiKey(): string
    {
        $fromDb = trim((string) static::get('google_maps_api_key', ''));

        if ($fromDb !== '') {
            return $fromDb;
        }

        return trim((string) config('gymlead.google_maps_api_key'));
    }

    public static function forgetCache(): void
    {
        $keys = static::query()->pluck('key');
        foreach ($keys as $key) {
            Cache::forget('setting.'.$key);
        }
    }
}
