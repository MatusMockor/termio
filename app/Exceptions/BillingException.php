<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

final class BillingException extends Exception
{
    public function __construct(string $message, private readonly int $statusCode)
    {
        parent::__construct($message);
    }

    public static function portalSessionUnavailable(): self
    {
        return new self('Unable to create billing portal session.', 502);
    }

    public static function serviceUnavailable(): self
    {
        return new self('Billing service temporarily unavailable.', 503);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
