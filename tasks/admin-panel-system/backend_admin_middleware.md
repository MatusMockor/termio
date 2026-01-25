# Admin Middleware & Authorization

**PRD Source**: `prds/2026-01-admin-panel-system.md` (Section 4.2)
**Category**: Backend
**Complexity**: Small
**Dependencies**: `database_schema.md`
**Status**: Not Started

## Technical Overview

**Summary**: Implement authentication and authorization middleware for admin panel routes. AdminMiddleware checks `is_admin` flag, LogAdminAction automatically logs all admin actions to audit trail.

**Architecture Impact**: Creates secure boundary around `/api/admin/*` routes. All admin endpoints protected by middleware. Automatic audit logging for compliance.

**Risk Assessment**:
- **High**: Bypass vulnerability if middleware not applied to all admin routes
- **Low**: Performance impact from audit logging (async logging mitigates)

## Middleware Components

### 1. AdminMiddleware

Verifies user has admin privileges before accessing admin routes.

**File**: `app/Http/Middleware/AdminMiddleware.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->isAdmin()) {
            return response()->json([
                'message' => 'Forbidden. Admin access required.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
```

**Logic**:
1. Check if user is authenticated
2. Check if user has `is_admin = true`
3. Return 401 if not authenticated
4. Return 403 if authenticated but not admin
5. Allow request to proceed if admin

**Security Notes**:
- Must be applied to ALL `/api/admin/*` routes
- User model `isAdmin()` method checks `is_admin` boolean field
- No role-based permissions in Phase 1 (simple yes/no admin)

---

### 2. LogAdminAction Middleware

Automatically logs admin actions to audit_logs table.

**File**: `app/Http/Middleware/LogAdminAction.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Admin\AuditLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class LogAdminAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only log successful state-changing requests
        if (!$this->shouldLog($request, $response)) {
            return $response;
        }

        $this->logAction($request, $response);

        return $response;
    }

    private function shouldLog(Request $request, Response $response): bool
    {
        // Only log POST, PATCH, DELETE (not GET)
        if (!in_array($request->method(), ['POST', 'PATCH', 'DELETE'], true)) {
            return false;
        }

        // Only log successful responses (2xx)
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return false;
        }

        return true;
    }

    private function logAction(Request $request, Response $response): void
    {
        $action = $this->extractAction($request);
        $target = $this->extractTarget($request);

        $this->auditLogService->log(
            adminId: $request->user()->id,
            action: $action,
            targetType: $target['type'] ?? 'unknown',
            targetId: $target['id'] ?? null,
            targetName: $target['name'] ?? null,
            details: $this->extractDetails($request, $response),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }

    private function extractAction(Request $request): string
    {
        $route = $request->route();
        $method = $request->method();

        if (!$route) {
            return 'unknown_action';
        }

        $routeName = $route->getName();

        // Map route names to action strings
        return match (true) {
            str_contains($routeName, 'tenants.store') => 'tenant_created',
            str_contains($routeName, 'tenants.update') => 'tenant_updated',
            str_contains($routeName, 'tenants.destroy') => 'tenant_deleted',
            str_contains($routeName, 'tenants.suspend') => 'tenant_suspended',
            str_contains($routeName, 'tenants.activate') => 'tenant_activated',
            str_contains($routeName, 'users.suspend') => 'user_suspended',
            str_contains($routeName, 'users.reset-password') => 'user_password_reset',
            str_contains($routeName, 'subscriptions.cancel') => 'subscription_cancelled',
            str_contains($routeName, 'subscriptions.extend-trial') => 'trial_extended',
            str_contains($routeName, 'settings.update') => 'settings_updated',
            str_contains($routeName, 'feature-flags.update') => 'feature_flag_updated',
            str_contains($routeName, 'impersonate') => 'impersonation_started',
            default => sprintf('%s_%s', strtolower($method), $routeName),
        };
    }

    private function extractTarget(Request $request): array
    {
        $route = $request->route();

        if (!$route) {
            return [];
        }

        // Extract target from route parameters
        $tenantId = $route->parameter('tenant');
        $userId = $route->parameter('user');
        $subscriptionId = $route->parameter('subscription');

        if ($tenantId) {
            return [
                'type' => 'tenant',
                'id' => (int) $tenantId,
                'name' => $this->getTenantName($tenantId),
            ];
        }

        if ($userId) {
            return [
                'type' => 'user',
                'id' => (int) $userId,
                'name' => $this->getUserName($userId),
            ];
        }

        if ($subscriptionId) {
            return [
                'type' => 'subscription',
                'id' => (int) $subscriptionId,
                'name' => "Subscription #{$subscriptionId}",
            ];
        }

        return [];
    }

    private function extractDetails(Request $request, Response $response): array
    {
        $details = [
            'request_data' => $request->except(['password', '_token', '_method']),
        ];

        // Add response data if JSON
        if ($response instanceof \Illuminate\Http\JsonResponse) {
            $responseData = $response->getData(true);

            if (isset($responseData['data'])) {
                $details['response_summary'] = $this->summarizeResponse($responseData['data']);
            }
        }

        return $details;
    }

    private function summarizeResponse(mixed $data): array
    {
        if (!is_array($data)) {
            return ['type' => gettype($data)];
        }

        return [
            'fields' => array_keys($data),
            'count' => count($data),
        ];
    }

    private function getTenantName(int $tenantId): ?string
    {
        try {
            $tenant = \App\Models\Tenant::find($tenantId);
            return $tenant?->name;
        } catch (\Exception) {
            return null;
        }
    }

    private function getUserName(int $userId): ?string
    {
        try {
            $user = \App\Models\User::find($userId);
            return $user ? "{$user->first_name} {$user->last_name}" : null;
        } catch (\Exception) {
            return null;
        }
    }
}
```

**Logic**:
1. Execute request handler first (response needed for logging)
2. Check if request should be logged (only POST/PATCH/DELETE with 2xx response)
3. Extract action name from route
4. Extract target entity (tenant, user, subscription)
5. Collect request/response details
6. Log to audit_logs table asynchronously
7. Return original response

**Security Notes**:
- Sensitive fields (password, tokens) excluded from audit log
- IP address and user agent captured for security auditing
- Logging failures must not block requests (fire-and-forget)

---

## Route Registration

**File**: `routes/api.php`

```php
<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\LogAdminAction;
use Illuminate\Support\Facades\Route;

// Admin routes - protected by AdminMiddleware and LogAdminAction
Route::prefix('admin')
    ->middleware(['auth:sanctum', AdminMiddleware::class, LogAdminAction::class])
    ->name('admin.')
    ->group(static function (): void {
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Tenants
        Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::get('/tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
        Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
        Route::patch('/tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
        Route::delete('/tenants/{tenant}', [TenantController::class, 'destroy'])->name('tenants.destroy');
        Route::post('/tenants/{tenant}/restore', [TenantController::class, 'restore'])->name('tenants.restore');
        Route::post('/tenants/{tenant}/suspend', [TenantController::class, 'suspend'])->name('tenants.suspend');
        Route::post('/tenants/{tenant}/activate', [TenantController::class, 'activate'])->name('tenants.activate');

        // Users
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
        Route::post('/users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

        // Subscriptions
        Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::get('/subscriptions/{subscription}', [SubscriptionController::class, 'show'])->name('subscriptions.show');
        Route::post('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
        Route::post('/subscriptions/{subscription}/extend-trial', [SubscriptionController::class, 'extendTrial'])->name('subscriptions.extend-trial');
        Route::post('/subscriptions/{subscription}/retry-payment', [SubscriptionController::class, 'retryPayment'])->name('subscriptions.retry-payment');
        Route::post('/subscriptions/{subscription}/mark-paid', [SubscriptionController::class, 'markPaid'])->name('subscriptions.mark-paid');

        // Revenue
        Route::get('/revenue/metrics', [RevenueController::class, 'metrics'])->name('revenue.metrics');
        Route::get('/revenue/chart', [RevenueController::class, 'chart'])->name('revenue.chart');
        Route::get('/revenue/breakdown', [RevenueController::class, 'breakdown'])->name('revenue.breakdown');
        Route::get('/revenue/transactions', [RevenueController::class, 'transactions'])->name('revenue.transactions');
        Route::get('/revenue/failed-payments', [RevenueController::class, 'failedPayments'])->name('revenue.failed-payments');
        Route::post('/revenue/export/transactions', [RevenueController::class, 'exportTransactions'])->name('revenue.export.transactions');
        Route::post('/revenue/export/mrr', [RevenueController::class, 'exportMrr'])->name('revenue.export.mrr');

        // Impersonation
        Route::post('/impersonate/{user}', [ImpersonationController::class, 'start'])->name('impersonate');
        Route::post('/impersonate/exit', [ImpersonationController::class, 'exit'])->name('impersonate.exit');

        // Settings
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/stripe/test', [SettingsController::class, 'testStripe'])->name('settings.stripe.test');
        Route::post('/settings/email/test', [SettingsController::class, 'testEmail'])->name('settings.email.test');

        // Feature Flags
        Route::get('/feature-flags', [FeatureFlagController::class, 'index'])->name('feature-flags.index');
        Route::patch('/feature-flags/{featureFlag}', [FeatureFlagController::class, 'update'])->name('feature-flags.update');

        // Audit Log
        Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
        Route::post('/audit-log/export', [AuditLogController::class, 'export'])->name('audit-log.export');
    });
```

**Middleware Stack**:
1. `auth:sanctum` - Laravel authentication (user must be logged in)
2. `AdminMiddleware` - Check `is_admin` flag
3. `LogAdminAction` - Auto-log state-changing actions

**Security**:
- All routes require authentication
- All routes require admin flag
- All state-changing routes logged to audit trail

---

## Middleware Registration

**File**: `app/Http/Kernel.php` or `bootstrap/app.php` (Laravel 11+)

```php
<?php

// For Laravel 11+ (bootstrap/app.php)
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\LogAdminAction;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'log.admin' => LogAdminAction::class,
        ]);
    })
    ->create();
```

---

## Acceptance Criteria

- AdminMiddleware blocks non-admin users (403 Forbidden)
- AdminMiddleware allows admin users (200 OK)
- LogAdminAction logs all POST/PATCH/DELETE requests
- LogAdminAction excludes GET requests
- LogAdminAction excludes failed requests (4xx, 5xx)
- Audit log contains admin_id, action, target, details, IP, user agent
- Sensitive fields (password) excluded from audit log
- All `/api/admin/*` routes protected by middleware
- Middleware registered in application

## Testing Requirements

### AdminMiddleware Tests

**File**: `tests/Feature/Middleware/AdminMiddlewareTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_admin_routes(): void
    {
        $response = $this->getJson('/api/admin/dashboard');

        $response->assertStatus(401);
    }

    public function test_non_admin_user_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->getJson('/api/admin/dashboard');

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Forbidden. Admin access required.']);
    }

    public function test_admin_user_can_access_admin_routes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->getJson('/api/admin/dashboard');

        $response->assertSuccessful();
    }
}
```

### LogAdminAction Tests

**File**: `tests/Feature/Middleware/LogAdminActionTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LogAdminActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_action_is_logged(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tenant = Tenant::factory()->create();

        $this->actingAs($admin)->postJson("/api/admin/tenants/{$tenant->id}/suspend");

        $this->assertDatabaseHas(AuditLog::class, [
            'admin_id' => $admin->id,
            'action' => 'tenant_suspended',
            'target_type' => 'tenant',
            'target_id' => $tenant->id,
        ]);
    }

    public function test_get_requests_are_not_logged(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->getJson('/api/admin/tenants');

        $this->assertDatabaseMissing(AuditLog::class, [
            'admin_id' => $admin->id,
        ]);
    }

    public function test_failed_requests_are_not_logged(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->postJson('/api/admin/tenants/999999/suspend');

        $this->assertDatabaseMissing(AuditLog::class, [
            'admin_id' => $admin->id,
            'action' => 'tenant_suspended',
        ]);
    }

    public function test_sensitive_fields_excluded_from_log(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->postJson('/api/admin/tenants', [
            'name' => 'Test Tenant',
            'password' => 'secret123',
        ]);

        $log = AuditLog::where('admin_id', $admin->id)->first();

        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('password', $log->details['request_data']);
    }

    public function test_audit_log_contains_ip_and_user_agent(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tenant = Tenant::factory()->create();

        $response = $this->actingAs($admin)
            ->withHeaders(['User-Agent' => 'TestBrowser/1.0'])
            ->postJson("/api/admin/tenants/{$tenant->id}/suspend");

        $log = AuditLog::where('admin_id', $admin->id)->first();

        $this->assertNotNull($log->ip_address);
        $this->assertSame('TestBrowser/1.0', $log->user_agent);
    }
}
```

## Edge Cases

1. **Audit log write failure**: Must not block admin action
   - Solution: Wrap logging in try-catch, log error but continue

2. **Concurrent admin sessions**: Same admin on multiple devices
   - Solution: Allowed, each session logged separately

3. **Impersonation during admin action**: Admin impersonating user performs action
   - Solution: Log both admin_id and impersonated user context

4. **Route parameter extraction failure**: Target entity deleted before logging
   - Solution: Graceful fallback to null target_name

5. **Large request payloads**: Audit log details exceeds JSON column size
   - Solution: Truncate request_data to 10KB max

## Error Handling

- AdminMiddleware must return clear error messages (401/403)
- LogAdminAction must catch all exceptions and not block requests
- Audit log write failures logged to application error log
- Missing target entities do not prevent logging (use null)

## Performance Considerations

- AdminMiddleware adds <1ms overhead (single boolean check)
- LogAdminAction dispatches async (no blocking)
- Audit log writes are fire-and-forget (queue recommended for high traffic)
- Route name extraction cached per request

## Security Checklist

- [ ] AdminMiddleware applied to ALL `/api/admin/*` routes
- [ ] No admin routes bypass middleware
- [ ] Sensitive fields excluded from audit log
- [ ] IP address captured for security auditing
- [ ] User agent captured for device tracking
- [ ] Audit log immutable (no updates/deletes)
- [ ] Admin flag cannot be modified via API (manual database only)
