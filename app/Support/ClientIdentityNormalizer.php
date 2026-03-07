<?php

declare(strict_types=1);

namespace App\Support;

final class ClientIdentityNormalizer
{
    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        return $digits !== '' ? $digits : null;
    }

    public static function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($email));

        return $normalized !== '' ? $normalized : null;
    }
}
