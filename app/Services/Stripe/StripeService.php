<?php

declare(strict_types=1);

namespace App\Services\Stripe;

use App\Contracts\Services\StripeService as StripeServiceContract;
use App\Models\Tenant;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentMethod;
use Stripe\Price;
use Stripe\Product;
use Stripe\StripeClient;

final class StripeService implements StripeServiceContract
{
    private ?StripeClient $stripe = null;

    /**
     * Get the Stripe client, creating it lazily.
     *
     * @throws \RuntimeException if Stripe is not configured
     */
    private function getClient(): StripeClient
    {
        if ($this->stripe !== null) {
            return $this->stripe;
        }

        $secret = config('cashier.secret');

        if (empty($secret)) {
            throw new \RuntimeException('Stripe is not configured. Please set STRIPE_SECRET in your environment.');
        }

        $this->stripe = new StripeClient((string) $secret);

        return $this->stripe;
    }

    /**
     * Check if Stripe is configured.
     */
    public function isConfigured(): bool
    {
        return ! empty(config('cashier.secret'));
    }

    /**
     * Create a new Stripe customer for the given tenant.
     *
     * @throws ApiErrorException
     */
    public function createCustomer(Tenant $tenant): Customer
    {
        $owner = $tenant->owner;

        $params = [
            'name' => $tenant->name,
            'metadata' => [
                'tenant_id' => (string) $tenant->id,
            ],
        ];

        if ($owner !== null) {
            $params['email'] = $owner->email;
        }

        if ($tenant->phone !== null) {
            $params['phone'] = $tenant->phone;
        }

        if ($tenant->address !== null) {
            $params['address'] = [
                'line1' => $tenant->address,
            ];
        }

        return $this->getClient()->customers->create($params);
    }

    /**
     * Retrieve an existing Stripe customer by ID.
     *
     * @throws ApiErrorException
     */
    public function getCustomer(string $customerId): Customer
    {
        return $this->getClient()->customers->retrieve($customerId);
    }

    /**
     * Update an existing Stripe customer.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ApiErrorException
     */
    public function updateCustomer(string $customerId, array $data): Customer
    {
        return $this->getClient()->customers->update($customerId, $data);
    }

    /**
     * Attach a payment method to a customer.
     *
     * @throws ApiErrorException
     */
    public function attachPaymentMethod(string $paymentMethodId, string $customerId): PaymentMethod
    {
        return $this->getClient()->paymentMethods->attach(
            $paymentMethodId,
            ['customer' => $customerId]
        );
    }

    /**
     * Set the default payment method for a customer.
     *
     * @throws ApiErrorException
     */
    public function setDefaultPaymentMethod(string $customerId, string $paymentMethodId): Customer
    {
        return $this->getClient()->customers->update(
            $customerId,
            ['invoice_settings' => ['default_payment_method' => $paymentMethodId]]
        );
    }

    /**
     * Retrieve a payment method by ID.
     *
     * @throws ApiErrorException
     */
    public function getPaymentMethod(string $paymentMethodId): PaymentMethod
    {
        return $this->getClient()->paymentMethods->retrieve($paymentMethodId);
    }

    /**
     * Detach a payment method from its customer.
     *
     * @throws ApiErrorException
     */
    public function detachPaymentMethod(string $paymentMethodId): PaymentMethod
    {
        return $this->getClient()->paymentMethods->detach($paymentMethodId);
    }

    /**
     * Retrieve a Stripe price by ID.
     *
     * @throws ApiErrorException
     */
    public function getPrice(string $priceId): Price
    {
        return $this->getClient()->prices->retrieve($priceId);
    }

    /**
     * Retrieve a Stripe product by ID.
     *
     * @throws ApiErrorException
     */
    public function getProduct(string $productId): Product
    {
        return $this->getClient()->products->retrieve($productId);
    }

    /**
     * Create a setup intent for adding payment methods.
     *
     * @return array{client_secret: string, id: string}
     *
     * @throws ApiErrorException
     */
    public function createSetupIntent(string $customerId): array
    {
        $setupIntent = $this->getClient()->setupIntents->create([
            'customer' => $customerId,
            'payment_method_types' => ['card'],
        ]);

        return [
            'client_secret' => $setupIntent->client_secret ?? '',
            'id' => $setupIntent->id,
        ];
    }
}
