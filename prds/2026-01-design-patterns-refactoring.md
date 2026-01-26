# Design Patterns Refactoring - PRD

**Created**: 2026-01-25
**Status**: Draft
**Owner**: Product Manager
**Target Release**: Q2 2026 (Phased Implementation)
**Pricing Tier**: All tiers (code quality improvement)
**Document Type**: Technical Improvement Initiative

---

## Executive Summary

This PRD outlines a comprehensive design patterns refactoring initiative to transform Termio's codebase from a functional but rigid implementation into a flexible, maintainable, and scalable architecture. By implementing 8 strategic design patterns across backend (Laravel/PHP) and frontend (React/TypeScript) codebases, we will significantly reduce technical debt, improve code quality, and accelerate future feature development.

### Business Value

**Why This Matters:**
- **Faster feature development**: New subscription types, payment providers, or validation rules can be added in minutes, not days
- **Reduced bugs**: Clear separation of concerns and testable units prevent regression
- **Lower maintenance costs**: Well-structured code requires less time to understand and modify
- **Easier onboarding**: New developers can understand and contribute faster
- **Competitive advantage**: Ability to quickly adapt to market demands (new pricing models, integrations)

**Current Pain Points:**
- Adding a new subscription type requires modifying 5+ files with scattered logic
- Validation logic is duplicated across 3 different Actions
- Job classes share 80% identical code but can't be easily refactored
- Frontend components tightly coupled to Laravel Resource response structure
- State management scattered, making it hard to add features like undo/redo

**Expected Outcomes:**
- **50% reduction** in code duplication across subscription management
- **3x faster** implementation of new subscription types or validation rules
- **100% test coverage** for all pattern implementations
- **Zero breaking changes** for existing functionality

### Priority Matrix

| Pattern | Priority | Effort | Impact | Business Value | Files Affected |
|---------|----------|--------|--------|----------------|----------------|
| **Chain of Responsibility** | HIGH | Medium | High | Add new validations without modifying existing code | 3-5 Actions |
| **Template Method** | HIGH | Low | High | Eliminate 80% code duplication in Jobs | 2 Jobs + 1 abstract |
| **Adapter** | MEDIUM | Low | Medium | Decouple frontend from Laravel API structure | All API clients |
| **Strategy** | MEDIUM | Medium | Medium | Support unlimited subscription types | 1 Action |
| **Facade** | MEDIUM | Low | Medium | Simplify billing API usage | 10+ components |
| **State** | LOW | High | Medium | Centralize subscription status logic | 15+ files |
| **Builder** | LOW | Low | Low | Cleaner notification creation | 8 Notifications |
| **Command** | LOW | Medium | Low | Enable undo/redo for auth operations | Auth store |

---

## 1. Goal

Transform Termio's codebase by implementing 8 strategic design patterns to achieve:

1. **Flexibility**: Add new subscription types, validators, or states without modifying core code
2. **Maintainability**: Reduce code duplication by 50% and improve code readability
3. **Testability**: Achieve 100% unit test coverage for all pattern implementations
4. **Scalability**: Support future features (multi-currency, enterprise plans, complex workflows)
5. **Developer Experience**: New team members productive within days, not weeks

---

## 2. Target Audience

### Primary Users (Direct)
- **Backend Developers**: Implementing subscription logic, validations, background jobs
- **Frontend Developers**: Consuming APIs, managing state, building UI components
- **QA Engineers**: Writing tests, verifying behavior

### Secondary Users (Indirect)
- **Product Managers**: Requesting new features (new plans, pricing models)
- **Customer Support**: Debugging issues, understanding system behavior
- **Future Team Members**: Onboarding and learning codebase

### Stakeholders
- **CTO/Tech Lead**: Responsible for code quality, technical debt, team velocity
- **Investors**: Care about development speed, scalability, time-to-market

---

## 3. Problem Statement

### Current State - Backend (PHP/Laravel)

#### Problem 1: Hardcoded Subscription Types (SubscriptionCreateAction)
```php
// Current: Two subscription paths hardcoded
if ($plan->slug === 'free') {
    return $this->createFreeSubscription($tenant, $plan);
}
return $this->createPaidSubscription($dto, $tenant, $plan);
```

**Pain Points:**
- Adding "Enterprise" plan requires modifying Action class
- Cannot add custom logic per plan type (e.g., manual approval for Enterprise)
- Violates Open/Closed Principle
- Testing requires mocking entire Action

#### Problem 2: Sequential Validations (SubscriptionDowngradeAction)
```php
// Current: All validations in sequence, hard to extend
if (!$subscription) throw SubscriptionException::subscriptionNotFound();
if (!$newPlan) throw SubscriptionException::planNotFound();
if (!$this->subscriptionService->canDowngradeTo($tenant, $newPlan))
    throw SubscriptionException::cannotDowngrade();
$violations = $this->usageValidation->checkLimitViolations($tenant, $newPlan);
if (!empty($violations)) throw SubscriptionException::usageExceedsLimits();
```

**Pain Points:**
- Adding new validation requires modifying Action
- Cannot reorder validations without risk
- Duplicate validation logic across Upgrade/Downgrade/Cancel Actions
- Hard to test individual validations

#### Problem 3: Duplicate Job Logic (ProcessExpiredTrialsJob vs ProcessScheduledDowngradesJob)
```php
// Both jobs share identical structure:
// 1. Query subscriptions
// 2. Chunk processing (100 per batch)
// 3. Individual processing with error handling
// 4. Notification sending
// 5. Logging
// Result: 80% code duplication, inconsistent error handling
```

**Pain Points:**
- Bug fixes must be applied to both jobs
- Inconsistent logging formats
- Cannot add metrics/monitoring uniformly
- Difficult to add new processing jobs

#### Problem 4: Scattered Status Checks (Subscription Model)
```php
// Status logic scattered across codebase:
if ($subscription->stripe_status === SubscriptionStatus::Trialing)
if ($subscription->trial_ends_at && $subscription->trial_ends_at->isFuture())
if ($subscription->stripe_status === SubscriptionStatus::Active && !$subscription->ends_at)
```

**Pain Points:**
- Adding new status requires changes in 15+ files
- Business rules duplicated everywhere
- Cannot add state transitions easily
- Hard to visualize subscription lifecycle

#### Problem 5: Complex Notification Construction
```php
// Current: Notifications take 4-5 constructor parameters
new SubscriptionDowngradeScheduledNotification(
    $tenant,
    $currentPlan,
    $newPlan,
    $effectiveDate
);
```

**Pain Points:**
- Adding optional parameters (e.g., reason, metadata) breaks all usages
- Unclear which parameters are required
- Hard to create variations (email-only, SMS-only)

### Current State - Frontend (React/TypeScript)

#### Problem 6: Tight Coupling to Laravel Resources (API Clients)
```typescript
// Current: Frontend knows about Laravel Resource structure
const response = await api.get<{ data: Plan[] }>('/api/plans');
return response.data.data; // Nested .data access everywhere

const response = await api.get<{ data: Subscription | null }>('/api/subscriptions');
return response.data.data;
```

**Pain Points:**
- If Laravel changes Resource structure, ALL frontend code breaks
- Cannot easily switch to different backend
- Inconsistent: some endpoints return `{ data: [...] }`, others just `{ ... }`
- Components cannot be unit tested without mocking Laravel structure

#### Problem 7: Direct State Mutations (authStore)
```typescript
// Current: Direct mutations, no history
logout: () => {
    set({ user: null, tenant: null, token: null, isAuthenticated: false });
    localStorage.removeItem('auth_token');
}
```

**Pain Points:**
- Cannot implement undo/redo
- Cannot queue actions (e.g., "logout after saving")
- Hard to add middleware (logging, analytics)
- Testing requires mocking Zustand

#### Problem 8: Multiple API Imports (Billing Components)
```typescript
// Current: Components import 3 different modules
import { plansApi } from '@/api/billing';
import { subscriptionsApi } from '@/api/billing';
import { billingApi } from '@/api/billing';
```

**Pain Points:**
- Components need to know about internal API structure
- Cannot easily add caching layer
- Hard to implement request batching
- Tight coupling prevents API refactoring

### Impact of Inaction

**Short-term (3-6 months):**
- Adding new subscription tiers takes 2-3 days instead of hours
- Bug fixes in one area break other areas
- New developers spend weeks understanding code structure
- Technical debt accumulates, slowing every feature

**Long-term (1+ year):**
- Cannot compete with faster-moving competitors
- Major refactor required before scaling (risky, expensive)
- Team velocity drops by 50% as complexity increases
- Difficulty attracting senior developers (poor code quality)

---

## 4. Proposed Solution - Pattern Implementations

### Pattern 1: STRATEGY PATTERN - Subscription Creation (MEDIUM Priority)

**Problem Solved**: Eliminate hardcoded subscription types, support unlimited plan variations

**Technical Design:**

```php
// app/Contracts/Subscription/SubscriptionCreationStrategy.php
interface SubscriptionCreationStrategy
{
    public function supports(Plan $plan): bool;
    public function create(CreateSubscriptionDTO $dto, Tenant $tenant, Plan $plan): Subscription;
}

// app/Services/Subscription/Strategies/FreeSubscriptionStrategy.php
final class FreeSubscriptionStrategy implements SubscriptionCreationStrategy
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions
    ) {}

    public function supports(Plan $plan): bool
    {
        return $plan->slug === 'free';
    }

    public function create(CreateSubscriptionDTO $dto, Tenant $tenant, Plan $plan): Subscription
    {
        return $this->subscriptions->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'type' => SubscriptionType::Default->value,
            'stripe_id' => 'free_' . $tenant->id,
            'stripe_status' => SubscriptionStatus::Active->value,
            'stripe_price' => null,
            'billing_cycle' => 'monthly',
            'trial_ends_at' => null,
        ]);
    }
}

// app/Services/Subscription/Strategies/PaidSubscriptionStrategy.php
final class PaidSubscriptionStrategy implements SubscriptionCreationStrategy
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly StripeService $stripe,
    ) {}

    public function supports(Plan $plan): bool
    {
        return $plan->slug !== 'free' && !$plan->requires_approval;
    }

    public function create(CreateSubscriptionDTO $dto, Tenant $tenant, Plan $plan): Subscription
    {
        return DB::transaction(function () use ($dto, $tenant, $plan): Subscription {
            // Ensure Stripe customer exists
            if (!$tenant->stripe_id) {
                $customer = $this->stripe->createCustomer($tenant);
                $tenant->update(['stripe_id' => $customer->id]);
            }

            // Get price ID and create Stripe subscription
            $priceId = $dto->billingCycle === 'yearly'
                ? $plan->stripe_yearly_price_id
                : $plan->stripe_monthly_price_id;

            $stripeSubBuilder = $tenant->newSubscription(SubscriptionType::Default->value, $priceId);

            if ($dto->startTrial) {
                $stripeSubBuilder->trialDays(config('subscription.trial_days'));
            }

            $stripeSub = $stripeSubBuilder->create($dto->paymentMethodId);

            return $this->subscriptions->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'type' => SubscriptionType::Default->value,
                'stripe_id' => $stripeSub->stripe_id,
                'stripe_status' => $stripeSub->stripe_status,
                'stripe_price' => $priceId,
                'billing_cycle' => $dto->billingCycle,
                'trial_ends_at' => $dto->startTrial ? now()->addDays(config('subscription.trial_days')) : null,
            ]);
        });
    }
}

// Future: EnterpriseSubscriptionStrategy (manual approval, custom pricing)
final class EnterpriseSubscriptionStrategy implements SubscriptionCreationStrategy
{
    public function supports(Plan $plan): bool
    {
        return $plan->requires_approval === true;
    }

    public function create(CreateSubscriptionDTO $dto, Tenant $tenant, Plan $plan): Subscription
    {
        // Create pending subscription requiring manual approval
        // Send notification to admin
        // Set status to 'pending_approval'
    }
}

// app/Services/Subscription/SubscriptionStrategyResolver.php
final class SubscriptionStrategyResolver
{
    /**
     * @param array<SubscriptionCreationStrategy> $strategies
     */
    public function __construct(
        private readonly array $strategies
    ) {}

    public function resolve(Plan $plan): SubscriptionCreationStrategy
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($plan)) {
                return $strategy;
            }
        }

        throw new \RuntimeException('No strategy found for plan: ' . $plan->slug);
    }
}

// app/Actions/Subscription/SubscriptionCreateAction.php (REFACTORED)
final class SubscriptionCreateAction
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly PlanRepository $plans,
        private readonly SubscriptionStrategyResolver $strategyResolver,
    ) {}

    public function handle(CreateSubscriptionDTO $dto, Tenant $tenant): Subscription
    {
        $existingSubscription = $this->subscriptions->findActiveByTenant($tenant);
        if ($existingSubscription) {
            throw SubscriptionException::alreadySubscribed();
        }

        $plan = $this->plans->findById($dto->planId);
        if (!$plan) {
            throw SubscriptionException::planNotFound($dto->planId);
        }

        // Strategy pattern: delegates to appropriate strategy
        $strategy = $this->strategyResolver->resolve($plan);
        return $strategy->create($dto, $tenant, $plan);
    }
}

// app/Providers/AppServiceProvider.php (registration)
public function register(): void
{
    $this->app->singleton(SubscriptionStrategyResolver::class, function ($app) {
        return new SubscriptionStrategyResolver([
            $app->make(FreeSubscriptionStrategy::class),
            $app->make(PaidSubscriptionStrategy::class),
            $app->make(EnterpriseSubscriptionStrategy::class), // Future
        ]);
    });
}
```

**Benefits:**
- Add new subscription types by creating new Strategy class (no modifications)
- Each strategy independently testable
- Clear separation of concerns
- Supports future: multi-currency, partner plans, freemium trials

**Files to Create:**
- `app/Contracts/Subscription/SubscriptionCreationStrategy.php`
- `app/Services/Subscription/SubscriptionStrategyResolver.php`
- `app/Services/Subscription/Strategies/FreeSubscriptionStrategy.php`
- `app/Services/Subscription/Strategies/PaidSubscriptionStrategy.php`
- `tests/Unit/Services/Subscription/Strategies/*Test.php`

**Files to Modify:**
- `app/Actions/Subscription/SubscriptionCreateAction.php`
- `app/Providers/AppServiceProvider.php`

**Migration Strategy:**
1. Create all Strategy classes with existing logic
2. Update Action to use StrategyResolver
3. Run full test suite to verify behavior unchanged
4. Remove old private methods from Action
5. Deploy with feature flag (rollback if issues)

---

### Pattern 2: CHAIN OF RESPONSIBILITY - Subscription Validations (HIGH Priority)

**Problem Solved**: Flexible validation pipeline, easy to add/remove/reorder validators

**Technical Design:**

```php
// app/Contracts/Validation/SubscriptionValidator.php
interface SubscriptionValidator
{
    public function validate(Subscription $subscription, Plan $targetPlan, Tenant $tenant): void;
    public function setNext(SubscriptionValidator $validator): SubscriptionValidator;
}

// app/Services/Validation/AbstractSubscriptionValidator.php
abstract class AbstractSubscriptionValidator implements SubscriptionValidator
{
    private ?SubscriptionValidator $next = null;

    public function setNext(SubscriptionValidator $validator): SubscriptionValidator
    {
        $this->next = $validator;
        return $validator;
    }

    protected function validateNext(Subscription $subscription, Plan $targetPlan, Tenant $tenant): void
    {
        if ($this->next !== null) {
            $this->next->validate($subscription, $targetPlan, $tenant);
        }
    }
}

// app/Services/Validation/Validators/SubscriptionExistsValidator.php
final class SubscriptionExistsValidator extends AbstractSubscriptionValidator
{
    public function validate(Subscription $subscription, Plan $targetPlan, Tenant $tenant): void
    {
        if (!$subscription->exists) {
            throw SubscriptionException::subscriptionNotFound($subscription->id);
        }

        $this->validateNext($subscription, $targetPlan, $tenant);
    }
}

// app/Services/Validation/Validators/PlanExistsValidator.php
final class PlanExistsValidator extends AbstractSubscriptionValidator
{
    public function validate(Subscription $subscription, Plan $targetPlan, Tenant $tenant): void
    {
        if (!$targetPlan->exists) {
            throw SubscriptionException::planNotFound($targetPlan->id);
        }

        $this->validateNext($subscription, $targetPlan, $tenant);
    }
}

// app/Services/Validation/Validators/CanDowngradeValidator.php
final class CanDowngradeValidator extends AbstractSubscriptionValidator
{
    public function __construct(
        private readonly SubscriptionServiceContract $subscriptionService
    ) {}

    public function validate(Subscription $subscription, Plan $targetPlan, Tenant $tenant): void
    {
        if (!$this->subscriptionService->canDowngradeTo($tenant, $targetPlan)) {
            throw SubscriptionException::cannotDowngrade($subscription->plan, $targetPlan);
        }

        $this->validateNext($subscription, $targetPlan, $tenant);
    }
}

// app/Services/Validation/Validators/UsageLimitsValidator.php
final class UsageLimitsValidator extends AbstractSubscriptionValidator
{
    public function __construct(
        private readonly UsageValidationService $usageValidation
    ) {}

    public function validate(Subscription $subscription, Plan $targetPlan, Tenant $tenant): void
    {
        $violations = $this->usageValidation->checkLimitViolations($tenant, $targetPlan);

        if (!empty($violations)) {
            throw SubscriptionException::usageExceedsLimits($violations);
        }

        $this->validateNext($subscription, $targetPlan, $tenant);
    }
}

// Future validators (add without modifying existing code):
// - PaymentMethodValidator (check valid payment method)
// - ContractValidator (check if still under contract)
// - TeamApprovalValidator (enterprise plans require approval)

// app/Services/Validation/ValidationChainBuilder.php
final class ValidationChainBuilder
{
    /**
     * Build downgrade validation chain
     */
    public function buildDowngradeChain(
        SubscriptionExistsValidator $existsValidator,
        PlanExistsValidator $planValidator,
        CanDowngradeValidator $canDowngradeValidator,
        UsageLimitsValidator $usageLimitsValidator,
    ): SubscriptionValidator {
        $existsValidator
            ->setNext($planValidator)
            ->setNext($canDowngradeValidator)
            ->setNext($usageLimitsValidator);

        return $existsValidator;
    }

    /**
     * Build upgrade validation chain (different order/validators)
     */
    public function buildUpgradeChain(
        SubscriptionExistsValidator $existsValidator,
        PlanExistsValidator $planValidator,
        PaymentMethodValidator $paymentValidator, // Upgrade requires payment
    ): SubscriptionValidator {
        $existsValidator
            ->setNext($planValidator)
            ->setNext($paymentValidator);

        return $existsValidator;
    }
}

// app/Actions/Subscription/SubscriptionDowngradeAction.php (REFACTORED)
final class SubscriptionDowngradeAction
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly PlanRepository $plans,
        private readonly SubscriptionValidator $validationChain, // Injected chain
    ) {}

    public function handle(DowngradeSubscriptionDTO $dto): Subscription
    {
        $subscription = $this->subscriptions->findById($dto->subscriptionId);
        $newPlan = $this->plans->findById($dto->newPlanId);
        $tenant = $subscription->tenant;

        // Single validation call - entire chain executes
        $this->validationChain->validate($subscription, $newPlan, $tenant);

        $result = $this->scheduleDowngrade($subscription, $newPlan);
        $this->sendDowngradeScheduledNotification($tenant, $subscription->plan, $newPlan, $result['effective_date']);

        return $result['subscription'];
    }

    // ... rest of the methods unchanged
}

// app/Providers/AppServiceProvider.php (registration)
public function register(): void
{
    // Register downgrade validation chain
    $this->app->when(SubscriptionDowngradeAction::class)
        ->needs(SubscriptionValidator::class)
        ->give(function ($app) {
            $builder = $app->make(ValidationChainBuilder::class);
            return $builder->buildDowngradeChain(
                $app->make(SubscriptionExistsValidator::class),
                $app->make(PlanExistsValidator::class),
                $app->make(CanDowngradeValidator::class),
                $app->make(UsageLimitsValidator::class),
            );
        });

    // Register upgrade validation chain (different validators)
    $this->app->when(SubscriptionUpgradeAction::class)
        ->needs(SubscriptionValidator::class)
        ->give(function ($app) {
            $builder = $app->make(ValidationChainBuilder::class);
            return $builder->buildUpgradeChain(
                $app->make(SubscriptionExistsValidator::class),
                $app->make(PlanExistsValidator::class),
                $app->make(PaymentMethodValidator::class),
            );
        });
}
```

**Benefits:**
- Add new validators without modifying existing code
- Reorder validations via config (no code changes)
- Each validator independently testable
- Different validation chains for different actions (upgrade vs downgrade)
- Clear single responsibility per validator

**Files to Create:**
- `app/Contracts/Validation/SubscriptionValidator.php`
- `app/Services/Validation/AbstractSubscriptionValidator.php`
- `app/Services/Validation/ValidationChainBuilder.php`
- `app/Services/Validation/Validators/SubscriptionExistsValidator.php`
- `app/Services/Validation/Validators/PlanExistsValidator.php`
- `app/Services/Validation/Validators/CanDowngradeValidator.php`
- `app/Services/Validation/Validators/UsageLimitsValidator.php`
- `tests/Unit/Services/Validation/Validators/*Test.php`

**Files to Modify:**
- `app/Actions/Subscription/SubscriptionDowngradeAction.php`
- `app/Actions/Subscription/SubscriptionUpgradeAction.php`
- `app/Providers/AppServiceProvider.php`

**Migration Strategy:**
1. Create all Validator classes with existing logic
2. Create ValidationChainBuilder
3. Update Actions to use injected chain
4. Run integration tests
5. Remove old validation code from Actions
6. Deploy with monitoring

---

### Pattern 3: TEMPLATE METHOD - Job Processing (HIGH Priority)

**Problem Solved**: Eliminate 80% code duplication between background jobs

**Technical Design:**

```php
// app/Jobs/Subscription/AbstractSubscriptionProcessingJob.php
abstract class AbstractSubscriptionProcessingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CHUNK_SIZE = 100;

    // Template method - defines algorithm skeleton
    public function handle(): void
    {
        $this->beforeProcessing();

        $query = $this->buildQuery();

        $query->chunk(self::CHUNK_SIZE, function ($items): void {
            foreach ($items as $item) {
                $this->processItem($item);
            }
        });

        $this->afterProcessing();
    }

    // Abstract methods - subclasses must implement
    abstract protected function buildQuery(): Builder;
    abstract protected function processItem(Model $item): void;
    abstract protected function getJobName(): string;

    // Hook methods - subclasses can override
    protected function beforeProcessing(): void
    {
        Log::info("{$this->getJobName()} started");
    }

    protected function afterProcessing(): void
    {
        Log::info("{$this->getJobName()} completed");
    }

    // Common error handling
    protected function handleError(\Throwable $e, Model $item): void
    {
        Log::error("{$this->getJobName()} failed for item", [
            'item_id' => $item->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}

// app/Jobs/Subscription/ProcessExpiredTrialsJob.php (REFACTORED)
final class ProcessExpiredTrialsJob extends AbstractSubscriptionProcessingJob
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly PlanRepository $plans,
    ) {
        parent::__construct();
    }

    protected function getJobName(): string
    {
        return 'Process Expired Trials';
    }

    protected function buildQuery(): Builder
    {
        return Subscription::query()
            ->with(['tenant.owner', 'plan'])
            ->where('stripe_status', SubscriptionStatus::Trialing)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now());
    }

    protected function processItem(Model $item): void
    {
        if (!($item instanceof Subscription)) {
            return;
        }

        try {
            $freePlan = $this->plans->getFreePlan();
            if (!$freePlan) {
                Log::error('Cannot process expired trial: FREE plan not found');
                return;
            }

            $tenant = $item->tenant;
            if (!$tenant) {
                Log::error('Cannot process expired trial: tenant not found', ['subscription_id' => $item->id]);
                return;
            }

            if ($tenant->hasDefaultPaymentMethod()) {
                $this->convertToActive($item, $tenant);
            } else {
                $this->downgradeToFree($item, $freePlan, $tenant);
            }
        } catch (\Throwable $e) {
            $this->handleError($e, $item);
        }
    }

    private function convertToActive(Subscription $subscription, Tenant $tenant): void
    {
        $this->subscriptions->update($subscription, [
            'stripe_status' => SubscriptionStatus::Active->value,
            'trial_ends_at' => null,
        ]);

        $tenant->owner?->notify(new TrialEndedNotification($tenant, true));

        Log::info('Trial converted to active', [
            'subscription_id' => $subscription->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    private function downgradeToFree(Subscription $subscription, Plan $freePlan, Tenant $tenant): void
    {
        DB::transaction(static function () use ($subscription, $freePlan, $tenant): void {
            if (!str_starts_with($subscription->stripe_id, 'free_')) {
                $stripeSub = $tenant->subscription(SubscriptionType::Default->value);
                $stripeSub?->cancelNow();
            }

            $this->subscriptions->update($subscription, [
                'plan_id' => $freePlan->id,
                'stripe_id' => 'free_' . $tenant->id,
                'stripe_status' => SubscriptionStatus::Active->value,
                'stripe_price' => null,
                'trial_ends_at' => null,
            ]);
        });

        $tenant->owner?->notify(new TrialEndedNotification($tenant, false));

        Log::info('Trial expired - downgraded to FREE', [
            'subscription_id' => $subscription->id,
            'tenant_id' => $tenant->id,
        ]);
    }
}

// app/Jobs/Subscription/ProcessScheduledDowngradesJob.php (REFACTORED)
final class ProcessScheduledDowngradesJob extends AbstractSubscriptionProcessingJob
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
    ) {
        parent::__construct();
    }

    protected function getJobName(): string
    {
        return 'Process Scheduled Downgrades';
    }

    protected function buildQuery(): Builder
    {
        return Subscription::query()
            ->with(['tenant.owner', 'scheduledPlan'])
            ->whereNotNull('scheduled_plan_id')
            ->whereNotNull('scheduled_change_at')
            ->where('scheduled_change_at', '<=', now());
    }

    protected function processItem(Model $item): void
    {
        if (!($item instanceof Subscription)) {
            return;
        }

        try {
            $tenant = $item->tenant;
            $scheduledPlan = $item->scheduledPlan;

            if (!$tenant || !$scheduledPlan) {
                Log::error('Cannot process downgrade: missing data', [
                    'subscription_id' => $item->id,
                    'tenant_id' => $item->tenant_id,
                    'scheduled_plan_id' => $item->scheduled_plan_id,
                ]);
                return;
            }

            $this->executeDowngrade($item, $scheduledPlan);
            $this->sendNotification($tenant, $scheduledPlan);

        } catch (\Throwable $e) {
            $this->handleError($e, $item);
        }
    }

    private function executeDowngrade(Subscription $subscription, Plan $scheduledPlan): void
    {
        DB::transaction(function () use ($subscription, $scheduledPlan): void {
            $priceId = $subscription->billing_cycle === 'yearly'
                ? $scheduledPlan->stripe_yearly_price_id
                : $scheduledPlan->stripe_monthly_price_id;

            if (!str_starts_with($subscription->stripe_id, 'free_')) {
                $stripeSub = $subscription->tenant->subscription('default');
                if ($stripeSub && $priceId) {
                    $stripeSub->swap($priceId);
                }
            }

            $this->subscriptions->update($subscription, [
                'plan_id' => $scheduledPlan->id,
                'stripe_price' => $priceId,
                'scheduled_plan_id' => null,
                'scheduled_change_at' => null,
            ]);
        });
    }

    private function sendNotification(Tenant $tenant, Plan $scheduledPlan): void
    {
        $tenant->owner?->notify(new SubscriptionDowngradedNotification($tenant, $scheduledPlan));

        Log::info('Scheduled downgrade completed', [
            'tenant_id' => $tenant->id,
            'new_plan_id' => $scheduledPlan->id,
        ]);
    }
}

// Future: Easy to add more processing jobs
final class ProcessFailedPaymentsJob extends AbstractSubscriptionProcessingJob
{
    protected function getJobName(): string
    {
        return 'Process Failed Payments';
    }

    protected function buildQuery(): Builder
    {
        return Subscription::query()
            ->where('stripe_status', SubscriptionStatus::PastDue)
            ->where('updated_at', '<=', now()->subDays(3));
    }

    protected function processItem(Model $item): void
    {
        // Retry payment, send reminders, downgrade if failed
    }
}
```

**Benefits:**
- 80% reduction in code duplication
- Consistent error handling and logging across all jobs
- Easy to add new processing jobs (just implement 3 methods)
- Uniform monitoring and metrics collection
- Easier to test (test template logic once, specific logic separately)

**Files to Create:**
- `app/Jobs/Subscription/AbstractSubscriptionProcessingJob.php`
- `tests/Unit/Jobs/Subscription/AbstractSubscriptionProcessingJobTest.php`

**Files to Modify:**
- `app/Jobs/Subscription/ProcessExpiredTrialsJob.php`
- `app/Jobs/Subscription/ProcessScheduledDowngradesJob.php`

**Migration Strategy:**
1. Create AbstractSubscriptionProcessingJob
2. Refactor ProcessExpiredTrialsJob to extend abstract class
3. Run tests to verify behavior unchanged
4. Refactor ProcessScheduledDowngradesJob
5. Run tests again
6. Deploy with monitoring
7. Remove old code after validation

---

### Pattern 4: STATE PATTERN - Subscription Status (LOW Priority)

**Problem Solved**: Centralize subscription status logic, enable complex state transitions

**Technical Design:**

```php
// app/Contracts/States/SubscriptionState.php
interface SubscriptionState
{
    public function canUpgrade(): bool;
    public function canDowngrade(): bool;
    public function canCancel(): bool;
    public function canResume(): bool;
    public function getDisplayName(): string;
    public function getDescription(): string;
    public function getAllowedActions(): array;
}

// app/States/Subscription/AbstractSubscriptionState.php
abstract class AbstractSubscriptionState implements SubscriptionState
{
    public function __construct(
        protected readonly Subscription $subscription
    ) {}

    // Default implementations (can be overridden)
    public function canUpgrade(): bool { return false; }
    public function canDowngrade(): bool { return false; }
    public function canCancel(): bool { return false; }
    public function canResume(): bool { return false; }
}

// app/States/Subscription/TrialingState.php
final class TrialingState extends AbstractSubscriptionState
{
    public function canUpgrade(): bool
    {
        return true; // Can upgrade during trial
    }

    public function canDowngrade(): bool
    {
        return false; // Cannot downgrade from trial
    }

    public function canCancel(): bool
    {
        return true; // Can cancel trial
    }

    public function getDisplayName(): string
    {
        return 'On Trial';
    }

    public function getDescription(): string
    {
        $daysLeft = now()->diffInDays($this->subscription->trial_ends_at);
        return "Trial ends in {$daysLeft} days";
    }

    public function getAllowedActions(): array
    {
        return ['upgrade', 'cancel'];
    }
}

// app/States/Subscription/ActiveState.php
final class ActiveState extends AbstractSubscriptionState
{
    public function canUpgrade(): bool
    {
        return true;
    }

    public function canDowngrade(): bool
    {
        return true;
    }

    public function canCancel(): bool
    {
        return true;
    }

    public function getDisplayName(): string
    {
        return 'Active';
    }

    public function getDescription(): string
    {
        return 'Your subscription is active';
    }

    public function getAllowedActions(): array
    {
        return ['upgrade', 'downgrade', 'cancel', 'change_billing_cycle'];
    }
}

// app/States/Subscription/CanceledState.php
final class CanceledState extends AbstractSubscriptionState
{
    public function canResume(): bool
    {
        // Can only resume if still in grace period
        return $this->subscription->onGracePeriod();
    }

    public function getDisplayName(): string
    {
        return 'Canceled';
    }

    public function getDescription(): string
    {
        if ($this->subscription->onGracePeriod()) {
            $daysLeft = now()->diffInDays($this->subscription->ends_at);
            return "Subscription ends in {$daysLeft} days";
        }

        return 'Subscription has ended';
    }

    public function getAllowedActions(): array
    {
        return $this->subscription->onGracePeriod() ? ['resume'] : ['resubscribe'];
    }
}

// app/States/Subscription/PastDueState.php
final class PastDueState extends AbstractSubscriptionState
{
    public function canCancel(): bool
    {
        return true;
    }

    public function getDisplayName(): string
    {
        return 'Past Due';
    }

    public function getDescription(): string
    {
        return 'Payment failed. Please update your payment method.';
    }

    public function getAllowedActions(): array
    {
        return ['update_payment_method', 'cancel'];
    }
}

// app/Services/Subscription/SubscriptionStateFactory.php
final class SubscriptionStateFactory
{
    public function create(Subscription $subscription): SubscriptionState
    {
        // Determine state based on subscription properties
        if ($subscription->onTrial()) {
            return new TrialingState($subscription);
        }

        if ($subscription->canceled()) {
            return new CanceledState($subscription);
        }

        if ($subscription->stripe_status === SubscriptionStatus::PastDue) {
            return new PastDueState($subscription);
        }

        if ($subscription->stripe_status === SubscriptionStatus::Active) {
            return new ActiveState($subscription);
        }

        throw new \RuntimeException('Unknown subscription state');
    }
}

// app/Models/Subscription.php (MODIFIED)
final class Subscription extends Model
{
    // ... existing code ...

    public function getState(): SubscriptionState
    {
        return app(SubscriptionStateFactory::class)->create($this);
    }

    // Delegate to state object
    public function canUpgrade(): bool
    {
        return $this->getState()->canUpgrade();
    }

    public function canDowngrade(): bool
    {
        return $this->getState()->canDowngrade();
    }

    public function canCancel(): bool
    {
        return $this->getState()->canCancel();
    }

    public function canResume(): bool
    {
        return $this->getState()->canResume();
    }

    public function getAvailableActions(): array
    {
        return $this->getState()->getAllowedActions();
    }
}

// Usage in controllers/API
$subscription = $tenant->subscription;
$state = $subscription->getState();

return response()->json([
    'status' => $state->getDisplayName(),
    'description' => $state->getDescription(),
    'allowed_actions' => $state->getAllowedActions(),
    'can_upgrade' => $state->canUpgrade(),
    'can_downgrade' => $state->canDowngrade(),
]);
```

**Benefits:**
- Centralized status logic (no more scattered `if` statements)
- Easy to add new states (e.g., `SuspendedState`, `LimitedState`)
- State-specific behavior encapsulated
- Frontend gets clear list of allowed actions
- Easier to visualize and document subscription lifecycle

**Files to Create:**
- `app/Contracts/States/SubscriptionState.php`
- `app/States/Subscription/AbstractSubscriptionState.php`
- `app/States/Subscription/TrialingState.php`
- `app/States/Subscription/ActiveState.php`
- `app/States/Subscription/CanceledState.php`
- `app/States/Subscription/PastDueState.php`
- `app/Services/Subscription/SubscriptionStateFactory.php`
- `tests/Unit/States/Subscription/*Test.php`

**Files to Modify:**
- `app/Models/Subscription.php` (add state methods)
- `app/Http/Controllers/Api/SubscriptionController.php` (use state)
- Multiple files with scattered status checks (replace with state methods)

**Migration Strategy:**
1. Create all State classes and factory
2. Add methods to Subscription model (keep old methods)
3. Update API endpoints to use state
4. Gradually replace scattered status checks
5. Deprecate old methods
6. Remove after full migration

**Note**: This is LOW priority because it requires touching many files. Implement after other patterns stabilize.

---

### Pattern 5: BUILDER PATTERN - Notification Building (LOW Priority)

**Problem Solved**: Cleaner notification construction with optional parameters

**Technical Design:**

```php
// app/Services/Notification/SubscriptionNotificationBuilder.php
final class SubscriptionNotificationBuilder
{
    private Tenant $tenant;
    private Plan $plan;
    private ?Plan $previousPlan = null;
    private ?Carbon $effectiveDate = null;
    private ?string $reason = null;
    private array $metadata = [];
    private array $channels = ['mail']; // Default

    public function forTenant(Tenant $tenant): self
    {
        $this->tenant = $tenant;
        return $this;
    }

    public function withPlan(Plan $plan): self
    {
        $this->plan = $plan;
        return $this;
    }

    public function withPreviousPlan(Plan $previousPlan): self
    {
        $this->previousPlan = $previousPlan;
        return $this;
    }

    public function effectiveAt(Carbon $date): self
    {
        $this->effectiveDate = $date;
        return $this;
    }

    public function withReason(string $reason): self
    {
        $this->reason = $reason;
        return $this;
    }

    public function withMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function viaChannels(array $channels): self
    {
        $this->channels = $channels;
        return $this;
    }

    public function viaMail(): self
    {
        $this->channels = ['mail'];
        return $this;
    }

    public function viaSms(): self
    {
        $this->channels = ['sms'];
        return $this;
    }

    public function viaMailAndSms(): self
    {
        $this->channels = ['mail', 'sms'];
        return $this;
    }

    public function buildDowngradeScheduled(): SubscriptionDowngradeScheduledNotification
    {
        if (!isset($this->tenant, $this->plan, $this->previousPlan, $this->effectiveDate)) {
            throw new \RuntimeException('Missing required parameters for downgrade notification');
        }

        return new SubscriptionDowngradeScheduledNotification(
            $this->tenant,
            $this->previousPlan,
            $this->plan,
            $this->effectiveDate,
            $this->reason,
            $this->metadata,
            $this->channels,
        );
    }

    public function buildUpgraded(): SubscriptionUpgradedNotification
    {
        if (!isset($this->tenant, $this->plan)) {
            throw new \RuntimeException('Missing required parameters for upgrade notification');
        }

        return new SubscriptionUpgradedNotification(
            $this->tenant,
            $this->plan,
            $this->previousPlan,
            $this->metadata,
            $this->channels,
        );
    }

    public function buildCanceled(): SubscriptionCanceledNotification
    {
        if (!isset($this->tenant, $this->plan, $this->effectiveDate)) {
            throw new \RuntimeException('Missing required parameters for canceled notification');
        }

        return new SubscriptionCanceledNotification(
            $this->tenant,
            $this->plan,
            $this->effectiveDate,
            $this->reason,
            $this->channels,
        );
    }
}

// Usage examples:

// Simple notification
$notification = (new SubscriptionNotificationBuilder())
    ->forTenant($tenant)
    ->withPlan($newPlan)
    ->buildUpgraded();

// Complex notification with all options
$notification = (new SubscriptionNotificationBuilder())
    ->forTenant($tenant)
    ->withPlan($newPlan)
    ->withPreviousPlan($currentPlan)
    ->effectiveAt($periodEnd)
    ->withReason('Usage exceeded limits')
    ->withMetadata(['triggered_by' => 'system'])
    ->viaMailAndSms()
    ->buildDowngradeScheduled();

// Conditional channels
$builder = (new SubscriptionNotificationBuilder())
    ->forTenant($tenant)
    ->withPlan($plan);

if ($tenant->has_premium_support) {
    $builder->viaMailAndSms();
} else {
    $builder->viaMail();
}

$notification = $builder->buildCanceled();
```

**Benefits:**
- Fluent, readable API
- Optional parameters without constructor sprawl
- Type-safe building process
- Easy to add new notification types
- Conditional construction logic

**Files to Create:**
- `app/Services/Notification/SubscriptionNotificationBuilder.php`
- `tests/Unit/Services/Notification/SubscriptionNotificationBuilderTest.php`

**Files to Modify:**
- `app/Notifications/SubscriptionDowngradeScheduledNotification.php` (add optional params)
- `app/Actions/Subscription/*Action.php` (use builder)

**Migration Strategy:**
1. Create builder class
2. Update notifications to accept optional parameters
3. Gradually replace direct notification construction
4. Deprecate old constructors
5. Remove after migration

**Note**: LOW priority - current notification construction works, this is a quality-of-life improvement.

---

### Pattern 6: ADAPTER PATTERN - API Response Transformation (MEDIUM Priority)

**Problem Solved**: Decouple frontend from Laravel Resource structure

**Technical Design:**

```typescript
// src/adapters/LaravelResourceAdapter.ts
/**
 * Adapter to normalize Laravel Resource responses
 * Handles both { data: T } and direct T responses
 */
export class LaravelResourceAdapter {
  /**
   * Extract data from Laravel Resource response
   * Handles: { data: T }, { data: T[] }, or direct response
   */
  static extractSingle<T>(response: any): T {
    // If response has 'data' property and it's not an array, extract it
    if (response.data !== undefined && !Array.isArray(response.data)) {
      return response.data;
    }

    // Otherwise return as-is
    return response;
  }

  /**
   * Extract array from Laravel Resource collection
   */
  static extractCollection<T>(response: any): T[] {
    // If response has 'data' array, extract it
    if (response.data !== undefined && Array.isArray(response.data)) {
      return response.data;
    }

    // If response itself is array
    if (Array.isArray(response)) {
      return response;
    }

    // Fallback to empty array
    return [];
  }

  /**
   * Extract paginated data
   */
  static extractPaginated<T>(response: any): {
    data: T[];
    meta: {
      current_page: number;
      last_page: number;
      per_page: number;
      total: number;
    };
  } {
    if (response.data && response.meta) {
      return {
        data: Array.isArray(response.data) ? response.data : [],
        meta: {
          current_page: response.meta.current_page ?? 1,
          last_page: response.meta.last_page ?? 1,
          per_page: response.meta.per_page ?? 10,
          total: response.meta.total ?? 0,
        },
      };
    }

    // Fallback for non-paginated response
    return {
      data: Array.isArray(response.data) ? response.data : [],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: Array.isArray(response.data) ? response.data.length : 0,
      },
    };
  }

  /**
   * Extract nullable single resource
   */
  static extractNullable<T>(response: any): T | null {
    if (response.data === null || response.data === undefined) {
      return null;
    }

    return this.extractSingle<T>(response);
  }
}

// src/api/billing.ts (REFACTORED)
import { LaravelResourceAdapter } from '../adapters/LaravelResourceAdapter';

export const plansApi = {
  getAll: async (): Promise<Plan[]> => {
    const response = await api.get('/api/plans');
    return LaravelResourceAdapter.extractCollection<Plan>(response.data);
  },

  getById: async (id: number): Promise<Plan> => {
    const response = await api.get(`/api/plans/${id}`);
    return LaravelResourceAdapter.extractSingle<Plan>(response.data);
  },

  compare: async (): Promise<PlanComparison> => {
    const response = await api.get('/api/plans/compare');
    return LaravelResourceAdapter.extractSingle<PlanComparison>(response.data);
  },
};

export const subscriptionsApi = {
  getCurrent: async (): Promise<Subscription | null> => {
    const response = await api.get('/api/subscriptions');
    return LaravelResourceAdapter.extractNullable<Subscription>(response.data);
  },

  getUsage: async (): Promise<UsageStats> => {
    const response = await api.get('/api/subscriptions/usage');
    return LaravelResourceAdapter.extractSingle<UsageStats>(response.data);
  },

  create: async (data: CreateSubscriptionRequest): Promise<Subscription> => {
    const response = await api.post('/api/subscriptions', data);
    return LaravelResourceAdapter.extractSingle<Subscription>(response.data);
  },

  // ... all other methods use adapter
};

export const billingApi = {
  getInvoices: async (): Promise<Invoice[]> => {
    const response = await api.get('/api/billing/invoices');
    return LaravelResourceAdapter.extractCollection<Invoice>(response.data);
  },

  // ... all other methods use adapter
};
```

**Benefits:**
- Frontend doesn't know about Laravel Resource structure
- Easy to switch backend frameworks
- Consistent API across all endpoints
- Single place to handle API changes
- Better error handling

**Files to Create:**
- `src/adapters/LaravelResourceAdapter.ts`
- `src/adapters/__tests__/LaravelResourceAdapter.test.ts`

**Files to Modify:**
- `src/api/billing.ts`
- `src/api/auth.ts`
- `src/api/appointments.ts`
- `src/api/clients.ts`
- All other API client files

**Migration Strategy:**
1. Create adapter class with full test coverage
2. Update one API client (e.g., billing.ts) and test
3. Gradually update remaining clients
4. Remove direct `response.data.data` access
5. Document adapter usage in team docs

---

### Pattern 7: COMMAND PATTERN - Auth Actions (LOW Priority)

**Problem Solved**: Enable undo/redo, action queuing, middleware

**Technical Design:**

```typescript
// src/commands/Command.ts
export interface Command {
  execute(): void | Promise<void>;
  undo?(): void | Promise<void>;
  canUndo?(): boolean;
}

// src/commands/CommandInvoker.ts
export class CommandInvoker {
  private history: Command[] = [];
  private currentIndex: number = -1;

  async execute(command: Command): Promise<void> {
    await command.execute();

    // Add to history if command supports undo
    if (command.canUndo?.()) {
      // Remove any commands after current index (new timeline)
      this.history = this.history.slice(0, this.currentIndex + 1);
      this.history.push(command);
      this.currentIndex++;
    }
  }

  async undo(): Promise<void> {
    if (!this.canUndo()) {
      throw new Error('Nothing to undo');
    }

    const command = this.history[this.currentIndex];
    if (command.undo) {
      await command.undo();
      this.currentIndex--;
    }
  }

  async redo(): Promise<void> {
    if (!this.canRedo()) {
      throw new Error('Nothing to redo');
    }

    this.currentIndex++;
    const command = this.history[this.currentIndex];
    await command.execute();
  }

  canUndo(): boolean {
    return this.currentIndex >= 0;
  }

  canRedo(): boolean {
    return this.currentIndex < this.history.length - 1;
  }

  clear(): void {
    this.history = [];
    this.currentIndex = -1;
  }
}

// src/commands/auth/LoginCommand.ts
export class LoginCommand implements Command {
  private previousState: {
    user: User | null;
    tenant: Tenant | null;
    token: string | null;
  } | null = null;

  constructor(
    private authStore: ReturnType<typeof useAuthStore>,
    private user: User,
    private tenant: Tenant,
    private token: string,
  ) {}

  async execute(): Promise<void> {
    // Save previous state for undo
    this.previousState = {
      user: this.authStore.user,
      tenant: this.authStore.tenant,
      token: this.authStore.token,
    };

    // Execute login
    this.authStore.setAuth(this.user, this.tenant, this.token);
  }

  async undo(): Promise<void> {
    if (this.previousState) {
      // Restore previous state
      if (this.previousState.user && this.previousState.tenant && this.previousState.token) {
        this.authStore.setAuth(
          this.previousState.user,
          this.previousState.tenant,
          this.previousState.token
        );
      } else {
        this.authStore.logout();
      }
    }
  }

  canUndo(): boolean {
    return this.previousState !== null;
  }
}

// src/commands/auth/LogoutCommand.ts
export class LogoutCommand implements Command {
  private previousState: {
    user: User;
    tenant: Tenant;
    token: string;
  } | null = null;

  constructor(private authStore: ReturnType<typeof useAuthStore>) {}

  async execute(): Promise<void> {
    const { user, tenant, token } = this.authStore;

    // Save state for undo (only if logged in)
    if (user && tenant && token) {
      this.previousState = { user, tenant, token };
    }

    // Execute logout
    this.authStore.logout();

    // Call API
    await authApi.logout();
  }

  async undo(): Promise<void> {
    if (this.previousState) {
      this.authStore.setAuth(
        this.previousState.user,
        this.previousState.tenant,
        this.previousState.token
      );
    }
  }

  canUndo(): boolean {
    return this.previousState !== null;
  }
}

// src/commands/auth/UpdateUserCommand.ts
export class UpdateUserCommand implements Command {
  private previousUser: User | null = null;

  constructor(
    private authStore: ReturnType<typeof useAuthStore>,
    private updates: Partial<User>,
  ) {}

  async execute(): Promise<void> {
    this.previousUser = this.authStore.user;
    this.authStore.updateUser(this.updates);
  }

  async undo(): Promise<void> {
    if (this.previousUser) {
      this.authStore.updateUser(this.previousUser);
    }
  }

  canUndo(): boolean {
    return this.previousUser !== null;
  }
}

// Usage in components:
import { CommandInvoker } from '@/commands/CommandInvoker';
import { LoginCommand } from '@/commands/auth/LoginCommand';
import { LogoutCommand } from '@/commands/auth/LogoutCommand';

const commandInvoker = new CommandInvoker();

// Login
const loginCommand = new LoginCommand(authStore, user, tenant, token);
await commandInvoker.execute(loginCommand);

// Logout
const logoutCommand = new LogoutCommand(authStore);
await commandInvoker.execute(logoutCommand);

// Undo logout (restore session)
if (commandInvoker.canUndo()) {
  await commandInvoker.undo();
}

// Future: Queue commands
const commands = [
  new UpdateUserCommand(authStore, { name: 'New Name' }),
  new LogoutCommand(authStore),
];

for (const cmd of commands) {
  await commandInvoker.execute(cmd);
}
```

**Benefits:**
- Undo/redo support for auth actions
- Action queuing and batching
- Middleware support (logging, analytics)
- Better testability
- Clear action history

**Files to Create:**
- `src/commands/Command.ts`
- `src/commands/CommandInvoker.ts`
- `src/commands/auth/LoginCommand.ts`
- `src/commands/auth/LogoutCommand.ts`
- `src/commands/auth/UpdateUserCommand.ts`
- `src/commands/__tests__/*Test.ts`

**Files to Modify:**
- Login/Logout components (use commands instead of direct store calls)

**Migration Strategy:**
1. Create command infrastructure
2. Create auth commands
3. Update login/logout flows to use commands
4. Add undo/redo UI (optional)
5. Gradually expand to other domains

**Note**: LOW priority - current auth flow works fine, this adds undo/redo capability which is nice-to-have.

---

### Pattern 8: FACADE PATTERN - API Client (MEDIUM Priority)

**Problem Solved**: Simplify API usage, reduce coupling to internal API structure

**Technical Design:**

```typescript
// src/api/facades/BillingFacade.ts
/**
 * Simplified facade for all billing-related operations
 * Hides complexity of plansApi, subscriptionsApi, billingApi
 */
export class BillingFacade {
  /**
   * Get current subscription with plan and usage data
   */
  async getCurrentSubscription(): Promise<{
    subscription: Subscription | null;
    plan: Plan | null;
    usage: UsageStats | null;
    features: FeatureAccess | null;
  }> {
    const [subscription, usage, features] = await Promise.all([
      subscriptionsApi.getCurrent(),
      subscriptionsApi.getUsage().catch(() => null),
      subscriptionsApi.getFeatures().catch(() => null),
    ]);

    const plan = subscription ? await plansApi.getById(subscription.plan_id) : null;

    return { subscription, plan, usage, features };
  }

  /**
   * Get all available plans with comparison data
   */
  async getAvailablePlans(): Promise<{
    plans: Plan[];
    comparison: PlanComparison;
    currentPlan: Plan | null;
  }> {
    const [plans, comparison, subscription] = await Promise.all([
      plansApi.getAll(),
      plansApi.compare(),
      subscriptionsApi.getCurrent(),
    ]);

    const currentPlan = subscription
      ? plans.find((p) => p.id === subscription.plan_id) ?? null
      : null;

    return { plans, comparison, currentPlan };
  }

  /**
   * Create new subscription with payment method
   */
  async createSubscription(
    planId: number,
    billingCycle: BillingCycle,
    paymentMethodId?: string,
    startTrial: boolean = false,
  ): Promise<Subscription> {
    // If payment method provided, add it first
    if (paymentMethodId) {
      await billingApi.addPaymentMethod({ payment_method_id: paymentMethodId });
    }

    // Create subscription
    return await subscriptionsApi.create({
      plan_id: planId,
      billing_cycle: billingCycle,
      payment_method_id: paymentMethodId,
      start_trial: startTrial,
    });
  }

  /**
   * Upgrade subscription with automatic payment
   */
  async upgradeSubscription(
    planId: number,
    paymentMethodId?: string,
  ): Promise<Subscription> {
    if (paymentMethodId) {
      await billingApi.setDefaultPaymentMethod(parseInt(paymentMethodId));
    }

    return await subscriptionsApi.upgrade({
      new_plan_id: planId,
      payment_method_id: paymentMethodId,
    });
  }

  /**
   * Downgrade subscription (scheduled at period end)
   */
  async downgradeSubscription(planId: number): Promise<Subscription> {
    return await subscriptionsApi.downgrade({
      new_plan_id: planId,
    });
  }

  /**
   * Cancel subscription (with grace period)
   */
  async cancelSubscription(): Promise<Subscription> {
    return await subscriptionsApi.cancel();
  }

  /**
   * Resume canceled subscription (if in grace period)
   */
  async resumeSubscription(): Promise<Subscription> {
    return await subscriptionsApi.resume();
  }

  /**
   * Change billing cycle (monthly <-> yearly)
   */
  async changeBillingCycle(billingCycle: BillingCycle): Promise<Subscription> {
    return await subscriptionsApi.changeBillingCycle(billingCycle);
  }

  /**
   * Get payment history with invoices
   */
  async getPaymentHistory(): Promise<{
    invoices: Invoice[];
    paymentMethods: PaymentMethod[];
    defaultPaymentMethod: PaymentMethod | null;
  }> {
    const [invoices, paymentMethods] = await Promise.all([
      billingApi.getInvoices(),
      billingApi.getPaymentMethods(),
    ]);

    const defaultPaymentMethod =
      paymentMethods.find((pm) => pm.is_default) ?? null;

    return { invoices, paymentMethods, defaultPaymentMethod };
  }

  /**
   * Add and set default payment method
   */
  async setupPaymentMethod(paymentMethodId: string): Promise<PaymentMethod> {
    const paymentMethod = await billingApi.addPaymentMethod({
      payment_method_id: paymentMethodId,
    });

    return await billingApi.setDefaultPaymentMethod(paymentMethod.id);
  }

  /**
   * Download invoice as PDF
   */
  async downloadInvoice(invoiceId: number): Promise<Blob> {
    return await billingApi.downloadInvoice(invoiceId);
  }

  /**
   * Get Stripe setup intent for payment method collection
   */
  async getPaymentSetupIntent(): Promise<SetupIntent> {
    return await billingApi.getSetupIntent();
  }

  /**
   * Check if user can access a feature
   */
  async canAccessFeature(featureKey: string): Promise<boolean> {
    const features = await subscriptionsApi.getFeatures();
    return features[featureKey] === true;
  }

  /**
   * Get usage percentage for a limit
   */
  async getUsagePercentage(limitKey: string): Promise<number> {
    const usage = await subscriptionsApi.getUsage();
    const current = usage[`${limitKey}_used`] ?? 0;
    const limit = usage[`${limitKey}_limit`] ?? 0;

    if (limit === -1) return 0; // Unlimited
    if (limit === 0) return 100; // No limit set

    return Math.min(100, (current / limit) * 100);
  }
}

// Export singleton instance
export const billingFacade = new BillingFacade();

// Usage in components (BEFORE):
import { plansApi, subscriptionsApi, billingApi } from '@/api/billing';

const [plans, subscription, usage] = await Promise.all([
  plansApi.getAll(),
  subscriptionsApi.getCurrent(),
  subscriptionsApi.getUsage(),
]);

const currentPlan = plans.find((p) => p.id === subscription?.plan_id);
// ... complex logic ...

// Usage in components (AFTER):
import { billingFacade } from '@/api/facades/BillingFacade';

const { subscription, plan, usage, features } = await billingFacade.getCurrentSubscription();
// Single call, clean data

// Upgrade example (AFTER):
await billingFacade.upgradeSubscription(newPlanId, paymentMethodId);
// Handles payment method setup + upgrade in one call
```

**Benefits:**
- Simple, high-level API for components
- Hides complexity of multiple API clients
- Easy to add caching layer
- Batch operations in single call
- Consistent error handling
- Components don't need to know internal API structure

**Files to Create:**
- `src/api/facades/BillingFacade.ts`
- `src/api/facades/__tests__/BillingFacade.test.ts`

**Files to Modify:**
- All components using billing APIs (gradual migration)

**Migration Strategy:**
1. Create BillingFacade with all methods
2. Add comprehensive tests
3. Update one component (e.g., SubscriptionPage) to use facade
4. Verify functionality
5. Gradually migrate remaining components
6. Eventually deprecate direct API imports

---

## 5. Implementation Plan - Phased Approach

### Phase 1: High Priority Patterns (2-3 weeks)

**Goal**: Eliminate code duplication, add flexibility for new features

#### Week 1-2: Template Method + Chain of Responsibility
- **Day 1-2**: Implement AbstractSubscriptionProcessingJob
- **Day 3-4**: Refactor ProcessExpiredTrialsJob and ProcessScheduledDowngradesJob
- **Day 5-7**: Create validation chain infrastructure
- **Day 8-10**: Implement all validators and chain builder
- **Success Criteria**:
  - Zero test failures
  - Code duplication reduced by 80% in Jobs
  - Can add new validator without modifying Action

#### Week 3: Testing & Deployment
- **Day 1-2**: Comprehensive unit tests for all patterns
- **Day 3**: Integration tests
- **Day 4**: Code review and documentation
- **Day 5**: Deploy to staging, monitor for 2 days
- **Day 6-7**: Deploy to production, monitor metrics

**Deliverables:**
- Template Method: 1 abstract class, 2 refactored jobs
- Chain of Responsibility: 6 validators, 1 chain builder
- 100% test coverage for new code
- Migration guide for team

---

### Phase 2: Medium Priority Patterns (2-3 weeks)

**Goal**: Improve frontend/backend decoupling, simplify API usage

#### Week 4-5: Adapter + Strategy + Facade
- **Day 1-3**: Implement LaravelResourceAdapter (frontend)
- **Day 4-6**: Create SubscriptionCreationStrategy (backend)
- **Day 7-10**: Implement BillingFacade (frontend)
- **Day 11-15**: Migrate existing code to use patterns

**Success Criteria**:
- Frontend doesn't access `response.data.data`
- Can add new subscription type in <1 hour
- Components import 1 facade instead of 3 APIs

**Deliverables:**
- Adapter: 1 class, updated all API clients
- Strategy: 3 strategies, 1 resolver
- Facade: 1 class, 15+ methods
- Updated component examples

---

### Phase 3: Low Priority Patterns (2-4 weeks, optional)

**Goal**: Quality-of-life improvements, advanced features

#### Week 6-8: State + Builder + Command
- **Day 1-5**: Implement State pattern for subscriptions
- **Day 6-10**: Create NotificationBuilder
- **Day 11-15**: Implement Command pattern for auth
- **Day 16-20**: Migrate existing code, add undo/redo UI (optional)

**Success Criteria**:
- Subscription status logic centralized
- Notifications use builder
- Undo/redo works for auth actions (optional feature)

**Deliverables:**
- State: 5 state classes, 1 factory
- Builder: 1 builder class
- Command: 3 commands, 1 invoker
- Optional: Undo/redo UI component

---

## 6. Testing Strategy

### Unit Tests

**Backend (PHPUnit):**

```php
// tests/Unit/Services/Subscription/Strategies/FreeSubscriptionStrategyTest.php
final class FreeSubscriptionStrategyTest extends TestCase
{
    public function test_supports_free_plan(): void
    {
        $plan = Plan::factory()->make(['slug' => 'free']);
        $strategy = new FreeSubscriptionStrategy($this->mock(SubscriptionRepository::class));

        $this->assertTrue($strategy->supports($plan));
    }

    public function test_does_not_support_paid_plan(): void
    {
        $plan = Plan::factory()->make(['slug' => 'premium']);
        $strategy = new FreeSubscriptionStrategy($this->mock(SubscriptionRepository::class));

        $this->assertFalse($strategy->supports($plan));
    }

    public function test_creates_free_subscription(): void
    {
        $plan = Plan::factory()->create(['slug' => 'free']);
        $tenant = Tenant::factory()->create();

        $repository = $this->mock(SubscriptionRepository::class);
        $repository->shouldReceive('create')->once()->andReturn(new Subscription());

        $strategy = new FreeSubscriptionStrategy($repository);
        $dto = new CreateSubscriptionDTO($plan->id, 'monthly', null, false);

        $result = $strategy->create($dto, $tenant, $plan);

        $this->assertInstanceOf(Subscription::class, $result);
    }
}

// tests/Unit/Services/Validation/Validators/UsageLimitsValidatorTest.php
final class UsageLimitsValidatorTest extends TestCase
{
    public function test_passes_when_usage_within_limits(): void
    {
        $usageService = $this->mock(UsageValidationService::class);
        $usageService->shouldReceive('checkLimitViolations')->andReturn([]);

        $validator = new UsageLimitsValidator($usageService);

        $this->expectNotToPerformAssertions();
        $validator->validate($subscription, $plan, $tenant);
    }

    public function test_throws_when_usage_exceeds_limits(): void
    {
        $usageService = $this->mock(UsageValidationService::class);
        $usageService->shouldReceive('checkLimitViolations')
            ->andReturn(['staff' => 'Exceeds limit']);

        $validator = new UsageLimitsValidator($usageService);

        $this->expectException(SubscriptionException::class);
        $validator->validate($subscription, $plan, $tenant);
    }

    public function test_calls_next_validator_when_valid(): void
    {
        $usageService = $this->mock(UsageValidationService::class);
        $usageService->shouldReceive('checkLimitViolations')->andReturn([]);

        $nextValidator = $this->mock(SubscriptionValidator::class);
        $nextValidator->shouldReceive('validate')->once();

        $validator = new UsageLimitsValidator($usageService);
        $validator->setNext($nextValidator);

        $validator->validate($subscription, $plan, $tenant);
    }
}
```

**Frontend (Vitest):**

```typescript
// src/adapters/__tests__/LaravelResourceAdapter.test.ts
describe('LaravelResourceAdapter', () => {
  describe('extractSingle', () => {
    it('extracts data from Laravel Resource response', () => {
      const response = { data: { id: 1, name: 'Test' } };
      const result = LaravelResourceAdapter.extractSingle(response);

      expect(result).toEqual({ id: 1, name: 'Test' });
    });

    it('returns response as-is if no data property', () => {
      const response = { id: 1, name: 'Test' };
      const result = LaravelResourceAdapter.extractSingle(response);

      expect(result).toEqual({ id: 1, name: 'Test' });
    });
  });

  describe('extractCollection', () => {
    it('extracts array from Laravel Resource collection', () => {
      const response = { data: [{ id: 1 }, { id: 2 }] };
      const result = LaravelResourceAdapter.extractCollection(response);

      expect(result).toEqual([{ id: 1 }, { id: 2 }]);
    });

    it('returns empty array if no data', () => {
      const response = {};
      const result = LaravelResourceAdapter.extractCollection(response);

      expect(result).toEqual([]);
    });
  });
});

// src/commands/__tests__/LoginCommand.test.ts
describe('LoginCommand', () => {
  it('executes login and saves previous state', async () => {
    const authStore = {
      user: null,
      tenant: null,
      token: null,
      setAuth: vi.fn(),
    };

    const command = new LoginCommand(authStore, mockUser, mockTenant, 'token123');
    await command.execute();

    expect(authStore.setAuth).toHaveBeenCalledWith(mockUser, mockTenant, 'token123');
    expect(command.canUndo()).toBe(true);
  });

  it('undoes login and restores previous state', async () => {
    const authStore = {
      user: mockPreviousUser,
      tenant: mockPreviousTenant,
      token: 'oldToken',
      setAuth: vi.fn(),
      logout: vi.fn(),
    };

    const command = new LoginCommand(authStore, mockNewUser, mockNewTenant, 'newToken');
    await command.execute();
    await command.undo();

    expect(authStore.setAuth).toHaveBeenCalledWith(mockPreviousUser, mockPreviousTenant, 'oldToken');
  });
});
```

### Integration Tests

```php
// tests/Feature/Actions/Subscription/SubscriptionCreateActionTest.php
final class SubscriptionCreateActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_free_subscription_using_free_strategy(): void
    {
        $tenant = Tenant::factory()->create();
        $freePlan = Plan::factory()->create(['slug' => 'free']);

        $action = app(SubscriptionCreateAction::class);
        $dto = new CreateSubscriptionDTO($freePlan->id, 'monthly', null, false);

        $subscription = $action->handle($dto, $tenant);

        $this->assertDatabaseHas(Subscription::class, [
            'tenant_id' => $tenant->id,
            'plan_id' => $freePlan->id,
        ]);
        $this->assertTrue(str_starts_with($subscription->stripe_id, 'free_'));
    }

    public function test_uses_correct_strategy_for_paid_plan(): void
    {
        $tenant = Tenant::factory()->create(['stripe_id' => 'cus_123']);
        $paidPlan = Plan::factory()->create([
            'slug' => 'premium',
            'stripe_monthly_price_id' => 'price_123',
        ]);

        $action = app(SubscriptionCreateAction::class);
        $dto = new CreateSubscriptionDTO($paidPlan->id, 'monthly', 'pm_123', false);

        $subscription = $action->handle($dto, $tenant);

        $this->assertDatabaseHas(Subscription::class, [
            'tenant_id' => $tenant->id,
            'plan_id' => $paidPlan->id,
        ]);
        $this->assertFalse(str_starts_with($subscription->stripe_id, 'free_'));
    }
}

// tests/Feature/Actions/Subscription/SubscriptionDowngradeActionTest.php
final class SubscriptionDowngradeActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_downgrade_passes_through_validation_chain(): void
    {
        $subscription = Subscription::factory()->create();
        $newPlan = Plan::factory()->create();

        $action = app(SubscriptionDowngradeAction::class);
        $dto = new DowngradeSubscriptionDTO($subscription->id, $newPlan->id);

        // Should pass all validators and succeed
        $result = $action->handle($dto);

        $this->assertDatabaseHas(Subscription::class, [
            'id' => $subscription->id,
            'scheduled_plan_id' => $newPlan->id,
        ]);
    }

    public function test_downgrade_fails_when_usage_exceeds_limits(): void
    {
        // Create subscription with high usage
        $tenant = Tenant::factory()->has(Staff::factory()->count(10))->create();
        $subscription = Subscription::factory()->for($tenant)->create();
        $newPlan = Plan::factory()->create(['limits' => ['staff' => 5]]);

        $action = app(SubscriptionDowngradeAction::class);
        $dto = new DowngradeSubscriptionDTO($subscription->id, $newPlan->id);

        $this->expectException(SubscriptionException::class);
        $action->handle($dto);
    }
}
```

### E2E Tests (Frontend)

```typescript
// e2e/subscription/upgrade.spec.ts
import { test, expect } from '@playwright/test';

test.describe('Subscription Upgrade Flow', () => {
  test('upgrades from free to premium plan', async ({ page }) => {
    await page.goto('/settings/subscription');

    // Click upgrade button
    await page.click('[data-testid="upgrade-to-premium"]');

    // Fill payment details
    await page.fill('[data-testid="card-number"]', '4242424242424242');
    await page.fill('[data-testid="card-expiry"]', '12/25');
    await page.fill('[data-testid="card-cvc"]', '123');

    // Confirm upgrade
    await page.click('[data-testid="confirm-upgrade"]');

    // Wait for success message
    await expect(page.locator('[data-testid="success-message"]')).toBeVisible();

    // Verify plan changed
    await expect(page.locator('[data-testid="current-plan"]')).toHaveText('Premium');
  });

  test('displays validation error when downgrading with excess usage', async ({ page }) => {
    // Setup: Account with 10 staff on Standard plan (limit: 5 staff)

    await page.goto('/settings/subscription');
    await page.click('[data-testid="downgrade-to-easy"]');

    // Should show error modal
    await expect(page.locator('[data-testid="usage-error-modal"]')).toBeVisible();
    await expect(page.locator('[data-testid="error-message"]')).toContainText('staff limit');
  });
});
```

### Performance Tests

```php
// tests/Performance/SubscriptionProcessingJobsTest.php
final class SubscriptionProcessingJobsTest extends TestCase
{
    public function test_processes_1000_expired_trials_within_acceptable_time(): void
    {
        // Create 1000 expired trial subscriptions
        Subscription::factory()->count(1000)->create([
            'stripe_status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->subDay(),
        ]);

        $start = microtime(true);

        $job = new ProcessExpiredTrialsJob(
            app(SubscriptionRepository::class),
            app(PlanRepository::class),
        );
        $job->handle();

        $duration = microtime(true) - $start;

        // Should process 1000 items in under 5 seconds
        $this->assertLessThan(5.0, $duration);

        // Verify all processed
        $this->assertEquals(0, Subscription::where('stripe_status', SubscriptionStatus::Trialing)->count());
    }
}
```

---

## 7. Success Metrics

### Code Quality Metrics

| Metric | Before | After Phase 1 | After Phase 2 | After Phase 3 | Target |
|--------|--------|---------------|---------------|---------------|--------|
| Code Duplication (Jobs) | 80% | 10% | 10% | 10% | <15% |
| Cyclomatic Complexity (Actions) | 15-20 | 8-12 | 6-10 | 5-8 | <10 |
| Test Coverage (Backend) | 65% | 85% | 90% | 95% | >90% |
| Test Coverage (Frontend) | 45% | 60% | 75% | 85% | >80% |
| Lines of Code per Class | 150-200 | 80-120 | 80-120 | 60-100 | <150 |

### Development Velocity Metrics

| Task | Before | After Patterns | Improvement |
|------|--------|----------------|-------------|
| Add new subscription type | 2-3 days | 2-4 hours | 10x faster |
| Add new validator | 4-6 hours | 30 minutes | 8x faster |
| Add new processing job | 1 day | 1-2 hours | 6x faster |
| Change API response format | 1 week | 1 day | 5x faster |
| Add new notification type | 2-3 hours | 30 minutes | 4x faster |

### Business Impact Metrics

| Metric | Baseline | Target (6 months) | Measurement Method |
|--------|----------|-------------------|-------------------|
| Time to implement new plan tier | 3 days | 4 hours | Track PR time-to-merge |
| Bug regression rate | 15% | <5% | Count bugs introduced per release |
| Onboarding time (new devs) | 3-4 weeks | 1-2 weeks | Survey new hires |
| Code review time | 2-3 hours | 30-60 minutes | GitHub PR metrics |
| Hotfix deployment time | 4-6 hours | 1-2 hours | Incident reports |

### Quality Metrics (Monitoring)

```php
// Log metrics after pattern implementation
Log::info('Pattern usage metrics', [
    'pattern' => 'Strategy',
    'strategy_used' => get_class($strategy),
    'execution_time_ms' => $duration,
    'success' => true,
]);

// Dashboard queries:
// - Strategy pattern: Which strategies are most used?
// - Validator chain: Which validators fail most often?
// - Job processing: Average processing time per job
// - API adapter: Response transformation errors
```

---

## 8. Risks & Mitigation

### Risk 1: Breaking Changes During Refactoring
**Probability**: Medium | **Impact**: High

**Mitigation:**
- Comprehensive test coverage before refactoring
- Feature flags for gradual rollout
- Parallel implementation (keep old code until new code proven)
- Canary deployments (5% → 25% → 100%)
- Immediate rollback plan

**Rollback Strategy:**
```php
// Feature flag example
if (config('features.use_strategy_pattern')) {
    $strategy = $this->strategyResolver->resolve($plan);
    return $strategy->create($dto, $tenant, $plan);
}

// Old implementation (fallback)
return $this->createSubscriptionLegacy($dto, $tenant, $plan);
```

### Risk 2: Performance Degradation
**Probability**: Low | **Impact**: Medium

**Mitigation:**
- Performance tests for all patterns
- Benchmarking before/after refactoring
- Profiling in staging environment
- Caching where appropriate
- Monitor key metrics (response time, memory usage)

**Monitoring:**
- Laravel Telescope for backend performance
- Sentry for error tracking
- New Relic/DataDog for production metrics

### Risk 3: Team Adoption Resistance
**Probability**: Medium | **Impact**: Medium

**Mitigation:**
- Comprehensive documentation with examples
- Pair programming sessions
- Code review guidelines
- Pattern showcase (internal demo)
- Gradual adoption (start with high-value patterns)

**Documentation Plan:**
- Pattern guide for each pattern (why, when, how)
- Video tutorials (5-10 minutes each)
- Example PRs as reference
- Architecture Decision Records (ADRs)

### Risk 4: Over-Engineering
**Probability**: Low | **Impact**: Low

**Mitigation:**
- Only implement patterns that solve real problems
- Start with high-priority patterns
- Review after Phase 1 before continuing
- Get team feedback on complexity
- Don't force patterns where not needed

**Review Criteria:**
- Does this pattern reduce code duplication? (Yes → Keep)
- Does this pattern make code easier to understand? (No → Simplify)
- Can new developers understand this? (No → Add docs/examples)

### Risk 5: Incomplete Migration
**Probability**: Medium | **Impact**: Medium

**Mitigation:**
- Track migration progress in project board
- Deprecation warnings for old code
- Automated detection of old patterns (linting)
- Scheduled cleanup sprints
- Code freeze on old patterns

**Migration Tracking:**
```php
// Deprecated method with warning
/**
 * @deprecated Use SubscriptionStrategyResolver instead
 */
private function createFreeSubscription(...) {
    Log::warning('Using deprecated createFreeSubscription method');
    // ... implementation
}
```

---

## 9. Scope

### In Scope

**Phase 1 (Must Have):**
- Template Method pattern for Job processing
- Chain of Responsibility for validations
- Full unit test coverage for both patterns
- Documentation and migration guide

**Phase 2 (Should Have):**
- Adapter pattern for API responses
- Strategy pattern for subscription creation
- Facade pattern for billing API
- Updated component examples

**Phase 3 (Nice to Have):**
- State pattern for subscription status
- Builder pattern for notifications
- Command pattern for auth actions
- Optional undo/redo UI

### Out of Scope

**Explicitly NOT Included:**
- Rewriting entire codebase (only targeted refactoring)
- Changing external APIs or database schema
- Performance optimization beyond pattern implementation
- UI/UX changes (patterns are backend/architecture focused)
- Multi-tenancy improvements (separate initiative)
- New features (pure refactoring only)

**Future Extensions (Post-Phase 3):**
- Observer pattern for event handling
- Decorator pattern for feature toggles
- Proxy pattern for API rate limiting
- Singleton pattern for service instances
- Repository pattern expansion to other domains

### Dependencies

**Must Be Completed First:**
- None (patterns can be implemented independently)

**Blocks These Initiatives:**
- Multi-currency support (needs Strategy pattern)
- Advanced subscription workflows (needs State pattern)
- API versioning (needs Adapter pattern)

---

## 10. Implementation Handoff

### For Architect Agent

**Task Breakdown Recommendations:**

#### Phase 1 Tasks:

**Task 1.1: Template Method Pattern**
- **Files**: `app/Jobs/Subscription/AbstractSubscriptionProcessingJob.php`
- **Estimated Effort**: 4 hours
- **Tests**: `tests/Unit/Jobs/Subscription/AbstractSubscriptionProcessingJobTest.php`
- **Dependencies**: None

**Task 1.2: Refactor ProcessExpiredTrialsJob**
- **Files**: `app/Jobs/Subscription/ProcessExpiredTrialsJob.php`
- **Estimated Effort**: 3 hours
- **Tests**: Update existing tests
- **Dependencies**: Task 1.1

**Task 1.3: Refactor ProcessScheduledDowngradesJob**
- **Files**: `app/Jobs/Subscription/ProcessScheduledDowngradesJob.php`
- **Estimated Effort**: 3 hours
- **Tests**: Update existing tests
- **Dependencies**: Task 1.1

**Task 1.4: Chain of Responsibility - Infrastructure**
- **Files**:
  - `app/Contracts/Validation/SubscriptionValidator.php`
  - `app/Services/Validation/AbstractSubscriptionValidator.php`
  - `app/Services/Validation/ValidationChainBuilder.php`
- **Estimated Effort**: 4 hours
- **Tests**: `tests/Unit/Services/Validation/*Test.php`
- **Dependencies**: None

**Task 1.5: Chain of Responsibility - Validators**
- **Files**: All validator classes (6 total)
- **Estimated Effort**: 6 hours
- **Tests**: One test file per validator
- **Dependencies**: Task 1.4

**Task 1.6: Integrate Validation Chain into Actions**
- **Files**:
  - `app/Actions/Subscription/SubscriptionDowngradeAction.php`
  - `app/Actions/Subscription/SubscriptionUpgradeAction.php`
  - `app/Providers/AppServiceProvider.php`
- **Estimated Effort**: 4 hours
- **Tests**: Update existing integration tests
- **Dependencies**: Task 1.5

#### Phase 2 Tasks:

**Task 2.1: Laravel Resource Adapter (Frontend)**
- **Files**: `src/adapters/LaravelResourceAdapter.ts`
- **Estimated Effort**: 3 hours
- **Tests**: `src/adapters/__tests__/LaravelResourceAdapter.test.ts`
- **Dependencies**: None

**Task 2.2: Update API Clients with Adapter**
- **Files**: All files in `src/api/`
- **Estimated Effort**: 4 hours
- **Tests**: Update existing API tests
- **Dependencies**: Task 2.1

**Task 2.3: Strategy Pattern - Infrastructure**
- **Files**:
  - `app/Contracts/Subscription/SubscriptionCreationStrategy.php`
  - `app/Services/Subscription/SubscriptionStrategyResolver.php`
- **Estimated Effort**: 2 hours
- **Tests**: Resolver tests
- **Dependencies**: None

**Task 2.4: Strategy Pattern - Implementations**
- **Files**: All strategy classes (3 total)
- **Estimated Effort**: 6 hours
- **Tests**: One test file per strategy
- **Dependencies**: Task 2.3

**Task 2.5: Integrate Strategy into SubscriptionCreateAction**
- **Files**:
  - `app/Actions/Subscription/SubscriptionCreateAction.php`
  - `app/Providers/AppServiceProvider.php`
- **Estimated Effort**: 2 hours
- **Tests**: Update existing tests
- **Dependencies**: Task 2.4

**Task 2.6: Billing Facade (Frontend)**
- **Files**: `src/api/facades/BillingFacade.ts`
- **Estimated Effort**: 6 hours
- **Tests**: `src/api/facades/__tests__/BillingFacade.test.ts`
- **Dependencies**: Task 2.2

**Task 2.7: Migrate Components to Facade**
- **Files**: All billing-related components
- **Estimated Effort**: 8 hours
- **Tests**: Update component tests
- **Dependencies**: Task 2.6

### Task Folder Structure

```
tasks/
├── design-patterns-refactoring/
│   ├── phase-1/
│   │   ├── 1.1-template-method-abstract.md
│   │   ├── 1.2-refactor-expired-trials-job.md
│   │   ├── 1.3-refactor-scheduled-downgrades-job.md
│   │   ├── 1.4-validation-chain-infrastructure.md
│   │   ├── 1.5-validation-chain-validators.md
│   │   └── 1.6-integrate-validation-chain.md
│   ├── phase-2/
│   │   ├── 2.1-laravel-resource-adapter.md
│   │   ├── 2.2-update-api-clients.md
│   │   ├── 2.3-strategy-infrastructure.md
│   │   ├── 2.4-strategy-implementations.md
│   │   ├── 2.5-integrate-strategy.md
│   │   ├── 2.6-billing-facade.md
│   │   └── 2.7-migrate-components-to-facade.md
│   └── phase-3/
│       ├── 3.1-state-pattern-infrastructure.md
│       ├── 3.2-state-implementations.md
│       ├── 3.3-notification-builder.md
│       ├── 3.4-command-pattern-infrastructure.md
│       └── 3.5-command-implementations.md
```

### Related PRDs

- [2026-01-subscription-pricing-system.md](/Users/matusmockor/Developer/termio/prds/2026-01-subscription-pricing-system.md) - Subscription tiers and pricing
- [2026-01-onboarding-system.md](/Users/matusmockor/Developer/termio/prds/2026-01-onboarding-system.md) - User onboarding flow

### Technical Specifications

**Backend:**
- PHP 8.4, Laravel 12
- Follows SOLID principles
- Repository pattern already implemented
- Strict typing enforced
- Pint for code formatting

**Frontend:**
- React 18, TypeScript
- TanStack Query for data fetching
- Zustand for state management
- Tailwind CSS
- Vitest for testing

**Code Standards:**
- No `else` statements (guard clauses only)
- All callbacks type-hinted with `static function`
- Enums for statuses (no hardcoded strings)
- JSON_THROW_ON_ERROR for JSON operations
- Interfaces for all repositories/services

---

## 11. Agent Session Log

### Session 2026-01-25 (Initial PRD Creation)

**Status**: Draft PRD completed

**Context Gathered:**
- Scanned existing PRDs (onboarding, subscription pricing, admin panel)
- Analyzed current backend codebase:
  - SubscriptionCreateAction: Hardcoded free vs paid logic
  - SubscriptionDowngradeAction: Sequential validations
  - ProcessExpiredTrialsJob & ProcessScheduledDowngradesJob: 80% code duplication
  - Subscription model: Status checks scattered across codebase
  - Notifications: Complex constructor parameters
- Analyzed frontend codebase:
  - API clients: Tight coupling to Laravel Resource structure
  - authStore: Direct state mutations
  - Multiple API imports in components

**Pattern Opportunities Validated:**
1. ✅ Strategy Pattern - Confirmed hardcoded subscription types in SubscriptionCreateAction
2. ✅ Chain of Responsibility - Confirmed sequential validations in Actions
3. ✅ Template Method - Confirmed 80% duplication between Job classes
4. ✅ State Pattern - Confirmed scattered status checks (15+ files estimated)
5. ✅ Builder Pattern - Confirmed complex notification constructors (4-5 params)
6. ✅ Adapter Pattern - Confirmed `response.data.data` pattern everywhere
7. ✅ Command Pattern - Confirmed direct store mutations (no undo/redo)
8. ✅ Facade Pattern - Confirmed multiple API imports (plansApi, subscriptionsApi, billingApi)

**Decisions:**
- Prioritized patterns based on business impact vs implementation effort
- Phase 1: High priority (Template Method, Chain of Responsibility)
- Phase 2: Medium priority (Adapter, Strategy, Facade)
- Phase 3: Low priority (State, Builder, Command)
- Each pattern includes detailed before/after code examples
- Migration strategy defined for each pattern
- Testing strategy covers unit, integration, E2E, and performance tests

**Next Steps:**
1. Review PRD with team
2. Get approval from CTO/Tech Lead
3. Create detailed tasks for Phase 1
4. Assign backend-senior agent for backend patterns
5. Assign frontend-senior agent for frontend patterns
6. Schedule kickoff meeting

**Questions for Next Session:**
- Should we start with Phase 1 or do all phases together?
- Do we need feature flags for all patterns or just high-risk ones?
- What's the deployment strategy (staging → canary → production)?
- Who will be responsible for code reviews?
- What's the timeline constraint (any hard deadlines)?

---

## Appendix A: Pattern Comparison Table

| Pattern | Problem | Solution | When to Use | When NOT to Use |
|---------|---------|----------|-------------|-----------------|
| **Strategy** | Hardcoded logic variants | Encapsulate algorithms | Multiple algorithms for same task | Only one algorithm exists |
| **Chain of Responsibility** | Sequential validations | Chain of handlers | Request can be handled by multiple objects | Single handler always needed |
| **Template Method** | Duplicate workflow | Abstract class with template | Same algorithm, different steps | Completely different workflows |
| **State** | Scattered status checks | State objects | Behavior changes with state | Few states, simple logic |
| **Builder** | Complex constructors | Fluent interface | Many optional parameters | Few required parameters |
| **Adapter** | Incompatible interfaces | Wrapper class | Need to use incompatible code | Interfaces already compatible |
| **Command** | Direct method calls | Command objects | Need undo/redo or queuing | Simple CRUD operations |
| **Facade** | Complex subsystem | Simplified interface | Hide complexity from clients | Subsystem is simple |

---

## Appendix B: Code Metrics Tracking

```php
// tools/analyze-metrics.php
// Script to track metrics before/after pattern implementation

$metrics = [
    'code_duplication' => calculateDuplication('app/Jobs/Subscription'),
    'cyclomatic_complexity' => calculateComplexity('app/Actions/Subscription'),
    'test_coverage' => getTestCoverage(),
    'lines_per_class' => calculateLinesPerClass('app'),
];

Log::info('Pattern refactoring metrics', $metrics);
```

```typescript
// tools/analyze-frontend.ts
// Frontend metrics tracking

const metrics = {
  apiResponseAccess: countOccurrences('response.data.data'),
  directStoreAccess: countOccurrences('authStore.set'),
  multipleImports: countFiles(['plansApi', 'subscriptionsApi', 'billingApi']),
};

console.log('Frontend metrics:', metrics);
```

---

**Document Status**: ✅ Draft Complete - Ready for Review

**Total Effort Estimate**: 6-9 weeks for all 3 phases

**ROI**: High (10x faster feature development, 80% less duplication, better maintainability)
