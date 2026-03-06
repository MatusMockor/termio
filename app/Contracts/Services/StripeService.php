<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Tenant;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\Invoice;
use Stripe\PaymentMethod;
use Stripe\Price;
use Stripe\Product;
use Stripe\Subscription;

interface StripeService
{
    /**
     * Check if Stripe is configured.
     */
    public function isConfigured(): bool;

    /**
     * Create a new Stripe customer for the given tenant.
     */
    public function createCustomer(Tenant $tenant): Customer;

    /**
     * Retrieve an existing Stripe customer by ID.
     */
    public function getCustomer(string $customerId): Customer;

    /**
     * Update an existing Stripe customer.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateCustomer(string $customerId, array $data): Customer;

    /**
     * Attach a payment method to a customer.
     */
    public function attachPaymentMethod(string $paymentMethodId, string $customerId): PaymentMethod;

    /**
     * Set the default payment method for a customer.
     */
    public function setDefaultPaymentMethod(string $customerId, string $paymentMethodId): Customer;

    /**
     * Retrieve a payment method by ID.
     */
    public function getPaymentMethod(string $paymentMethodId): PaymentMethod;

    /**
     * Detach a payment method from its customer.
     */
    public function detachPaymentMethod(string $paymentMethodId): PaymentMethod;

    /**
     * Retrieve a Stripe price by ID.
     */
    public function getPrice(string $priceId): Price;

    /**
     * Retrieve a Stripe product by ID.
     */
    public function getProduct(string $productId): Product;

    /**
     * Retrieve a Stripe invoice by ID.
     */
    public function getInvoice(string $invoiceId): Invoice;

    /**
     * Create a setup intent for adding payment methods.
     *
     * @return array{client_secret: string, id: string}
     */
    public function createSetupIntent(string $customerId): array;

    /**
     * Create a billing portal session URL for a customer.
     */
    public function createBillingPortalSession(string $customerId, string $returnUrl): string;

    /**
     * Create a Stripe Checkout session.
     *
     * @param  array<string, mixed>  $params
     */
    public function createCheckoutSession(array $params): Session;

    /**
     * Create a Stripe subscription.
     *
     * @param  array<string, mixed>  $payload
     * @param  array{idempotency_key?: string}  $options
     */
    public function createSubscription(array $payload, array $options = []): Subscription;

    /**
     * Retrieve a Stripe subscription by ID.
     */
    public function getSubscription(string $subscriptionId): Subscription;

    /**
     * Cancel a Stripe subscription immediately.
     */
    public function cancelSubscription(string $subscriptionId): Subscription;
}
