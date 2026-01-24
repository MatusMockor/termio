# Plan Management Backend

**PRD Source**: `prds/2026-01-subscription-pricing-system.md`
**Category**: Backend
**Complexity**: Medium
**Dependencies**: `database_schema.md`, `backend_stripe_integration.md`
**Status**: Not Started

## Technical Overview

**Summary**: Implement backend CRUD operations for subscription plans including creation, updates, and soft activation/deactivation. Plans are primarily seeded but admin can modify pricing and limits. Per PRD REQ-01.

**Architecture Impact**: Adds Plan model, repository, actions, and API controller. Plans define features and limits that drive the entire subscription system.

**Risk Assessment**:
- **Medium**: Price changes should only affect new subscriptions
- **Low**: Plan deactivation must check for active subscribers
- **Low**: Feature flag changes affect all users immediately

## Data Layer

### Plan Model

**File**: `app/Models/Plan.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property float $monthly_price
 * @property float $yearly_price
 * @property string|null $stripe_monthly_price_id
 * @property string|null $stripe_yearly_price_id
 * @property array<string, mixed> $features
 * @property array<string, int> $limits
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_public
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read Collection<int, Subscription> $subscriptions
 */
final class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'monthly_price',
        'yearly_price',
        'stripe_monthly_price_id',
        'stripe_yearly_price_id',
        'features',
        'limits',
        'sort_order',
        'is_active',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'features' => 'array',
            'limits' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Check if plan is the free tier.
     */
    public function isFree(): bool
    {
        return $this->slug === 'free';
    }

    /**
     * Get yearly discount percentage.
     */
    public function getYearlyDiscountPercentage(): float
    {
        if ($this->monthly_price <= 0) {
            return 0;
        }

        $annualMonthlyTotal = $this->monthly_price * 12;
        $savings = $annualMonthlyTotal - $this->yearly_price;

        return round(($savings / $annualMonthlyTotal) * 100, 0);
    }

    /**
     * Get monthly equivalent of yearly price.
     */
    public function getYearlyMonthlyEquivalent(): float
    {
        return round($this->yearly_price / 12, 2);
    }

    /**
     * Check if plan has a specific feature.
     */
    public function hasFeature(string $feature): bool
    {
        if (!isset($this->features[$feature])) {
            return false;
        }

        $value = $this->features[$feature];

        if (is_bool($value)) {
            return $value;
        }

        return $value !== false && $value !== 'none';
    }

    /**
     * Get a specific limit value.
     */
    public function getLimit(string $resource): int
    {
        return $this->limits[$resource] ?? 0;
    }

    /**
     * Check if limit is unlimited (-1).
     */
    public function isUnlimited(string $resource): bool
    {
        return $this->getLimit($resource) === -1;
    }

    /**
     * Check if plan has active subscribers.
     */
    public function hasActiveSubscribers(): bool
    {
        return $this->subscriptions()
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->exists();
    }
}
```

### DTOs

**File**: `app/DTOs/Plan/CreatePlanDTO.php`

```php
<?php

declare(strict_types=1);

namespace App\DTOs\Plan;

final readonly class CreatePlanDTO
{
    /**
     * @param  array<string, mixed>  $features
     * @param  array<string, int>  $limits
     */
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $description,
        public float $monthlyPrice,
        public float $yearlyPrice,
        public array $features,
        public array $limits,
        public int $sortOrder = 0,
        public bool $isActive = true,
        public bool $isPublic = true,
    ) {}
}
```

**File**: `app/DTOs/Plan/UpdatePlanDTO.php`

```php
<?php

declare(strict_types=1);

namespace App\DTOs\Plan;

final readonly class UpdatePlanDTO
{
    /**
     * @param  array<string, mixed>|null  $features
     * @param  array<string, int>|null  $limits
     */
    public function __construct(
        public ?string $description = null,
        public ?float $monthlyPrice = null,
        public ?float $yearlyPrice = null,
        public ?string $stripeMontlyPriceId = null,
        public ?string $stripeYearlyPriceId = null,
        public ?array $features = null,
        public ?array $limits = null,
        public ?int $sortOrder = null,
        public ?bool $isActive = null,
        public ?bool $isPublic = null,
    ) {}
}
```

## Component Architecture

### Plan Actions

**File**: `app/Actions/Plan/PlanCreateAction.php`

```php
<?php

declare(strict_types=1);

namespace App\Actions\Plan;

use App\Contracts\Repositories\PlanRepository;
use App\DTOs\Plan\CreatePlanDTO;
use App\Exceptions\PlanException;
use App\Models\Plan;
use Illuminate\Support\Str;

final class PlanCreateAction
{
    public function __construct(
        private readonly PlanRepository $plans,
    ) {}

    public function handle(CreatePlanDTO $dto): Plan
    {
        // Validate unique slug
        $existingPlan = $this->plans->findBySlug($dto->slug);
        if ($existingPlan) {
            throw PlanException::slugAlreadyExists($dto->slug);
        }

        return $this->plans->create([
            'name' => $dto->name,
            'slug' => Str::slug($dto->slug),
            'description' => $dto->description,
            'monthly_price' => $dto->monthlyPrice,
            'yearly_price' => $dto->yearlyPrice,
            'features' => $dto->features,
            'limits' => $dto->limits,
            'sort_order' => $dto->sortOrder,
            'is_active' => $dto->isActive,
            'is_public' => $dto->isPublic,
        ]);
    }
}
```

**File**: `app/Actions/Plan/PlanUpdateAction.php`

```php
<?php

declare(strict_types=1);

namespace App\Actions\Plan;

use App\Contracts\Repositories\PlanRepository;
use App\DTOs\Plan\UpdatePlanDTO;
use App\Models\Plan;

final class PlanUpdateAction
{
    public function __construct(
        private readonly PlanRepository $plans,
    ) {}

    public function handle(Plan $plan, UpdatePlanDTO $dto): Plan
    {
        $updateData = [];

        if ($dto->description !== null) {
            $updateData['description'] = $dto->description;
        }

        if ($dto->monthlyPrice !== null) {
            $updateData['monthly_price'] = $dto->monthlyPrice;
        }

        if ($dto->yearlyPrice !== null) {
            $updateData['yearly_price'] = $dto->yearlyPrice;
        }

        if ($dto->stripeMontlyPriceId !== null) {
            $updateData['stripe_monthly_price_id'] = $dto->stripeMontlyPriceId;
        }

        if ($dto->stripeYearlyPriceId !== null) {
            $updateData['stripe_yearly_price_id'] = $dto->stripeYearlyPriceId;
        }

        if ($dto->features !== null) {
            $updateData['features'] = $dto->features;
        }

        if ($dto->limits !== null) {
            $updateData['limits'] = $dto->limits;
        }

        if ($dto->sortOrder !== null) {
            $updateData['sort_order'] = $dto->sortOrder;
        }

        if ($dto->isActive !== null) {
            $updateData['is_active'] = $dto->isActive;
        }

        if ($dto->isPublic !== null) {
            $updateData['is_public'] = $dto->isPublic;
        }

        return $this->plans->update($plan, $updateData);
    }
}
```

**File**: `app/Actions/Plan/PlanDeactivateAction.php`

```php
<?php

declare(strict_types=1);

namespace App\Actions\Plan;

use App\Contracts\Repositories\PlanRepository;
use App\Exceptions\PlanException;
use App\Models\Plan;

final class PlanDeactivateAction
{
    public function __construct(
        private readonly PlanRepository $plans,
    ) {}

    public function handle(Plan $plan): Plan
    {
        // Cannot deactivate the FREE plan
        if ($plan->isFree()) {
            throw PlanException::cannotDeactivateFreePlan();
        }

        // Cannot deactivate plan with active subscribers
        if ($plan->hasActiveSubscribers()) {
            throw PlanException::hasActiveSubscribers($plan);
        }

        return $this->plans->update($plan, [
            'is_active' => false,
        ]);
    }
}
```

### Plan Exception

**File**: `app/Exceptions/PlanException.php`

```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Plan;
use Exception;

final class PlanException extends Exception
{
    public static function slugAlreadyExists(string $slug): self
    {
        return new self("A plan with slug '{$slug}' already exists.");
    }

    public static function cannotDeactivateFreePlan(): self
    {
        return new self('The FREE plan cannot be deactivated.');
    }

    public static function hasActiveSubscribers(Plan $plan): self
    {
        return new self("Cannot deactivate plan '{$plan->name}' because it has active subscribers.");
    }

    public static function notFound(int $id): self
    {
        return new self("Plan with ID {$id} not found.");
    }
}
```

### Plan Controller

**File**: `app/Http/Controllers/PlanController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Repositories\PlanRepository;
use App\Http\Resources\PlanResource;
use Illuminate\Http\JsonResponse;

final class PlanController extends Controller
{
    public function __construct(
        private readonly PlanRepository $plans,
    ) {}

    /**
     * Get all public plans for pricing page.
     */
    public function index(): JsonResponse
    {
        $plans = $this->plans->getPublic();

        return response()->json([
            'plans' => PlanResource::collection($plans),
        ]);
    }

    /**
     * Get a specific plan.
     */
    public function show(int $id): JsonResponse
    {
        $plan = $this->plans->findById($id);

        if (!$plan || !$plan->is_active) {
            return response()->json([
                'error' => 'plan_not_found',
                'message' => 'Plan not found.',
            ], 404);
        }

        return response()->json([
            'plan' => new PlanResource($plan),
        ]);
    }

    /**
     * Compare current plan with another.
     */
    public function compare(int $currentPlanId, int $targetPlanId): JsonResponse
    {
        $currentPlan = $this->plans->findById($currentPlanId);
        $targetPlan = $this->plans->findById($targetPlanId);

        if (!$currentPlan || !$targetPlan) {
            return response()->json([
                'error' => 'plan_not_found',
                'message' => 'One or both plans not found.',
            ], 404);
        }

        // Build comparison of features
        $comparison = $this->buildFeatureComparison($currentPlan, $targetPlan);

        return response()->json([
            'current_plan' => new PlanResource($currentPlan),
            'target_plan' => new PlanResource($targetPlan),
            'is_upgrade' => $targetPlan->sort_order > $currentPlan->sort_order,
            'feature_comparison' => $comparison,
        ]);
    }

    /**
     * @return array<string, array{current: mixed, target: mixed, improved: bool}>
     */
    private function buildFeatureComparison($currentPlan, $targetPlan): array
    {
        $comparison = [];

        // Compare features
        $allFeatures = array_unique(array_merge(
            array_keys($currentPlan->features),
            array_keys($targetPlan->features)
        ));

        foreach ($allFeatures as $feature) {
            $currentValue = $currentPlan->features[$feature] ?? false;
            $targetValue = $targetPlan->features[$feature] ?? false;

            $comparison['features'][$feature] = [
                'current' => $currentValue,
                'target' => $targetValue,
                'improved' => $this->isImproved($currentValue, $targetValue),
            ];
        }

        // Compare limits
        $allLimits = array_unique(array_merge(
            array_keys($currentPlan->limits),
            array_keys($targetPlan->limits)
        ));

        foreach ($allLimits as $limit) {
            $currentValue = $currentPlan->limits[$limit] ?? 0;
            $targetValue = $targetPlan->limits[$limit] ?? 0;

            $comparison['limits'][$limit] = [
                'current' => $currentValue === -1 ? 'unlimited' : $currentValue,
                'target' => $targetValue === -1 ? 'unlimited' : $targetValue,
                'improved' => $targetValue === -1 || ($currentValue !== -1 && $targetValue > $currentValue),
            ];
        }

        return $comparison;
    }

    private function isImproved(mixed $current, mixed $target): bool
    {
        // Boolean comparison
        if (is_bool($current) && is_bool($target)) {
            return !$current && $target;
        }

        // String comparison (basic -> advanced)
        if (is_string($current) && is_string($target)) {
            $levels = ['none' => 0, 'basic' => 1, 'advanced' => 2, 'full' => 3];

            return ($levels[$target] ?? 0) > ($levels[$current] ?? 0);
        }

        return false;
    }
}
```

### Plan Resource

**File**: `app/Http/Resources/PlanResource.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Plan
 */
final class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'pricing' => [
                'monthly' => [
                    'amount' => $this->monthly_price,
                    'currency' => 'EUR',
                ],
                'yearly' => [
                    'amount' => $this->yearly_price,
                    'monthly_equivalent' => $this->getYearlyMonthlyEquivalent(),
                    'discount_percentage' => $this->getYearlyDiscountPercentage(),
                    'currency' => 'EUR',
                ],
            ],
            'features' => $this->features,
            'limits' => $this->formatLimits(),
            'is_recommended' => $this->slug === 'smart', // Highlight SMART as best value
        ];
    }

    /**
     * Format limits for display (replace -1 with 'unlimited').
     *
     * @return array<string, int|string>
     */
    private function formatLimits(): array
    {
        $formatted = [];

        foreach ($this->limits as $key => $value) {
            $formatted[$key] = $value === -1 ? 'unlimited' : $value;
        }

        return $formatted;
    }
}
```

## API Specification

### Public Plan Endpoints

```yaml
/api/plans:
  get:
    summary: Get all public plans for pricing page
    responses:
      200:
        description: List of plans
        content:
          application/json:
            schema:
              type: object
              properties:
                plans:
                  type: array
                  items:
                    $ref: '#/components/schemas/Plan'

/api/plans/{id}:
  get:
    summary: Get specific plan details
    parameters:
      - name: id
        in: path
        required: true
        schema:
          type: integer
    responses:
      200:
        description: Plan details
      404:
        description: Plan not found

/api/plans/compare/{currentId}/{targetId}:
  get:
    summary: Compare two plans
    parameters:
      - name: currentId
        in: path
        required: true
        schema:
          type: integer
      - name: targetId
        in: path
        required: true
        schema:
          type: integer
    responses:
      200:
        description: Plan comparison
        content:
          application/json:
            schema:
              type: object
              properties:
                current_plan:
                  $ref: '#/components/schemas/Plan'
                target_plan:
                  $ref: '#/components/schemas/Plan'
                is_upgrade:
                  type: boolean
                feature_comparison:
                  type: object
```

### Plan Schema

```yaml
components:
  schemas:
    Plan:
      type: object
      properties:
        id:
          type: integer
        name:
          type: string
        slug:
          type: string
        description:
          type: string
        pricing:
          type: object
          properties:
            monthly:
              type: object
              properties:
                amount:
                  type: number
                currency:
                  type: string
            yearly:
              type: object
              properties:
                amount:
                  type: number
                monthly_equivalent:
                  type: number
                discount_percentage:
                  type: number
                currency:
                  type: string
        features:
          type: object
        limits:
          type: object
        is_recommended:
          type: boolean
```

## Testing Strategy

### E2E Test
- `TestPlanManagement` covering list plans, compare plans, plan updates
- Verify: Plans returned correctly, comparison logic accurate

### Manual Verification
- Access pricing page API
- Compare FREE vs SMART plan
- Verify discount calculations

## Implementation Steps

1. **Medium** - Create Plan model with PHPDoc annotations and helper methods
2. **Small** - Create CreatePlanDTO and UpdatePlanDTO
3. **Small** - Create PlanException class
4. **Medium** - Create PlanCreateAction
5. **Small** - Create PlanUpdateAction
6. **Small** - Create PlanDeactivateAction
7. **Medium** - Create PlanController with public endpoints
8. **Medium** - Create PlanResource with formatted output
9. **Small** - Add plan routes to api.php
10. **Small** - Create PlanFactory for testing
11. **Medium** - Write unit tests for Plan model methods
12. **Medium** - Write feature tests for API endpoints
13. **Small** - Run Pint and verify code style

## Cross-Task Dependencies

- **Depends on**: `database_schema.md`, `backend_stripe_integration.md`
- **Blocks**: `admin_plan_management.md`, `frontend_plan_selection.md`
- **Parallel work**: Can work alongside `backend_subscription_service.md`
