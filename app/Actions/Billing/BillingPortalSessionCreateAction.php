<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Contracts\Services\StripeBillingGatewayContract;
use App\Contracts\Services\StripeCustomerProvisionerContract;
use App\DTOs\Billing\PortalSessionDTO;
use App\Exceptions\BillingException;
use App\Exceptions\BillingProviderException;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

final class BillingPortalSessionCreateAction
{
    public function __construct(
        private readonly StripeCustomerProvisionerContract $stripeCustomerProvisioner,
        private readonly StripeBillingGatewayContract $billingGateway,
    ) {}

    public function handle(Tenant $tenant, string $returnUrl): PortalSessionDTO
    {
        try {
            $customerId = $this->stripeCustomerProvisioner->ensureCustomerId($tenant);
            $portalUrl = $this->billingGateway->createPortalSession(
                $customerId,
                $returnUrl,
            );
        } catch (BillingProviderException $exception) {
            $this->logPortalSessionError($tenant, $exception);

            if ($exception->isMissingCustomerError()) {
                throw BillingException::portalSessionUnavailable();
            }

            throw BillingException::serviceUnavailable();
        }

        return new PortalSessionDTO(url: $portalUrl);
    }

    private function logPortalSessionError(Tenant $tenant, BillingProviderException $exception): void
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
