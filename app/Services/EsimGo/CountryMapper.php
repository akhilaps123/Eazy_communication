<?php

namespace App\Services\EsimGo;

use App\Support\IsoCountries;

/**
 * Maps an eSIM Go bundle's location data to ISO 3166-1 alpha-2 country codes.
 *
 * Provider data (GET /catalogue, as received):
 * - countries[]:      the bundle's main location(s), each {name, region, iso}.
 *                     Single-country bundles: iso is a country code, e.g. "FR".
 *                     Regional bundles: iso is a region name, e.g. "Europe Lite",
 *                     "Asia", "Global". Special locations: "CYP" (Northern
 *                     Cyprus), "US-HI" (Hawaii, an ISO 3166-2 subdivision).
 * - roamingEnabled[]: countries the bundle also works in, each {name, region, iso}.
 *                     For regional bundles this is the list of covered countries.
 *
 * Rules: a code is kept only if it is an officially assigned ISO 3166-1
 * alpha-2 code (App\Support\IsoCountries). Nothing is guessed: region names
 * are resolved only through the provider's own roamingEnabled list, and any
 * identifier that cannot be mapped is returned in "unmapped" for review
 * (the original value always stays in the bundle's raw_payload).
 */
final class CountryMapper
{
    /**
     * @return array{
     *     codes: list<string>,          ISO 3166-1 alpha-2 codes, unique and sorted
     *     resolved: list<string>,       non-ISO main locations whose countries came from roamingEnabled (e.g. "Europe Lite", "CYP")
     *     unmapped: list<string>,       identifiers that could not be mapped (e.g. "US-HI", "AN")
     *     regional: bool,               main location is not a country, countries come from roamingEnabled
     *     roaming_added: int            extra roaming countries added to a country bundle
     * }
     */
    public static function map(array $raw): array
    {
        $main = [];
        $mainOther = [];
        $roaming = [];
        $unmapped = [];

        foreach (self::isoValues($raw['countries'] ?? []) as $value) {
            IsoCountries::isValid(strtoupper($value)) ? $main[strtoupper($value)] = true : $mainOther[$value] = true;
        }
        foreach (self::isoValues($raw['roamingEnabled'] ?? []) as $value) {
            IsoCountries::isValid(strtoupper($value)) ? $roaming[strtoupper($value)] = true : $unmapped[$value] = true;
        }

        $codes = $main + $roaming;                              // keys are codes: no duplicates
        $roamingOnly = array_diff_key($roaming, $main);

        // Region names / special locations are resolved only by the provider's own
        // country list; without one they stay unmapped (never guessed).
        $resolved = [];
        foreach (array_keys($mainOther) as $value) {
            $roaming !== [] ? $resolved[$value] = true : $unmapped[$value] = true;
        }

        $codes = array_keys($codes);
        sort($codes);

        return [
            'codes' => $codes,
            'resolved' => self::sorted($resolved),
            'unmapped' => self::sorted($unmapped),
            'regional' => $main === [] && $resolved !== [],
            'roaming_added' => $main !== [] ? count($roamingOnly) : 0,
        ];
    }

    /** @return list<string> */
    private static function sorted(array $keyed): array
    {
        $values = array_map('strval', array_keys($keyed));
        sort($values);

        return $values;
    }

    /** @return list<string> trimmed, non-empty iso values */
    private static function isoValues(mixed $entries): array
    {
        $values = [];
        foreach ((array) $entries as $entry) {
            $iso = is_array($entry) ? trim((string) ($entry['iso'] ?? '')) : '';
            if ($iso !== '' && mb_strlen($iso) <= 100) {
                $values[] = $iso;
            }
        }

        return $values;
    }
}
