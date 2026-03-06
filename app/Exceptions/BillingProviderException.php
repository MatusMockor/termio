<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class BillingProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?int $httpStatus = null,
        private readonly ?string $stripeCode = null,
        private readonly ?string $stripeParam = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromThrowable(Throwable $exception): self
    {
        $httpStatus = method_exists($exception, 'getHttpStatus')
            ? $exception->getHttpStatus()
            : null;
        $stripeCode = method_exists($exception, 'getStripeCode')
            ? $exception->getStripeCode()
            : null;
        $stripeParam = method_exists($exception, 'getStripeParam')
            ? $exception->getStripeParam()
            : null;

        return new self(
            $exception->getMessage(),
            is_int($httpStatus) ? $httpStatus : null,
            is_string($stripeCode) ? $stripeCode : null,
            is_string($stripeParam) ? $stripeParam : null,
            $exception,
        );
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function getStripeCode(): ?string
    {
        return $this->stripeCode;
    }

    public function getStripeParam(): ?string
    {
        return $this->stripeParam;
    }

    public function isMissingCustomerError(): bool
    {
        if ($this->httpStatus === 404) {
            return true;
        }

        if ($this->stripeParam === 'customer') {
            return true;
        }

        return $this->stripeCode === 'resource_missing';
    }
}
