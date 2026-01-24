# Feature Gating System

**PRD Source**: `prds/2026-01-subscription-pricing-system.md`
**Category**: Backend
**Complexity**: Medium
**Dependencies**: `backend_subscription_service.md`, `backend_usage_limit_enforcement.md`
**Status**: Not Started

## Technical Overview

**Summary**: Implement feature access control based on subscription tier. Features are gated at API level with clear error responses and upgrade prompts. Provides centralized feature checking via SubscriptionService per PRD REQ-12.

**Architecture Impact**: Adds feature gate middleware and helper methods. Modifies feature-specific controllers to check access. Frontend receives consistent 403 responses with feature requirements.

**Risk Assessment**:
- **Low**: Feature checks are read-only operations
- **Medium**: Must ensure all features are correctly gated
- **Low**: Graceful degradation for edge cases

## Component Architecture

### Feature Gate Middleware

**File**: `app/Http/Middleware/CheckFeatureAccess.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Services\SubscriptionServiceContract;
use App\Services\Tenant\TenantContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CheckFeatureAccess
{
    public function __construct(
        private readonly SubscriptionServiceContract $subscriptionService,
        private readonly TenantContextService $tenantContext,
    ) {}

    /**
     * @param  string  $feature  The feature key to check
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = $this->tenantContext->getTenant();

        if (!$tenant) {
            return $next($request);
        }

        if (!$this->subscriptionService->hasFeature($tenant, $feature)) {
            $currentPlan = $this->subscriptionService->getCurrentPlan($tenant);
            $requiredPlan = $this->getMinimumPlanForFeature($feature);

            return response()->json([
                'error' => 'feature_not_available',
                'message' => "This feature requires {$requiredPlan} plan or higher.",
                'feature' => $feature,
                'current_plan' => $currentPlan->slug,
                'required_plan' => strtolower($requiredPlan),
                'upgrade_url' => route('subscription.plans'),
            ], 403);
        }

        return $next($request);
    }

    private function getMinimumPlanForFeature(string $feature): string
    {
        // Map features to minimum required plan
        $featurePlanMap = [
            // EASY tier features
            'custom_logo' => 'EASY',
            'custom_colors' => 'EASY',
            'custom_booking_url' => 'EASY',
            'google_calendar_sync' => 'EASY',
            'payment_gateway' => 'EASY',
            'multi_language' => 'EASY',
            'recurring_appointments' => 'EASY',

            // SMART tier features
            'custom_domain' => 'SMART',
            'api_access' => 'SMART',
            'zapier_integration' => 'SMART',
            'staff_permissions' => 'SMART',
            'client_segmentation' => 'SMART',
            'waitlist_management' => 'SMART',
            'gift_vouchers' => 'SMART',
            'sms_reminders' => 'SMART',

            // PREMIUM tier features
            'white_label' => 'PREMIUM',
        ];

        return $featurePlanMap[$feature] ?? 'PREMIUM';
    }
}
```

### Feature Gate Helper

**File**: `app/Helpers/FeatureGate.php`

```php
<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Contracts\Services\SubscriptionServiceContract;
use App\Models\Tenant;
use Illuminate\Support\Facades\App;

final class FeatureGate
{
    /**
     * Check if tenant has access to a feature.
     */
    public static function check(Tenant $tenant, string $feature): bool
    {
        $service = App::make(SubscriptionServiceContract::class);

        return $service->hasFeature($tenant, $feature);
    }

    /**
     * Check if tenant has access to a feature, throw exception if not.
     *
     * @throws \App\Exceptions\FeatureNotAvailableException
     */
    public static function authorize(Tenant $tenant, string $feature): void
    {
        if (!self::check($tenant, $feature)) {
            throw new \App\Exceptions\FeatureNotAvailableException($feature);
        }
    }

    /**
     * Get the feature value (for tiered features).
     */
    public static function getValue(Tenant $tenant, string $feature): mixed
    {
        $service = App::make(SubscriptionServiceContract::class);

        return $service->getFeatureValue($tenant, $feature);
    }
}
```

### Feature Not Available Exception

**File**: `app/Exceptions/FeatureNotAvailableException.php`

```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

final class FeatureNotAvailableException extends Exception
{
    public function __construct(
        private readonly string $feature,
    ) {
        parent::__construct("Feature '{$feature}' is not available on your current plan.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => 'feature_not_available',
            'message' => $this->getMessage(),
            'feature' => $this->feature,
        ], 403);
    }
}
```

### Feature List Service

**File**: `app/Services/Subscription/FeatureListService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Contracts\Services\SubscriptionServiceContract;
use App\Models\Tenant;

final class FeatureListService
{
    public function __construct(
        private readonly SubscriptionServiceContract $subscriptionService,
    ) {}

    /**
     * Get all features with their availability status for a tenant.
     *
     * @return array<string, array{available: bool, value: mixed, required_plan: string}>
     */
    public function getFeatureStatus(Tenant $tenant): array
    {
        $features = $this->getAllFeatures();
        $result = [];

        foreach ($features as $feature => $requiredPlan) {
            $result[$feature] = [
                'available' => $this->subscriptionService->hasFeature($tenant, $feature),
                'value' => $this->subscriptionService->getFeatureValue($tenant, $feature),
                'required_plan' => $requiredPlan,
            ];
        }

        return $result;
    }

    /**
     * Get all available features grouped by category.
     *
     * @return array<string, array<string, string>>
     */
    public function getFeaturesGrouped(): array
    {
        return [
            'core_booking' => [
                'online_booking_widget' => 'FREE',
                'manual_reservations' => 'FREE',
                'calendar_view' => 'FREE', // Value: basic|advanced
                'client_database' => 'FREE', // Value: basic|advanced
            ],
            'notifications' => [
                'email_confirmations' => 'FREE',
                'email_reminders' => 'FREE',
                'sms_reminders' => 'SMART',
            ],
            'customization' => [
                'custom_logo' => 'EASY',
                'custom_colors' => 'EASY',
                'custom_booking_url' => 'EASY',
                'custom_domain' => 'SMART',
                'white_label' => 'PREMIUM',
            ],
            'integrations' => [
                'google_calendar_sync' => 'EASY',
                'payment_gateway' => 'EASY',
                'api_access' => 'SMART',
                'zapier_integration' => 'SMART',
            ],
            'advanced_features' => [
                'multi_language' => 'EASY',
                'staff_permissions' => 'SMART',
                'client_segmentation' => 'SMART',
                'waitlist_management' => 'SMART',
                'recurring_appointments' => 'EASY',
                'gift_vouchers' => 'SMART',
                'reports_statistics' => 'FREE', // Value: basic|full
            ],
        ];
    }

    /**
     * Get flat list of all features with required plans.
     *
     * @return array<string, string>
     */
    private function getAllFeatures(): array
    {
        $grouped = $this->getFeaturesGrouped();
        $flat = [];

        foreach ($grouped as $features) {
            $flat = array_merge($flat, $features);
        }

        return $flat;
    }
}
```

### Kernel Registration

**File**: `bootstrap/app.php` - Add feature middleware alias

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        // ... existing aliases
        'feature' => \App\Http\Middleware\CheckFeatureAccess::class,
    ]);
})
```

### Route Examples

```php
// routes/api.php

// Google Calendar sync - requires EASY+
Route::prefix('integrations/google-calendar')
    ->middleware('feature:google_calendar_sync')
    ->group(function () {
        Route::get('/connect', [GoogleCalendarController::class, 'connect']);
        Route::post('/disconnect', [GoogleCalendarController::class, 'disconnect']);
        Route::get('/sync', [GoogleCalendarController::class, 'sync']);
    });

// API access - requires SMART+
Route::prefix('api-tokens')
    ->middleware('feature:api_access')
    ->group(function () {
        Route::get('/', [ApiTokenController::class, 'index']);
        Route::post('/', [ApiTokenController::class, 'store']);
        Route::delete('/{token}', [ApiTokenController::class, 'destroy']);
    });

// Custom domain - requires SMART+
Route::prefix('settings/domain')
    ->middleware('feature:custom_domain')
    ->group(function () {
        Route::get('/', [DomainSettingsController::class, 'show']);
        Route::post('/', [DomainSettingsController::class, 'update']);
    });

// White label - requires PREMIUM
Route::prefix('settings/white-label')
    ->middleware('feature:white_label')
    ->group(function () {
        Route::get('/', [WhiteLabelController::class, 'show']);
        Route::post('/', [WhiteLabelController::class, 'update']);
    });
```

### Feature Status Controller

**File**: `app/Http/Controllers/SubscriptionFeatureController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\FeatureStatusResource;
use App\Services\Subscription\FeatureListService;
use App\Services\Tenant\TenantContextService;
use Illuminate\Http\JsonResponse;

final class SubscriptionFeatureController extends Controller
{
    public function __construct(
        private readonly FeatureListService $featureListService,
        private readonly TenantContextService $tenantContext,
    ) {}

    /**
     * Get all features with availability status for current tenant.
     */
    public function index(): JsonResponse
    {
        $tenant = $this->tenantContext->getTenant();
        $features = $this->featureListService->getFeatureStatus($tenant);

        return response()->json([
            'features' => $features,
        ]);
    }

    /**
     * Check if a specific feature is available.
     */
    public function check(string $feature): JsonResponse
    {
        $tenant = $this->tenantContext->getTenant();
        $features = $this->featureListService->getFeatureStatus($tenant);

        if (!isset($features[$feature])) {
            return response()->json([
                'error' => 'unknown_feature',
                'message' => "Feature '{$feature}' is not recognized.",
            ], 400);
        }

        return response()->json($features[$feature]);
    }
}
```

## API Specification

### Feature Status Endpoints

```yaml
/api/subscription/features:
  get:
    summary: Get all features with availability status
    security:
      - bearerAuth: []
    responses:
      200:
        description: Feature status map
        content:
          application/json:
            schema:
              type: object
              properties:
                features:
                  type: object
                  additionalProperties:
                    type: object
                    properties:
                      available:
                        type: boolean
                      value:
                        oneOf:
                          - type: boolean
                          - type: string
                      required_plan:
                        type: string

/api/subscription/features/{feature}:
  get:
    summary: Check specific feature availability
    security:
      - bearerAuth: []
    parameters:
      - name: feature
        in: path
        required: true
        schema:
          type: string
    responses:
      200:
        description: Feature status
        content:
          application/json:
            schema:
              type: object
              properties:
                available:
                  type: boolean
                value:
                  oneOf:
                    - type: boolean
                    - type: string
                required_plan:
                  type: string
      400:
        description: Unknown feature
```

### Feature-Gated Endpoint Response (403)

```yaml
response:
  description: Feature not available
  content:
    application/json:
      schema:
        type: object
        properties:
          error:
            type: string
            example: feature_not_available
          message:
            type: string
            example: This feature requires SMART plan or higher.
          feature:
            type: string
            example: api_access
          current_plan:
            type: string
            example: easy
          required_plan:
            type: string
            example: smart
          upgrade_url:
            type: string
            example: /subscription/plans
```

## Testing Strategy

### E2E Test
- `TestFeatureGating` covering FREE user accessing SMART feature, upgrade flow
- Verify: 403 response with correct metadata, feature works after upgrade

### Manual Verification
- Access gated endpoint on FREE plan
- Verify error message and upgrade URL
- Upgrade and verify access granted

## Implementation Steps

1. **Medium** - Create CheckFeatureAccess middleware with feature map
2. **Small** - Create FeatureGate helper class
3. **Small** - Create FeatureNotAvailableException
4. **Medium** - Create FeatureListService with grouped features
5. **Small** - Register middleware alias in bootstrap/app.php
6. **Medium** - Create SubscriptionFeatureController
7. **Medium** - Apply feature middleware to existing routes
8. **Small** - Add feature routes to api.php
9. **Medium** - Write unit tests for feature checking
10. **Medium** - Write feature tests for gated endpoints
11. **Small** - Run Pint and verify code style

## Feature to Route Mapping

| Feature | Routes | Minimum Plan |
|---------|--------|--------------|
| `google_calendar_sync` | `/integrations/google-calendar/*` | EASY |
| `payment_gateway` | `/settings/payments/*` | EASY |
| `custom_domain` | `/settings/domain/*` | SMART |
| `api_access` | `/api-tokens/*` | SMART |
| `white_label` | `/settings/white-label/*` | PREMIUM |
| `staff_permissions` | `/staff/*/permissions` | SMART |
| `client_segmentation` | `/clients/segments/*` | SMART |
| `gift_vouchers` | `/vouchers/*` | SMART |

## Cross-Task Dependencies

- **Depends on**: `backend_subscription_service.md`, `backend_usage_limit_enforcement.md`
- **Blocks**: `frontend_subscription_dashboard.md` (needs feature status API)
- **Parallel work**: Can work alongside `backend_billing_invoicing.md`
