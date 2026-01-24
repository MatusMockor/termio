# Stripe Integration with Laravel Cashier

**PRD Source**: `prds/2026-01-subscription-pricing-system.md`
**Category**: Backend
**Complexity**: Medium
**Dependencies**: `database_schema.md`
**Status**: Not Started

## Technical Overview

**Summary**: Configure Laravel Cashier v15.x for Stripe integration, set up Stripe products/prices, and implement the Billable trait on the Tenant model. This provides the foundation for all payment processing.

**Architecture Impact**: Adds Stripe as the payment provider. Tenant model becomes billable. Environment configuration required for API keys.

**Risk Assessment**:
- **High**: API key security - must use environment variables
- **Medium**: Stripe account setup dependency - requires business account
- **Low**: Package integration - Laravel Cashier is well-documented

## Component Architecture

### Configuration

**File**: `config/cashier.php`

```php
<?php

declare(strict_types=1);

return [
    'key' => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),
    'webhook' => [
        'secret' => env('STRIPE_WEBHOOK_SECRET'),
        'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
    ],
    'currency' => 'eur',
    'currency_locale' => 'sk_SK',
    'logger' => env('CASHIER_LOGGER', 'stack'),
];
```

**File**: `.env.example` additions

```env
# Stripe Configuration
STRIPE_KEY=pk_test_xxxxx
STRIPE_SECRET=sk_test_xxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxx
```

### Tenant Model Modifications

**File**: `app/Models/Tenant.php` - Add Billable trait

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Billable;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $business_type
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $country
 * @property string|null $vat_id
 * @property Carbon|null $vat_id_verified_at
 * @property string $timezone
 * @property array<string, mixed> $settings
 * @property string $status
 * @property string|null $stripe_id
 * @property string|null $pm_type
 * @property string|null $pm_last_four
 * @property Carbon|null $trial_ends_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 *
 * @property-read User|null $owner
 * @property-read Collection<int, User> $users
 * @property-read Collection<int, Service> $services
 * @property-read Collection<int, Client> $clients
 * @property-read Collection<int, Appointment> $appointments
 * @property-read Collection<int, WorkingHours> $workingHours
 * @property-read Collection<int, Subscription> $subscriptions
 * @property-read Collection<int, PaymentMethod> $paymentMethods
 */
final class Tenant extends Model
{
    use Billable;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'business_type',
        'address',
        'phone',
        'country',
        'vat_id',
        'vat_id_verified_at',
        'timezone',
        'settings',
        'status',
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'trial_ends_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
            'vat_id_verified_at' => 'datetime',
        ];
    }

    // ... existing relationships ...

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return HasMany<PaymentMethod, $this>
     */
    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    /**
     * Get the default payment method for the tenant.
     */
    public function defaultPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethods()->where('is_default', true)->first();
    }

    /**
     * Get the active subscription for the tenant.
     */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->first();
    }

    /**
     * Check if tenant is on trial.
     */
    public function onTrial(): bool
    {
        $subscription = $this->activeSubscription();

        if (!$subscription) {
            return false;
        }

        return $subscription->onTrial();
    }

    /**
     * Get days remaining in trial.
     */
    public function trialDaysRemaining(): int
    {
        $subscription = $this->activeSubscription();

        if (!$subscription || !$subscription->trial_ends_at) {
            return 0;
        }

        return (int) now()->diffInDays($subscription->trial_ends_at, false);
    }
}
```

### Stripe Service

**File**: `app/Services/Stripe/StripeService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Stripe;

use App\Contracts\Services\StripeServiceContract;
use App\Models\Tenant;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentMethod;
use Stripe\Price;
use Stripe\Product;
use Stripe\StripeClient;

final class StripeService implements StripeServiceContract
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('cashier.secret'));
    }

    /**
     * Create a Stripe customer for a tenant.
     *
     * @throws ApiErrorException
     */
    public function createCustomer(Tenant $tenant): Customer
    {
        return $this->stripe->customers->create([
            'name' => $tenant->name,
            'email' => $tenant->owner?->email,
            'phone' => $tenant->phone,
            'address' => [
                'country' => $tenant->country,
            ],
            'metadata' => [
                'tenant_id' => $tenant->id,
            ],
        ]);
    }

    /**
     * Retrieve a Stripe customer.
     *
     * @throws ApiErrorException
     */
    public function getCustomer(string $customerId): Customer
    {
        return $this->stripe->customers->retrieve($customerId);
    }

    /**
     * Update Stripe customer.
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
     * Attach payment method to customer.
     *
     * @throws ApiErrorException
     */
    public function attachPaymentMethod(string $paymentMethodId, string $customerId): PaymentMethod
    {
        return $this->stripe->paymentMethods->attach($paymentMethodId, [
            'customer' => $customerId,
        ]);
    }

    /**
     * Set default payment method for customer.
     *
     * @throws ApiErrorException
     */
    public function setDefaultPaymentMethod(string $customerId, string $paymentMethodId): Customer
    {
        return $this->stripe->customers->update($customerId, [
            'invoice_settings' => [
                'default_payment_method' => $paymentMethodId,
            ],
        ]);
    }

    /**
     * Retrieve payment method details.
     *
     * @throws ApiErrorException
     */
    public function getPaymentMethod(string $paymentMethodId): PaymentMethod
    {
        return $this->stripe->paymentMethods->retrieve($paymentMethodId);
    }

    /**
     * Detach payment method from customer.
     *
     * @throws ApiErrorException
     */
    public function detachPaymentMethod(string $paymentMethodId): PaymentMethod
    {
        return $this->stripe->paymentMethods->detach($paymentMethodId);
    }

    /**
     * Get Stripe price by ID.
     *
     * @throws ApiErrorException
     */
    public function getPrice(string $priceId): Price
    {
        return $this->stripe->prices->retrieve($priceId);
    }

    /**
     * Get Stripe product by ID.
     *
     * @throws ApiErrorException
     */
    public function getProduct(string $productId): Product
    {
        return $this->stripe->products->retrieve($productId);
    }

    /**
     * Create setup intent for adding payment method.
     *
     * @return array{client_secret: string}
     *
     * @throws ApiErrorException
     */
    public function createSetupIntent(string $customerId): array
    {
        $intent = $this->stripe->setupIntents->create([
            'customer' => $customerId,
            'payment_method_types' => ['card'],
        ]);

        return [
            'client_secret' => $intent->client_secret,
        ];
    }
}
```

### Service Contract

**File**: `app/Contracts/Services/StripeServiceContract.php`

```php
<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Tenant;
use Stripe\Customer;
use Stripe\PaymentMethod;
use Stripe\Price;
use Stripe\Product;

interface StripeServiceContract
{
    public function createCustomer(Tenant $tenant): Customer;

    public function getCustomer(string $customerId): Customer;

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCustomer(string $customerId, array $data): Customer;

    public function attachPaymentMethod(string $paymentMethodId, string $customerId): PaymentMethod;

    public function setDefaultPaymentMethod(string $customerId, string $paymentMethodId): Customer;

    public function getPaymentMethod(string $paymentMethodId): PaymentMethod;

    public function detachPaymentMethod(string $paymentMethodId): PaymentMethod;

    public function getPrice(string $priceId): Price;

    public function getProduct(string $productId): Product;

    /**
     * @return array{client_secret: string}
     */
    public function createSetupIntent(string $customerId): array;
}
```

### Service Provider Registration

**File**: `app/Providers/AppServiceProvider.php` - Add binding

```php
use App\Contracts\Services\StripeServiceContract;
use App\Services\Stripe\StripeService;

// In register() method:
$this->app->bind(StripeServiceContract::class, StripeService::class);
```

## API Specification

### Setup Intent Endpoint

```yaml
/api/billing/setup-intent:
  post:
    summary: Create Stripe setup intent for adding payment method
    security:
      - bearerAuth: []
    responses:
      200:
        description: Setup intent created
        content:
          application/json:
            schema:
              type: object
              properties:
                client_secret:
                  type: string
                  description: Stripe client secret for frontend
      401:
        description: Unauthorized
      500:
        description: Stripe API error
```

## Testing Strategy

### E2E Test
- `TestStripeIntegration` covering customer creation, payment method attachment
- Verify: Stripe customer created with correct metadata, payment methods attached

### Manual Verification
- Create Stripe test customer via API
- Attach test payment method
- Verify in Stripe dashboard

## Implementation Steps

1. **Small** - Install Laravel Cashier: `composer require laravel/cashier`
2. **Small** - Publish Cashier config and update for EUR
3. **Small** - Add Stripe environment variables to `.env.example`
4. **Medium** - Update Tenant model with Billable trait and new relationships
5. **Medium** - Create StripeService with customer management methods
6. **Small** - Create StripeServiceContract interface
7. **Small** - Register service binding in AppServiceProvider
8. **Medium** - Create Stripe products and prices in Stripe Dashboard (manual)
9. **Small** - Update PlanSeeder with Stripe price IDs after creating in dashboard
10. **Small** - Write integration tests with Stripe test mode
11. **Small** - Run Pint and verify code style

## Stripe Dashboard Setup (Manual)

Create the following products and prices in Stripe Dashboard:

### Products
1. **Termio EASY** - Subscription product
2. **Termio SMART** - Subscription product
3. **Termio STANDARD** - Subscription product
4. **Termio PREMIUM** - Subscription product

### Prices (per product)
- Monthly price in EUR
- Yearly price in EUR (with discount applied)

After creating, copy price IDs to PlanSeeder.

## Cross-Task Dependencies

- **Depends on**: `database_schema.md` - Tables must exist
- **Blocks**: `backend_subscription_service.md`, `backend_billing_invoicing.md`
