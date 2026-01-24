# Admin Panel for Plan Management

**PRD Source**: `prds/2026-01-subscription-pricing-system.md`
**Category**: Backend/Admin
**Complexity**: Medium
**Dependencies**: `backend_plan_management.md`, `backend_subscription_service.md`
**Status**: Not Started

## Technical Overview

**Summary**: Implement admin-only endpoints and UI for managing subscription plans including creating, updating, activating/deactivating plans, and viewing subscriber statistics per PRD REQ-01.

**Architecture Impact**: Adds admin-protected routes and controller. Creates admin dashboard for plan management. Integrates with existing admin authentication.

**Risk Assessment**:
- **Medium**: Admin authentication and authorization
- **Low**: Plan modification impact warnings
- **Low**: Historical data preservation for reporting

## Component Architecture

### Admin Plan Controller

**File**: `app/Http/Controllers/Admin/AdminPlanController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Plan\PlanCreateAction;
use App\Actions\Plan\PlanDeactivateAction;
use App\Actions\Plan\PlanUpdateAction;
use App\Contracts\Repositories\PlanRepository;
use App\DTOs\Plan\CreatePlanDTO;
use App\DTOs\Plan\UpdatePlanDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreatePlanRequest;
use App\Http\Requests\Admin\UpdatePlanRequest;
use App\Http\Resources\Admin\AdminPlanResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

final class AdminPlanController extends Controller
{
    public function __construct(
        private readonly PlanRepository $plans,
        private readonly PlanCreateAction $createAction,
        private readonly PlanUpdateAction $updateAction,
        private readonly PlanDeactivateAction $deactivateAction,
    ) {}

    /**
     * List all plans (including inactive).
     */
    public function index(): JsonResponse
    {
        $plans = $this->plans->getActive();

        // Include inactive plans for admin
        $allPlans = Plan::orderBy('sort_order')->get();

        return response()->json([
            'plans' => AdminPlanResource::collection($allPlans),
        ]);
    }

    /**
     * Get plan details with subscriber count.
     */
    public function show(int $id): JsonResponse
    {
        $plan = $this->plans->findById($id);

        if (!$plan) {
            return response()->json(['error' => 'Plan not found'], 404);
        }

        return response()->json([
            'plan' => new AdminPlanResource($plan),
        ]);
    }

    /**
     * Create a new plan.
     */
    public function store(CreatePlanRequest $request): JsonResponse
    {
        $dto = new CreatePlanDTO(
            name: $request->getName(),
            slug: $request->getSlug(),
            description: $request->getDescription(),
            monthlyPrice: $request->getMonthlyPrice(),
            yearlyPrice: $request->getYearlyPrice(),
            features: $request->getFeatures(),
            limits: $request->getLimits(),
            sortOrder: $request->getSortOrder(),
            isActive: $request->getIsActive(),
            isPublic: $request->getIsPublic(),
        );

        $plan = $this->createAction->handle($dto);

        return response()->json([
            'plan' => new AdminPlanResource($plan),
        ], 201);
    }

    /**
     * Update a plan.
     */
    public function update(UpdatePlanRequest $request, int $id): JsonResponse
    {
        $plan = $this->plans->findById($id);

        if (!$plan) {
            return response()->json(['error' => 'Plan not found'], 404);
        }

        $dto = new UpdatePlanDTO(
            description: $request->getDescription(),
            monthlyPrice: $request->getMonthlyPrice(),
            yearlyPrice: $request->getYearlyPrice(),
            stripeMontlyPriceId: $request->getStripeMonthlyPriceId(),
            stripeYearlyPriceId: $request->getStripeYearlyPriceId(),
            features: $request->getFeatures(),
            limits: $request->getLimits(),
            sortOrder: $request->getSortOrder(),
            isActive: $request->getIsActive(),
            isPublic: $request->getIsPublic(),
        );

        $plan = $this->updateAction->handle($plan, $dto);

        return response()->json([
            'plan' => new AdminPlanResource($plan),
        ]);
    }

    /**
     * Deactivate a plan.
     */
    public function deactivate(int $id): JsonResponse
    {
        $plan = $this->plans->findById($id);

        if (!$plan) {
            return response()->json(['error' => 'Plan not found'], 404);
        }

        try {
            $plan = $this->deactivateAction->handle($plan);

            return response()->json([
                'plan' => new AdminPlanResource($plan),
            ]);
        } catch (\App\Exceptions\PlanException $e) {
            return response()->json([
                'error' => 'deactivation_failed',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get subscription statistics per plan.
     */
    public function statistics(): JsonResponse
    {
        $stats = Plan::withCount([
            'subscriptions as active_subscribers' => static function ($query): void {
                $query->whereIn('stripe_status', ['active', 'trialing']);
            },
            'subscriptions as total_subscribers',
        ])
            ->orderBy('sort_order')
            ->get()
            ->map(static fn (Plan $plan): array => [
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'plan_slug' => $plan->slug,
                'active_subscribers' => $plan->active_subscribers,
                'total_subscribers' => $plan->total_subscribers,
                'monthly_price' => $plan->monthly_price,
                'yearly_price' => $plan->yearly_price,
                'mrr_contribution' => $plan->active_subscribers * $plan->monthly_price,
            ]);

        $totals = [
            'total_active_subscribers' => $stats->sum('active_subscribers'),
            'total_mrr' => $stats->sum('mrr_contribution'),
            'total_arr' => $stats->sum('mrr_contribution') * 12,
        ];

        return response()->json([
            'plans' => $stats,
            'totals' => $totals,
        ]);
    }
}
```

### Admin Plan Resource

**File**: `app/Http/Resources/Admin/AdminPlanResource.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Plan
 */
final class AdminPlanResource extends JsonResource
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
            'monthly_price' => $this->monthly_price,
            'yearly_price' => $this->yearly_price,
            'yearly_discount_percentage' => $this->getYearlyDiscountPercentage(),
            'stripe_monthly_price_id' => $this->stripe_monthly_price_id,
            'stripe_yearly_price_id' => $this->stripe_yearly_price_id,
            'features' => $this->features,
            'limits' => $this->limits,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'is_public' => $this->is_public,
            'is_free' => $this->isFree(),
            'has_active_subscribers' => $this->hasActiveSubscribers(),
            'subscriber_count' => $this->when(
                $this->relationLoaded('subscriptions'),
                fn () => $this->subscriptions->where('stripe_status', 'active')->count()
            ),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
```

### Form Requests

**File**: `app/Http/Requests/Admin/CreatePlanRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class CreatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:50',
            'slug' => 'required|string|max:50|unique:plans,slug',
            'description' => 'nullable|string|max:500',
            'monthly_price' => 'required|numeric|min:0',
            'yearly_price' => 'required|numeric|min:0',
            'features' => 'required|array',
            'limits' => 'required|array',
            'limits.reservations_per_month' => 'required|integer',
            'limits.users' => 'required|integer',
            'limits.locations' => 'required|integer',
            'limits.services' => 'required|integer',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    public function getName(): string
    {
        return $this->validated('name');
    }

    public function getSlug(): string
    {
        return $this->validated('slug');
    }

    public function getDescription(): ?string
    {
        return $this->validated('description');
    }

    public function getMonthlyPrice(): float
    {
        return (float) $this->validated('monthly_price');
    }

    public function getYearlyPrice(): float
    {
        return (float) $this->validated('yearly_price');
    }

    /**
     * @return array<string, mixed>
     */
    public function getFeatures(): array
    {
        return $this->validated('features');
    }

    /**
     * @return array<string, int>
     */
    public function getLimits(): array
    {
        return $this->validated('limits');
    }

    public function getSortOrder(): int
    {
        return (int) ($this->validated('sort_order') ?? 0);
    }

    public function getIsActive(): bool
    {
        return (bool) ($this->validated('is_active') ?? true);
    }

    public function getIsPublic(): bool
    {
        return (bool) ($this->validated('is_public') ?? true);
    }
}
```

**File**: `app/Http/Requests/Admin/UpdatePlanRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'description' => 'nullable|string|max:500',
            'monthly_price' => 'nullable|numeric|min:0',
            'yearly_price' => 'nullable|numeric|min:0',
            'stripe_monthly_price_id' => 'nullable|string|max:255',
            'stripe_yearly_price_id' => 'nullable|string|max:255',
            'features' => 'nullable|array',
            'limits' => 'nullable|array',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'is_public' => 'nullable|boolean',
        ];
    }

    public function getDescription(): ?string
    {
        return $this->validated('description');
    }

    public function getMonthlyPrice(): ?float
    {
        $price = $this->validated('monthly_price');

        return $price !== null ? (float) $price : null;
    }

    public function getYearlyPrice(): ?float
    {
        $price = $this->validated('yearly_price');

        return $price !== null ? (float) $price : null;
    }

    public function getStripeMonthlyPriceId(): ?string
    {
        return $this->validated('stripe_monthly_price_id');
    }

    public function getStripeYearlyPriceId(): ?string
    {
        return $this->validated('stripe_yearly_price_id');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getFeatures(): ?array
    {
        return $this->validated('features');
    }

    /**
     * @return array<string, int>|null
     */
    public function getLimits(): ?array
    {
        return $this->validated('limits');
    }

    public function getSortOrder(): ?int
    {
        $order = $this->validated('sort_order');

        return $order !== null ? (int) $order : null;
    }

    public function getIsActive(): ?bool
    {
        $active = $this->validated('is_active');

        return $active !== null ? (bool) $active : null;
    }

    public function getIsPublic(): ?bool
    {
        $public = $this->validated('is_public');

        return $public !== null ? (bool) $public : null;
    }
}
```

### Admin Routes

**File**: `routes/api.php` - Admin routes

```php
use App\Http\Controllers\Admin\AdminPlanController;

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'admin'])
    ->group(function () {
        // Plan management
        Route::get('/plans', [AdminPlanController::class, 'index']);
        Route::get('/plans/statistics', [AdminPlanController::class, 'statistics']);
        Route::get('/plans/{id}', [AdminPlanController::class, 'show']);
        Route::post('/plans', [AdminPlanController::class, 'store']);
        Route::put('/plans/{id}', [AdminPlanController::class, 'update']);
        Route::post('/plans/{id}/deactivate', [AdminPlanController::class, 'deactivate']);
    });
```

### Admin Middleware

**File**: `app/Http/Middleware/EnsureUserIsAdmin.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'Admin access required.',
            ], 403);
        }

        return $next($request);
    }
}
```

## API Specification

### Admin Plan Endpoints

```yaml
/api/admin/plans:
  get:
    summary: List all plans (including inactive)
    security:
      - bearerAuth: []
    responses:
      200:
        description: List of plans
      403:
        description: Admin access required

  post:
    summary: Create a new plan
    security:
      - bearerAuth: []
    requestBody:
      required: true
      content:
        application/json:
          schema:
            type: object
            required:
              - name
              - slug
              - monthly_price
              - yearly_price
              - features
              - limits
            properties:
              name:
                type: string
              slug:
                type: string
              description:
                type: string
              monthly_price:
                type: number
              yearly_price:
                type: number
              features:
                type: object
              limits:
                type: object
              sort_order:
                type: integer
              is_active:
                type: boolean
              is_public:
                type: boolean
    responses:
      201:
        description: Plan created
      400:
        description: Validation error
      403:
        description: Admin access required

/api/admin/plans/{id}:
  get:
    summary: Get plan details
    security:
      - bearerAuth: []
    responses:
      200:
        description: Plan details
      404:
        description: Plan not found

  put:
    summary: Update a plan
    security:
      - bearerAuth: []
    requestBody:
      content:
        application/json:
          schema:
            type: object
            properties:
              description:
                type: string
              monthly_price:
                type: number
              yearly_price:
                type: number
              features:
                type: object
              limits:
                type: object
    responses:
      200:
        description: Plan updated

/api/admin/plans/{id}/deactivate:
  post:
    summary: Deactivate a plan
    security:
      - bearerAuth: []
    responses:
      200:
        description: Plan deactivated
      400:
        description: Cannot deactivate (has subscribers or is FREE)

/api/admin/plans/statistics:
  get:
    summary: Get subscription statistics per plan
    security:
      - bearerAuth: []
    responses:
      200:
        description: Plan statistics
        content:
          application/json:
            schema:
              type: object
              properties:
                plans:
                  type: array
                totals:
                  type: object
                  properties:
                    total_active_subscribers:
                      type: integer
                    total_mrr:
                      type: number
                    total_arr:
                      type: number
```

## Testing Strategy

### E2E Test
- `TestAdminPlanManagement` covering CRUD operations, statistics
- Verify: Plans created/updated, deactivation blocked for plans with subscribers

### Manual Verification
- Access admin panel
- Create and update plans
- View subscription statistics

## Implementation Steps

1. **Small** - Create EnsureUserIsAdmin middleware
2. **Small** - Add `isAdmin()` method to User model
3. **Medium** - Create AdminPlanController
4. **Medium** - Create AdminPlanResource
5. **Medium** - Create CreatePlanRequest with getters
6. **Medium** - Create UpdatePlanRequest with getters
7. **Small** - Register admin middleware
8. **Small** - Add admin routes
9. **Medium** - Write feature tests for admin endpoints
10. **Small** - Run Pint and verify code style

## Cross-Task Dependencies

- **Depends on**: `backend_plan_management.md`, `backend_subscription_service.md`
- **Blocks**: None - this is a supporting admin feature
- **Parallel work**: Can work alongside frontend tasks
