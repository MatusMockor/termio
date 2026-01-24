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
    private readonly StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient((string) config('cashier.secret'));
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

        return $this->stripe->customers->create($params);
    }

    /**
     * Retrieve an existing Stripe customer by ID.
     *
     * @throws ApiErrorException
     */
    public function getCustomer(string $customerId): Customer
    {
        return $this->stripe->customers->retrieve($customerId);
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
        return $this->stripe->customers->update($customerId, $data);
    }

    /**
     * Attach a payment method to a customer.
     *
     * @throws ApiErrorException
     */
    public function attachPaymentMethod(string $paymentMethodId, string $customerId): PaymentMethod
    {
        return $this->stripe->paymentMethods->attach(
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
        return $this->stripe->customers->update(
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
        return $this->stripe->paymentMethods->retrieve($paymentMethodId);
    }

    /**
     * Detach a payment method from its customer.
     *
     * @throws ApiErrorException
     */
    public function detachPaymentMethod(string $paymentMethodId): PaymentMethod
    {
        return $this->stripe->paymentMethods->detach($paymentMethodId);
    }

    /**
     * Retrieve a Stripe price by ID.
     *
     * @throws ApiErrorException
     */
    public function getPrice(string $priceId): Price
    {
        return $this->stripe->prices->retrieve($priceId);
    }

    /**
     * Retrieve a Stripe product by ID.
     *
     * @throws ApiErrorException
     */
    public function getProduct(string $productId): Product
    {
        return $this->stripe->products->retrieve($productId);
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
        $setupIntent = $this->stripe->setupIntents->create([
            'customer' => $customerId,
            'payment_method_types' => ['card'],
        ]);

        return [
            'client_secret' => $setupIntent->client_secret ?? '',
            'id' => $setupIntent->id,
        ];
    }
}
