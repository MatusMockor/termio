<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Services\StripeCustomerProvisionerContract;
use App\Contracts\Services\StripeService as StripeServiceContract;
use App\Exceptions\BillingProviderException;
use App\Models\Tenant;
use Throwable;

final class StripeCustomerProvisioner implements StripeCustomerProvisionerContract
{
    public function __construct(
        private readonly StripeServiceContract $stripeService,
    ) {}

    public function ensureCustomerId(Tenant $tenant): string
    {
        if ($tenant->hasStripeId()) {
            return (string) $tenant->stripe_id;
        }

        try {
            $connectionName = $tenant->getConnectionName();
            $connection = Tenant::resolveConnection($connectionName);

            return $connection->transaction(function () use ($tenant, $connectionName): string {
                $lockedTenant = Tenant::on($connectionName)
                    ->whereKey($tenant->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedTenant->hasStripeId()) {
                    /** @var string $existingCustomerId */
                    $existingCustomerId = $lockedTenant->stripe_id;
                    $tenant->stripe_id = $existingCustomerId;

                    return $existingCustomerId;
                }

                $customer = $this->stripeService->createCustomer($lockedTenant);
                $lockedTenant->update(['stripe_id' => $customer->id]);
                $tenant->stripe_id = $customer->id;

                return $customer->id;
            });
        } catch (BillingProviderException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw BillingProviderException::fromThrowable($exception);
        }
    }
}
