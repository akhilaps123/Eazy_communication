<?php

namespace App\Support;

use Locale;

/**
 * ISO 3166-1 alpha-2 billing countries with English names, from PHP's intl
 * extension (ICU data) — no hand-maintained list. Pseudo/reserved codes are left out.
 */
class BillingCountries
{
    /**
     * Codes ICU knows that are not current ISO 3166-1 countries: user-assigned,
     * groupings, exceptionally reserved (AC, CP, DG, EA, IC, TA) and withdrawn codes.
     */
    private const EXCLUDED = [
        'AA', 'EU', 'EZ', 'QO', 'UN', 'ZZ',
        'AC', 'CP', 'DG', 'EA', 'IC', 'TA',
        'AN', 'BU', 'CS', 'DD', 'DY', 'FX', 'HV', 'NH', 'NT', 'QU', 'RH', 'SU', 'TP', 'YD', 'YU', 'ZR',
        'UK', 'VD',   // aliases of GB and VN
    ];

    private static ?array $cache = null;

    /** @return array<string, string> ISO2 => name, sorted by name */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $countries = [];
        foreach (range('A', 'Z') as $first) {
            foreach (range('A', 'Z') as $second) {
                $code = $first.$second;
                if (in_array($code, self::EXCLUDED, true) || ($first === 'Q' && $second >= 'M') || $first === 'X') {
                    continue;
                }
                $name = Locale::getDisplayRegion('-'.$code, 'en');
                if ($name !== '' && $name !== $code) {
                    $countries[$code] = $name;
                }
            }
        }
        asort($countries, SORT_NATURAL | SORT_FLAG_CASE);

        return self::$cache = $countries;
    }

    public static function name(?string $code): ?string
    {
        return $code ? (self::all()[strtoupper($code)] ?? null) : null;
    }
}
