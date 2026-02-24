<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class InvalidOnboardingDataException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $errors
     */
    public static function forTenantWorkingHours(int $tenantId, array $errors = []): self
    {
        $message = "Invalid onboarding working_hours payload for tenant {$tenantId}.";

        if (! $errors) {
            return new self($message);
        }

        return new self($message.' Validation errors: '.json_encode($errors, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    public static function forTenantReservationSettings(int $tenantId, array $errors = []): self
    {
        $message = "Invalid onboarding reservation_settings payload for tenant {$tenantId}.";

        if (! $errors) {
            return new self($message);
        }

        return new self($message.' Validation errors: '.json_encode($errors, JSON_THROW_ON_ERROR));
    }
}
