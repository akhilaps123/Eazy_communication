<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A customer-facing cart or checkout problem (safe to show as a message).
 * Never put internal details (costs, bundle codes) in the message.
 */
class CartException extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'cart')
    {
        parent::__construct($message);
    }
}
