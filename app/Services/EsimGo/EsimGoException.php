<?php

namespace App\Services\EsimGo;

use RuntimeException;

/**
 * A safe, credential-free description of an eSIM Go failure.
 */
class EsimGoException extends RuntimeException
{
    public const NOT_CONFIGURED = 'not_configured';

    public const AUTHENTICATION = 'authentication';   // 401 / 403

    public const RATE_LIMITED = 'rate_limited';       // 429

    public const UNAVAILABLE = 'unavailable';         // 5xx

    public const CONNECTION = 'connection';           // DNS, TLS, timeout...

    public const REQUEST = 'request';                 // 400 / 404 / refused locally

    public const INVALID_RESPONSE = 'invalid_response';

    public const INSUFFICIENT_BALANCE = 'insufficient_balance';   // 4xx mentioning balance / funds

    public function __construct(string $message, public readonly string $kind, public readonly ?int $status = null)
    {
        parent::__construct($message);
    }

    /**
     * Could eSIM Go have processed the request anyway? (timeout, connection drop,
     * 5xx, unreadable 2xx). For a purchase this means "maybe bought": it must
     * never be re-sent automatically.
     */
    public function outcomeUncertain(): bool
    {
        return in_array($this->kind, [self::CONNECTION, self::UNAVAILABLE, self::INVALID_RESPONSE], true);
    }
}
