<?php

namespace App\Services\Payments;

use RuntimeException;

/**
 * A payment problem whose message is safe to show to the customer.
 * Never include secrets, signatures or raw gateway responses in the message.
 */
class PaymentException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
