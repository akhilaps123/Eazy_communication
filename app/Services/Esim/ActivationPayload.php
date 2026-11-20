<?php

namespace App\Services\Esim;

use App\Models\Esim;

/**
 * Phase 9D — the eSIM activation code that a phone scans to download the eSIM.
 *
 * Format (GSMA SGP.22 "activation code"), exactly as eSIM Go uses it in its own
 * install links (GET /esims/{iccid} → appleInstallUrl example:
 * "…?carddata=LPA:1$rsp-1234.example.com$ABCD-ABCD-ABCD-ABCD"):
 *
 *     LPA:1$<SM-DP+ address>$<matching ID>
 *
 * Built ONLY from the two values eSIM Go returns for the eSIM — smdpAddress and
 * matchingId — and nothing else (no customer, order or bundle data).
 *
 * eSIM Go sometimes writes smdpAddress with a scheme ("http://rsp.example.com")
 * while its install-link example uses the bare host, so a leading http(s)://
 * and one trailing "/" are removed. Anything that is still not a plain host
 * name (paths, ports, "$", spaces …) is rejected — never "repaired".
 */
final class ActivationPayload
{
    public const PREFIX = 'LPA:1$';

    private const HOST = '/^(?=.{4,253}$)(?:[A-Za-z0-9](?:[A-Za-z0-9\-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63}$/';

    private const MATCHING_ID = '/^[A-Za-z0-9\-]{1,255}$/';

    private const ICCID = '/^[0-9]{18,22}$/';

    private function __construct(public readonly string $iccid, public readonly string $smdpHost, public readonly string $matchingId) {}

    /** @throws InvalidActivationData with a message that never contains the secret values */
    public static function fromEsim(Esim $esim): self
    {
        return self::from((string) $esim->iccid, $esim->smdp_address, $esim->activation_code);
    }

    /** @throws InvalidActivationData */
    public static function from(?string $iccid, ?string $smdpAddress, ?string $matchingId): self
    {
        $iccid = trim((string) $iccid);
        $smdp = trim((string) $smdpAddress);
        $matching = trim((string) $matchingId);

        if ($iccid === '') {
            throw new InvalidActivationData('The ICCID is missing.');
        }
        if (! preg_match(self::ICCID, $iccid)) {
            throw new InvalidActivationData('The ICCID is not a valid 18–22 digit number.');
        }
        if ($smdp === '') {
            throw new InvalidActivationData('The SM-DP+ address is missing.');
        }
        if ($matching === '') {
            throw new InvalidActivationData('The activation code (matching ID) is missing.');
        }

        $host = preg_replace('#^https?://#i', '', $smdp);
        $host = str_ends_with($host, '/') ? substr($host, 0, -1) : $host;
        if (! preg_match(self::HOST, $host)) {
            throw new InvalidActivationData('The SM-DP+ address is not a valid host name.');
        }
        if (! preg_match(self::MATCHING_ID, $matching)) {
            throw new InvalidActivationData('The activation code (matching ID) contains characters that are not allowed.');
        }

        return new self($iccid, $host, $matching);
    }

    /** The exact text encoded in the QR code. Secret: never log or audit it. */
    public function toString(): string
    {
        return self::PREFIX.$this->smdpHost.'$'.$this->matchingId;
    }
}
