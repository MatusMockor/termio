<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use RuntimeException;

final class ClientBookingAccessException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ApiErrorCode $errorCode,
        private readonly int $statusCode = 403,
    ) {
        parent::__construct($message);
    }

    public static function blacklisted(): self
    {
        return new self(
            message: 'Online booking is not available for this client.',
            errorCode: ApiErrorCode::ClientBlacklisted,
            statusCode: 403,
        );
    }

    public function getErrorCode(): ApiErrorCode
    {
        return $this->errorCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
