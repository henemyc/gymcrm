<?php

namespace App\Support;

class PhoneNormalizer
{
    public static function normalize(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) < 7) {
            return null;
        }

        return $digits;
    }

    public static function international(?string $phone): ?string
    {
        $digits = self::normalize($phone);
        if (! $digits) {
            return null;
        }
        if (strlen($digits) === 10) {
            return '91'.$digits;
        }

        return $digits;
    }

    public static function telHref(?string $phone): ?string
    {
        $intl = self::international($phone);

        return $intl ? 'tel:+'.$intl : null;
    }

    public static function whatsappHref(?string $phone): ?string
    {
        $intl = self::international($phone);

        return $intl ? 'https://wa.me/'.$intl : null;
    }
}
