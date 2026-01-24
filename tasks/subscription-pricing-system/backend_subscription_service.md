# Subscription Service Layer

**PRD Source**: `prds/2026-01-subscription-pricing-system.md`
**Category**: Backend
**Complexity**: Large
**Dependencies**: `database_schema.md`, `backend_stripe_integration.md`
**Status**: Not Started

## Technical Overview

**Summary**: Implement the core subscription management service layer including Actions, Services, Repositories, and DTOs. Handles subscription creation, upgrades, downgrades, cancellations, and trial management per PRD requirements (REQ-02, REQ-06, REQ-07, REQ-08, REQ-11).

**Architecture Impact**: Adds new Actions, Services, and Repositories for subscription domain. Central SubscriptionService becomes the source of truth for all subscription-related checks.

**Risk Assessment**:
- **High**: Proration calculations must be accurate to avoid billing disputes
- **Medium**: Trial expiration timing - must handle edge cases
- **Medium**: Downgrade scheduling - must preserve access until period end

## Data Layer

### DTOs

**File**: `app/DTOs/Subscription/CreateSubscriptionDTO.php`

```php
<?php

declare(strict_types=1);

namespace App\DTOs\Subscription;

final readonly class CreateSubscriptionDTO
{
    public function __construct(
        public int $tenantId,
        public int $planId,
        public string $billingCycle, // 'monthly' or 'yearly'
        public ?string $paymentMethodId = null,
        public bool $startTrial = true,
    ) {}
}
```

**File**: `app/DTOs/Subscription/UpgradeSubscriptionDTO.php`

```php
<?php

declare(strict_types=1);

namespace App\DTOs\Subscription;

final readonly class UpgradeSubscriptionDTO
{
    public function __construct(
        public int $subscriptionId,
        public int $newPlanId,
        public ?string $billingCycle = null, // null = keep current
    ) {}
}
```

**File**: `app/DTOs/Subscription/DowngradeSubscriptionDTO.php`

```php
<?php

declare(strict_types=1);

namespace App\DTOs\Subscription;

final readonly class DowngradeSubscriptionDTO
{
    public function __construct(
        public int $subscriptionId,
        public int $newPlanId,
    ) {}
}
```

### Repository Contract

**File**: `app/Contracts/Repositories/SubscriptionRepository.php`

```php
<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Collection;

interface SubscriptionRepository
{
    public function create(array $data): Subscription;

    public function update(Subscription $subscription, array $data): Subscription;

    public function findById(int $id): ?Subscription;

    public function findByStripeId(string $stripeId): ?Subscription;

    public function findActiveByTenant(Tenant $tenant): ?Subscription;

    public function findByTenant(Tenant $tenant): Collection;

    public function getTrialsEndingSoon(int $days): Collection;

    public function getScheduledDowngrades(): Collection;

    public function getExpiredSubscriptions(): Collection;
}
```

**File**: `app/Contracts/Repositories/PlanRepository.php`

```php
<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Plan;
use Illuminate\Support\Collection;

interface PlanRepository
{
    public function create(array $data): Plan;

    public function update(Plan $plan, array $data): Plan;

    public function findById(int $id): ?Plan;

    public function findBySlug(string $slug): ?Plan;

    public function getActive(): Collection;

    public function getPublic(): Collection;

    public function getFreePlan(): ?Plan;
}
```

### Eloquent Repositories

**File**: `app/Repositories/Eloquent/EloquentSubscriptionRepository.php`

```php
<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\SubscriptionRepository;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Collection;

final class EloquentSubscriptionRepository implements SubscriptionRepository
{
    public function create(array $data): Subscription
    {
        return Subscription::create($data);
    }

    public function update(Subscription $subscription, array $data): Subscription
    {
        $subscription->update($data);

        return $subscription->fresh() ?? $subscription;
    }

    public function findById(int $id): ?Subscription
    {
        return Subscription::find($id);
    }

    public function findByStripeId(string $stripeId): ?Subscription
    {
        return Subscription::where('stripe_id', $stripeId)->first();
    }

    public function findActiveByTenant(Tenant $tenant): ?Subscription
    {
        return Subscription::where('tenant_id', $tenant->id)
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->first();
    }

    public function findByTenant(Tenant $tenant): Collection
    {
        return Subscription::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->get();
    }

    public function getTrialsEndingSoon(int $days): Collection
    {
        return Subscription::where('stripe_status', 'trialing')
            ->whereBetween('trial_ends_at', [now(), now()->addDays($days)])
            ->get();
    }

    public function getScheduledDowngrades(): Collection
    {
        return Subscription::whereNotNull('scheduled_plan_id')
            ->where('scheduled_change_at', '<=', now())
            ->get();
    }

    public function getExpiredSubscriptions(): Collection
    {
        return Subscription::whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->where('stripe_status', '!=', 'canceled')
            ->get();
    }
}
```

**File**: `app/Repositories/Eloquent/EloquentPlanRepository.php`

```php
<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\PlanRepository;
use App\Models\Plan;
use Illuminate\Support\Collection;

final class EloquentPlanRepository implements PlanRepository
{
    public function create(array $data): Plan
    {
        return Plan::create($data);
    }

    public function update(Plan $plan, array $data): Plan
    {
        $plan->update($data);

        return $plan->fresh() ?? $plan;
    }

    public function findById(int $id): ?Plan
    {
        return Plan::find($id);
    }

    public function findBySlug(string $slug): ?Plan
    {
        return Plan::where('slug', $slug)->first();
    }

    public function getActive(): Collection
    {
        return Plan::where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    public function getPublic(): Collection
    {
        return Plan::where('is_active', true)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->get();
    }

    public function getFreePlan(): ?Plan
    {
        return Plan::where('slug', 'free')->first();
    }
}
```

## Component Architecture

### Subscription Service

**File**: `app/Services/Subscription/SubscriptionService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\Contracts\Services\SubscriptionServiceContract;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;

final class SubscriptionService implements SubscriptionServiceContract
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly PlanRepository $plans,
    ) {}

    /**
     * Get the current active plan for a tenant.
     */
    public function getCurrentPlan(Tenant $tenant): Plan
    {
        $subscription = $this->subscriptions->findActiveByTenant($tenant);

        if (!$subscription) {
            return $this->plans->getFreePlan();
        }

        return $subscription->plan;
    }

    /**
     * Check if tenant has access to a specific feature.
     */
    public function hasFeature(Tenant $tenant, string $feature): bool
    {
        $plan = $this->getCurrentPlan($tenant);
        $features = $plan->features;

        if (!isset($features[$feature])) {
            return false;
        }

        $value = $features[$feature];

        // Boolean feature
        if (is_bool($value)) {
            return $value;
        }

        // String feature (e.g., 'basic', 'advanced') - any non-false value means enabled
        return $value !== false && $value !== 'none';
    }

    /**
     * Get feature value (for tiered features like 'basic' vs 'advanced').
     */
    public function getFeatureValue(Tenant $tenant, string $feature): mixed
    {
        $plan = $this->getCurrentPlan($tenant);

        return $plan->features[$feature] ?? null;
    }

    /**
     * Get usage limit for a specific resource.
     */
    public function getLimit(Tenant $tenant, string $resource): int
    {
        $plan = $this->getCurrentPlan($tenant);

        return $plan->limits[$resource] ?? 0;
    }

    /**
     * Check if limit is unlimited (-1).
     */
    public function isUnlimited(Tenant $tenant, string $resource): bool
    {
        return $this->getLimit($tenant, $resource) === -1;
    }

    /**
     * Check if tenant is on trial.
     */
    public function isOnTrial(Tenant $tenant): bool
    {
        $subscription = $this->subscriptions->findActiveByTenant($tenant);

        if (!$subscription) {
            return false;
        }

        return $subscription->stripe_status === 'trialing'
            && $subscription->trial_ends_at
            && $subscription->trial_ends_at->isFuture();
    }

    /**
     * Get trial days remaining.
     */
    public function getTrialDaysRemaining(Tenant $tenant): int
    {
        $subscription = $this->subscriptions->findActiveByTenant($tenant);

        if (!$subscription || !$subscription->trial_ends_at) {
            return 0;
        }

        if ($subscription->trial_ends_at->isPast()) {
            return 0;
        }

        return (int) now()->diffInDays($subscription->trial_ends_at, false);
    }

    /**
     * Check if tenant can upgrade to a specific plan.
     */
    public function canUpgradeTo(Tenant $tenant, Plan $newPlan): bool
    {
        $currentPlan = $this->getCurrentPlan($tenant);

        return $newPlan->sort_order > $currentPlan->sort_order;
    }

    /**
     * Check if tenant can downgrade to a specific plan.
     */
    public function canDowngradeTo(Tenant $tenant, Plan $newPlan): bool
    {
        $currentPlan = $this->getCurrentPlan($tenant);

        return $newPlan->sort_order < $currentPlan->sort_order;
    }

    /**
     * Get plans available for upgrade.
     */
    public function getUpgradeOptions(Tenant $tenant): array
    {
        $currentPlan = $this->getCurrentPlan($tenant);

        return $this->plans->getPublic()
            ->filter(static fn (Plan $plan): bool => $plan->sort_order > $currentPlan->sort_order)
            ->values()
            ->all();
    }

    /**
     * Get plans available for downgrade.
     */
    public function getDowngradeOptions(Tenant $tenant): array
    {
        $currentPlan = $this->getCurrentPlan($tenant);

        return $this->plans->getPublic()
            ->filter(static fn (Plan $plan): bool => $plan->sort_order < $currentPlan->sort_order)
            ->values()
            ->all();
    }

    /**
     * Check if a scheduled downgrade or cancellation is pending.
     */
    public function hasPendingChange(Tenant $tenant): bool
    {
        $subscription = $this->subscriptions->findActiveByTenant($tenant);

        if (!$subscription) {
            return false;
        }

        return $subscription->scheduled_plan_id !== null
            || $subscription->ends_at !== null;
    }

    /**
     * Get pending change details.
     *
     * @return array{type: string, plan: ?Plan, date: ?\Carbon\Carbon}|null
     */
    public function getPendingChange(Tenant $tenant): ?array
    {
        $subscription = $this->subscriptions->findActiveByTenant($tenant);

        if (!$subscription) {
            return null;
        }

        if ($subscription->ends_at) {
            return [
                'type' => 'cancellation',
                'plan' => $this->plans->getFreePlan(),
                'date' => $subscription->ends_at,
            ];
        }

        if ($subscription->scheduled_plan_id) {
            return [
                'type' => 'downgrade',
                'plan' => $this->plans->findById($subscription->scheduled_plan_id),
                'date' => $subscription->scheduled_change_at,
            ];
        }

        return null;
    }
}
```

### Service Contract

**File**: `app/Contracts/Services/SubscriptionServiceContract.php`

```php
<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Plan;
use App\Models\Tenant;

interface SubscriptionServiceContract
{
    public function getCurrentPlan(Tenant $tenant): Plan;

    public function hasFeature(Tenant $tenant, string $feature): bool;

    public function getFeatureValue(Tenant $tenant, string $feature): mixed;

    public function getLimit(Tenant $tenant, string $resource): int;

    public function isUnlimited(Tenant $tenant, string $resource): bool;

    public function isOnTrial(Tenant $tenant): bool;

    public function getTrialDaysRemaining(Tenant $tenant): int;

    public function canUpgradeTo(Tenant $tenant, Plan $newPlan): bool;

    public function canDowngradeTo(Tenant $tenant, Plan $newPlan): bool;

    public function getUpgradeOptions(Tenant $tenant): array;

    public function getDowngradeOptions(Tenant $tenant): array;

    public function hasPendingChange(Tenant $tenant): bool;

    public function getPendingChange(Tenant $tenant): ?array;
}
```

### Actions

**File**: `app/Actions/Subscription/SubscriptionCreateAction.php`

```php
<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\Contracts\Services\StripeServiceContract;
use App\DTOs\Subscription\CreateSubscriptionDTO;
use App\Exceptions\SubscriptionException;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

final class SubscriptionCreateAction
{
    private const TRIAL_DAYS = 14;

    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly PlanRepository $plans,
        private readonly StripeServiceContract $stripe,
    ) {}

    public function handle(CreateSubscriptionDTO $dto, Tenant $tenant): Subscription
    {
        $plan = $this->plans->findById($dto->planId);

        if (!$plan) {
            throw SubscriptionException::planNotFound($dto->planId);
        }

        // FREE plan - no Stripe subscription needed
        if ($plan->slug === 'free') {
            return $this->createFreeSubscription($tenant, $plan);
        }

        return DB::transaction(function () use ($dto, $tenant, $plan): Subscription {
            // Ensure tenant has Stripe customer ID
            if (!$tenant->stripe_id) {
                $customer = $this->stripe->createCustomer($tenant);
                $tenant->update(['stripe_id' => $customer->id]);
            }

            // Get the appropriate Stripe price ID
            $priceId = $dto->billingCycle === 'yearly'
                ? $plan->stripe_yearly_price_id
                : $plan->stripe_monthly_price_id;

            // Create Stripe subscription
            $stripeSubscription = $tenant->newSubscription('default', $priceId);

            if ($dto->startTrial) {
                $stripeSubscription->trialDays(self::TRIAL_DAYS);
            }

            if ($dto->paymentMethodId) {
                $stripeSubscription->defaultPaymentMethod($dto->paymentMethodId);
            }

            $stripeSubscription = $stripeSubscription->create();

            // Create local subscription record
            return $this->subscriptions->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'type' => 'default',
                'stripe_id' => $stripeSubscription->stripe_id,
                'stripe_status' => $stripeSubscription->stripe_status,
                'stripe_price' => $priceId,
                'billing_cycle' => $dto->billingCycle,
                'trial_ends_at' => $dto->startTrial ? now()->addDays(self::TRIAL_DAYS) : null,
            ]);
        });
    }

    private function createFreeSubscription(Tenant $tenant, $plan): Subscription
    {
        return $this->subscriptions->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'type' => 'default',
            'stripe_id' => 'free_' . $tenant->id,
            'stripe_status' => 'active',
            'stripe_price' => null,
            'billing_cycle' => 'monthly',
            'trial_ends_at' => null,
        ]);
    }
}
```

**File**: `app/Actions/Subscription/SubscriptionUpgradeAction.php`

```php
<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\Contracts\Services\SubscriptionServiceContract;
use App\DTOs\Subscription\UpgradeSubscriptionDTO;
use App\Exceptions\SubscriptionException;
use App\Models\Subscription;
use App\Services\Subscription\ProrationService;
use Illuminate\Support\Facades\DB;

final class SubscriptionUpgradeAction
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly PlanRepository $plans,
        private readonly SubscriptionServiceContract $subscriptionService,
        private readonly ProrationService $proration,
    ) {}

    public function handle(UpgradeSubscriptionDTO $dto): Subscription
    {
        $subscription = $this->subscriptions->findById($dto->subscriptionId);

        if (!$subscription) {
            throw SubscriptionException::subscriptionNotFound($dto->subscriptionId);
        }

        $newPlan = $this->plans->findById($dto->newPlanId);

        if (!$newPlan) {
            throw SubscriptionException::planNotFound($dto->newPlanId);
        }

        $tenant = $subscription->tenant;

        if (!$this->subscriptionService->canUpgradeTo($tenant, $newPlan)) {
            throw SubscriptionException::cannotUpgrade($subscription->plan, $newPlan);
        }

        return DB::transaction(function () use ($subscription, $newPlan, $dto): Subscription {
            $billingCycle = $dto->billingCycle ?? $subscription->billing_cycle;

            $priceId = $billingCycle === 'yearly'
                ? $newPlan->stripe_yearly_price_id
                : $newPlan->stripe_monthly_price_id;

            // Cancel any scheduled downgrade
            if ($subscription->scheduled_plan_id) {
                $subscription = $this->subscriptions->update($subscription, [
                    'scheduled_plan_id' => null,
                    'scheduled_change_at' => null,
                ]);
            }

            // Swap to new plan with proration
            $subscription->tenant->subscription('default')->swap($priceId);

            // Update local record
            return $this->subscriptions->update($subscription, [
                'plan_id' => $newPlan->id,
                'stripe_price' => $priceId,
                'billing_cycle' => $billingCycle,
            ]);
        });
    }
}
```

**File**: `app/Actions/Subscription/SubscriptionDowngradeAction.php`

```php
<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\Contracts\Repositories\PlanRepository;
use App\Contracts\Repositories\SubscriptionRepository;
use App\Contracts\Services\SubscriptionServiceContract;
use App\DTOs\Subscription\DowngradeSubscriptionDTO;
use App\Exceptions\SubscriptionException;
use App\Models\Subscription;
use App\Services\Subscription\UsageValidationService;
use Illuminate\Support\Facades\DB;

final class SubscriptionDowngradeAction
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly PlanRepository $plans,
        private readonly SubscriptionServiceContract $subscriptionService,
        private readonly UsageValidationService $usageValidation,
    ) {}

    public function handle(DowngradeSubscriptionDTO $dto): Subscription
    {
        $subscription = $this->subscriptions->findById($dto->subscriptionId);

        if (!$subscription) {
            throw SubscriptionException::subscriptionNotFound($dto->subscriptionId);
        }

        $newPlan = $this->plans->findById($dto->newPlanId);

        if (!$newPlan) {
            throw SubscriptionException::planNotFound($dto->newPlanId);
        }

        $tenant = $subscription->tenant;

        if (!$this->subscriptionService->canDowngradeTo($tenant, $newPlan)) {
            throw SubscriptionException::cannotDowngrade($subscription->plan, $newPlan);
        }

        // Check if current usage exceeds new plan limits
        $violations = $this->usageValidation->checkLimitViolations($tenant, $newPlan);

        if (!empty($violations)) {
            throw SubscriptionException::usageExceedsLimits($violations);
        }

        return DB::transaction(function () use ($subscription, $newPlan): Subscription {
            // Get current period end from Stripe
            $stripeSub = $subscription->tenant->subscription('default');
            $periodEnd = $stripeSub->asStripeSubscription()->current_period_end;

            // Schedule downgrade for end of current period
            return $this->subscriptions->update($subscription, [
                'scheduled_plan_id' => $newPlan->id,
                'scheduled_change_at' => \Carbon\Carbon::createFromTimestamp($periodEnd),
            ]);
        });
    }
}
```

**File**: `app/Actions/Subscription/SubscriptionCancelAction.php`

```php
<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\Contracts\Repositories\SubscriptionRepository;
use App\Exceptions\SubscriptionException;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

final class SubscriptionCancelAction
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
    ) {}

    public function handle(int $subscriptionId, ?string $reason = null): Subscription
    {
        $subscription = $this->subscriptions->findById($subscriptionId);

        if (!$subscription) {
            throw SubscriptionException::subscriptionNotFound($subscriptionId);
        }

        if ($subscription->stripe_status === 'canceled') {
            throw SubscriptionException::alreadyCanceled();
        }

        return DB::transaction(function () use ($subscription, $reason): Subscription {
            $tenant = $subscription->tenant;

            // Cancel at period end in Stripe
            $tenant->subscription('default')->cancel();

            // Get period end date
            $stripeSub = $tenant->subscription('default')->asStripeSubscription();
            $endsAt = \Carbon\Carbon::createFromTimestamp($stripeSub->current_period_end);

            // Update local record
            return $this->subscriptions->update($subscription, [
                'ends_at' => $endsAt,
                'scheduled_plan_id' => null,
                'scheduled_change_at' => null,
            ]);
        });
    }
}
```

**File**: `app/Actions/Subscription/SubscriptionResumeAction.php`

```php
<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\Contracts\Repositories\SubscriptionRepository;
use App\Exceptions\SubscriptionException;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

final class SubscriptionResumeAction
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
    ) {}

    public function handle(int $subscriptionId): Subscription
    {
        $subscription = $this->subscriptions->findById($subscriptionId);

        if (!$subscription) {
            throw SubscriptionException::subscriptionNotFound($subscriptionId);
        }

        if (!$subscription->ends_at) {
            throw SubscriptionException::notCanceled();
        }

        if ($subscription->ends_at->isPast()) {
            throw SubscriptionException::cancellationAlreadyEffective();
        }

        return DB::transaction(function () use ($subscription): Subscription {
            $tenant = $subscription->tenant;

            // Resume in Stripe
            $tenant->subscription('default')->resume();

            // Update local record
            return $this->subscriptions->update($subscription, [
                'ends_at' => null,
            ]);
        });
    }
}
```

### Proration Service

**File**: `app/Services/Subscription/ProrationService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Models\Plan;
use App\Models\Subscription;
use Carbon\Carbon;

final class ProrationService
{
    /**
     * Calculate proration for plan upgrade.
     *
     * @return array{credit: float, charge: float, total: float, days_remaining: int}
     */
    public function calculateUpgradeProration(
        Subscription $subscription,
        Plan $newPlan,
        string $newBillingCycle
    ): array {
        $currentPlan = $subscription->plan;
        $billingCycle = $subscription->billing_cycle;

        // Get current period dates
        $stripeSub = $subscription->tenant->subscription('default')->asStripeSubscription();
        $periodStart = Carbon::createFromTimestamp($stripeSub->current_period_start);
        $periodEnd = Carbon::createFromTimestamp($stripeSub->current_period_end);

        $daysInPeriod = $periodStart->diffInDays($periodEnd);
        $daysRemaining = (int) now()->diffInDays($periodEnd, false);
        $daysRemaining = max(0, $daysRemaining);

        // Calculate daily rates
        $currentPrice = $billingCycle === 'yearly'
            ? $currentPlan->yearly_price / 365
            : $currentPlan->monthly_price / 30;

        $newPrice = $newBillingCycle === 'yearly'
            ? $newPlan->yearly_price / 365
            : $newPlan->monthly_price / 30;

        // Calculate proration
        $credit = round($currentPrice * $daysRemaining, 2);
        $charge = round($newPrice * $daysRemaining, 2);
        $total = round($charge - $credit, 2);

        return [
            'credit' => $credit,
            'charge' => $charge,
            'total' => max(0, $total), // Never negative for upgrades
            'days_remaining' => $daysRemaining,
        ];
    }
}
```

### Usage Validation Service

**File**: `app/Services/Subscription/UsageValidationService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Models\Plan;
use App\Models\Tenant;

final class UsageValidationService
{
    /**
     * Check if tenant's current usage exceeds new plan limits.
     *
     * @return array<string, array{current: int, limit: int}>
     */
    public function checkLimitViolations(Tenant $tenant, Plan $newPlan): array
    {
        $violations = [];
        $limits = $newPlan->limits;

        // Check users limit
        $userCount = $tenant->users()->count();
        $userLimit = $limits['users'] ?? 1;

        if ($userLimit !== -1 && $userCount > $userLimit) {
            $violations['users'] = [
                'current' => $userCount,
                'limit' => $userLimit,
            ];
        }

        // Check locations limit (if locations feature exists)
        // $locationCount = $tenant->locations()->count();
        // $locationLimit = $limits['locations'] ?? 1;
        // if ($locationLimit !== -1 && $locationCount > $locationLimit) { ... }

        // Check services limit
        $serviceCount = $tenant->services()->count();
        $serviceLimit = $limits['services'] ?? 10;

        if ($serviceLimit !== -1 && $serviceCount > $serviceLimit) {
            $violations['services'] = [
                'current' => $serviceCount,
                'limit' => $serviceLimit,
            ];
        }

        return $violations;
    }
}
```

### Exception Class

**File**: `app/Exceptions/SubscriptionException.php`

```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Plan;
use Exception;

final class SubscriptionException extends Exception
{
    public static function planNotFound(int $planId): self
    {
        return new self("Plan with ID {$planId} not found.");
    }

    public static function subscriptionNotFound(int $subscriptionId): self
    {
        return new self("Subscription with ID {$subscriptionId} not found.");
    }

    public static function cannotUpgrade(Plan $from, Plan $to): self
    {
        return new self("Cannot upgrade from {$from->name} to {$to->name}.");
    }

    public static function cannotDowngrade(Plan $from, Plan $to): self
    {
        return new self("Cannot downgrade from {$from->name} to {$to->name}.");
    }

    public static function usageExceedsLimits(array $violations): self
    {
        $messages = [];
        foreach ($violations as $resource => $data) {
            $messages[] = "{$resource}: {$data['current']} (limit: {$data['limit']})";
        }

        return new self('Current usage exceeds new plan limits: ' . implode(', ', $messages));
    }

    public static function alreadyCanceled(): self
    {
        return new self('Subscription is already canceled.');
    }

    public static function notCanceled(): self
    {
        return new self('Subscription is not canceled.');
    }

    public static function cancellationAlreadyEffective(): self
    {
        return new self('Cancellation has already taken effect.');
    }
}
```

## API Specification

### Subscription Endpoints

```yaml
/api/subscriptions:
  post:
    summary: Create new subscription
    security:
      - bearerAuth: []
    requestBody:
      required: true
      content:
        application/json:
          schema:
            type: object
            required:
              - plan_id
              - billing_cycle
            properties:
              plan_id:
                type: integer
              billing_cycle:
                type: string
                enum: [monthly, yearly]
              payment_method_id:
                type: string
              start_trial:
                type: boolean
                default: true
    responses:
      201:
        description: Subscription created
      400:
        description: Validation error
      402:
        description: Payment required

  get:
    summary: Get current subscription
    security:
      - bearerAuth: []
    responses:
      200:
        description: Current subscription details

/api/subscriptions/upgrade:
  post:
    summary: Upgrade subscription
    security:
      - bearerAuth: []
    requestBody:
      required: true
      content:
        application/json:
          schema:
            type: object
            required:
              - plan_id
            properties:
              plan_id:
                type: integer
              billing_cycle:
                type: string
                enum: [monthly, yearly]
    responses:
      200:
        description: Subscription upgraded
      400:
        description: Cannot upgrade

/api/subscriptions/downgrade:
  post:
    summary: Schedule subscription downgrade
    security:
      - bearerAuth: []
    requestBody:
      required: true
      content:
        application/json:
          schema:
            type: object
            required:
              - plan_id
            properties:
              plan_id:
                type: integer
    responses:
      200:
        description: Downgrade scheduled
      400:
        description: Usage exceeds new plan limits

/api/subscriptions/cancel:
  post:
    summary: Cancel subscription
    security:
      - bearerAuth: []
    requestBody:
      content:
        application/json:
          schema:
            type: object
            properties:
              reason:
                type: string
    responses:
      200:
        description: Cancellation scheduled

/api/subscriptions/resume:
  post:
    summary: Resume canceled subscription
    security:
      - bearerAuth: []
    responses:
      200:
        description: Subscription resumed
      400:
        description: Cannot resume
```

## Testing Strategy

### E2E Test
- `TestSubscriptionLifecycle` covering create -> upgrade -> downgrade -> cancel -> resume
- Verify: Subscription states transition correctly, Stripe synced

### Manual Verification
- Create subscription with trial
- Upgrade mid-cycle
- Schedule downgrade
- Cancel and resume

## Implementation Steps

1. **Small** - Create DTOs for subscription operations
2. **Small** - Create SubscriptionRepository contract and implementation
3. **Small** - Create PlanRepository contract and implementation
4. **Medium** - Create SubscriptionService with feature/limit checking
5. **Small** - Create SubscriptionServiceContract interface
6. **Medium** - Create SubscriptionCreateAction with Stripe integration
7. **Medium** - Create SubscriptionUpgradeAction with proration
8. **Medium** - Create SubscriptionDowngradeAction with scheduling
9. **Small** - Create SubscriptionCancelAction
10. **Small** - Create SubscriptionResumeAction
11. **Medium** - Create ProrationService for calculations
12. **Medium** - Create UsageValidationService for limit checking
13. **Small** - Create SubscriptionException class
14. **Medium** - Create SubscriptionController with all endpoints
15. **Small** - Register repository and service bindings
16. **Medium** - Write unit tests for services
17. **Medium** - Write feature tests for API endpoints
18. **Small** - Run Pint and verify code style

## Cross-Task Dependencies

- **Depends on**: `database_schema.md`, `backend_stripe_integration.md`
- **Blocks**: `backend_usage_limit_enforcement.md`, `backend_billing_invoicing.md`, `frontend_plan_selection.md`
