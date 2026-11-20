<?php

namespace App\Support;

use App\Models\Order;

/**
 * Order numbers: EC-YYMMDD-XXXXXX (approved decision 3), e.g. EC-261117-K7Q2M9.
 * Date in the application timezone + 6 random uppercase letters/digits
 * (cryptographically random, so numbers cannot be guessed). 16 characters,
 * well inside the 30-character column; the unique index is the final guard.
 */
class OrderNumber
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    public static function generate(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $number = self::candidate();
            if (! Order::query()->where('order_number', $number)->exists()) {
                return $number;
            }
        }

        throw new \RuntimeException('Could not generate a unique order number.');
    }

    public static function candidate(): string
    {
        $random = '';
        for ($i = 0; $i < 6; $i++) {
            $random .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return 'EC-'.now()->format('ymd').'-'.$random;
    }

    public static function isValid(string $number): bool
    {
        return (bool) preg_match('/^EC-\d{6}-[A-Z0-9]{6}$/', $number);
    }
}
