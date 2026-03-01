<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Contracts\Services\StripeService;
use App\DTOs\Billing\PortalSessionDTO;
use App\Exceptions\BillingException;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\RateLimitException;

final class BillingPortalSessionCreateAction
{
    public function __construct(
        private readonly StripeService $stripeService,
    ) {}

    public function handle(Tenant $tenant, string $returnUrl): PortalSessionDTO
    {
        $this->ensureStripeCustomer($tenant);

        try {
            $portalUrl = $this->stripeService->createBillingPortalSession(
                (string) $tenant->stripe_id,
                $returnUrl,
            );
        } catch (ApiConnectionException|RateLimitException $exception) {
            $this->logPortalSessionError($tenant, $exception);

            throw BillingException::serviceUnavailable();
        } catch (ApiErrorException $exception) {
            $this->logPortalSessionError($tenant, $exception);

            if ($this->isMissingCustomerError($exception)) {
                throw BillingException::portalSessionUnavailable();
            }

            throw BillingException::serviceUnavailable();
        }

        return new PortalSessionDTO(url: $portalUrl);
    }

    private function ensureStripeCustomer(Tenant $tenant): void
    {
        if ($tenant->stripe_id) {
            return;
        }

        try {
            $customer = $this->stripeService->createCustomer($tenant);
        } catch (ApiErrorException|RuntimeException $exception) {
            Log::error('Failed to create Stripe customer for billing portal.', [
                'tenant_id' => $tenant->id,
                'error' => $exception->getMessage(),
            ]);

            throw BillingException::serviceUnavailable();
        }

        $tenant->update(['stripe_id' => $customer->id]);
    }

    private function isMissingCustomerError(ApiErrorException $exception): bool
    {
        if ($exception->getHttpStatus() === 404) {
            return true;
        }

        if ($exception instanceof InvalidRequestException && $exception->getStripeParam() === 'customer') {
            return true;
        }

        return $exception->getStripeCode() === 'resource_missing';
    }

    private function logPortalSessionError(Tenant $tenant, ApiErrorException $exception): void
    {
        Log::error('Failed to create billing portal session.', [
            'tenant_id' => $tenant->id,
            'stripe_id' => $tenant->stripe_id,
            'http_status' => $exception->getHttpStatus(),
            'stripe_code' => $exception->getStripeCode(),
            'error' => $exception->getMessage(),
        ]);
    }
}
