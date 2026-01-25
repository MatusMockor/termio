# Audit Logging System

**PRD Source**: `prds/2026-01-admin-panel-system.md` (REQ-14)
**Category**: Backend
**Complexity**: Medium
**Dependencies**: `database_schema.md`, `backend_admin_middleware.md`
**Status**: Not Started

## Technical Overview

**Summary**: Implement comprehensive audit logging system for all admin actions. Includes AuditLog model, AuditLogService for logging operations, AuditLogRepository for queries, AuditLogController for viewing/exporting logs, and automated log rotation job.

**Architecture Impact**: Creates centralized audit trail for compliance, security, and accountability. All admin actions automatically logged via LogAdminAction middleware. Supports GDPR, SOC 2, and security audit requirements.

**Risk Assessment**:
- **Medium**: Log table growth can impact database size and query performance
- **Low**: Logging failures must not block admin operations
- **Low**: Sensitive data exposure in log details

## Service Layer

### AuditLogService

Central service for creating and managing audit log entries.

**File**: `app/Services/Admin/AuditLogService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Contracts\Repositories\AuditLogRepository;
use Illuminate\Support\Facades\Log;

final class AuditLogService
{
    public function __construct(
        private readonly AuditLogRepository $repository,
    ) {}

    public function log(
        int $adminId,
        string $action,
        string $targetType,
        ?int $targetId = null,
        ?string $targetName = null,
        ?array $details = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        try {
            $this->repository->create([
                'admin_id' => $adminId,
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'target_name' => $targetName,
                'details' => $this->sanitizeDetails($details),
                'ip_address' => $ipAddress,
                'user_agent' => $this->truncateUserAgent($userAgent),
            ]);
        } catch (\Exception $e) {
            // Log failure must not block admin operations
            Log::error('Audit log write failed', [
                'admin_id' => $adminId,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function logTenantAction(
        int $adminId,
        string $action,
        int $tenantId,
        string $tenantName,
        array $details = [],
    ): void {
        $this->log(
            adminId: $adminId,
            action: $action,
            targetType: 'tenant',
            targetId: $tenantId,
            targetName: $tenantName,
            details: $details,
        );
    }

    public function logUserAction(
        int $adminId,
        string $action,
        int $userId,
        string $userName,
        array $details = [],
    ): void {
        $this->log(
            adminId: $adminId,
            action: $action,
            targetType: 'user',
            targetId: $userId,
            targetName: $userName,
            details: $details,
        );
    }

    public function logSubscriptionAction(
        int $adminId,
        string $action,
        int $subscriptionId,
        array $details = [],
    ): void {
        $this->log(
            adminId: $adminId,
            action: $action,
            targetType: 'subscription',
            targetId: $subscriptionId,
            targetName: "Subscription #{$subscriptionId}",
            details: $details,
        );
    }

    public function logSettingsChange(
        int $adminId,
        string $settingKey,
        mixed $oldValue,
        mixed $newValue,
    ): void {
        $this->log(
            adminId: $adminId,
            action: 'setting_updated',
            targetType: 'platform_setting',
            targetId: null,
            targetName: $settingKey,
            details: [
                'key' => $settingKey,
                'old_value' => $this->maskSensitiveValue($settingKey, $oldValue),
                'new_value' => $this->maskSensitiveValue($settingKey, $newValue),
            ],
        );
    }

    private function sanitizeDetails(?array $details): ?array
    {
        if (!$details) {
            return null;
        }

        // Remove sensitive fields
        $sensitiveFields = ['password', 'token', 'secret', 'api_key', 'stripe_key'];

        foreach ($sensitiveFields as $field) {
            if (isset($details[$field])) {
                $details[$field] = '[REDACTED]';
            }
        }

        // Recursively sanitize nested arrays
        foreach ($details as $key => $value) {
            if (is_array($value)) {
                $details[$key] = $this->sanitizeDetails($value);
            }
        }

        // Truncate if too large (max 10KB JSON)
        $json = json_encode($details, JSON_THROW_ON_ERROR);

        if (strlen($json) > 10240) {
            $details = [
                'truncated' => true,
                'original_size' => strlen($json),
                'summary' => 'Details truncated due to size',
            ];
        }

        return $details;
    }

    private function maskSensitiveValue(string $key, mixed $value): mixed
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'api_key', 'stripe'];

        foreach ($sensitiveKeys as $sensitiveKey) {
            if (str_contains(strtolower($key), $sensitiveKey)) {
                return '[REDACTED]';
            }
        }

        return $value;
    }

    private function truncateUserAgent(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        return strlen($userAgent) > 500 ? substr($userAgent, 0, 500) : $userAgent;
    }
}
```

---

## Repository Layer

### AuditLogRepository Contract

**File**: `app/Contracts/Repositories/AuditLogRepository.php`

```php
<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AuditLogRepository
{
    public function create(array $data): AuditLog;

    public function findById(int $id): ?AuditLog;

    public function paginate(
        ?int $adminId = null,
        ?string $action = null,
        ?string $targetType = null,
        ?\DateTimeInterface $dateFrom = null,
        ?\DateTimeInterface $dateTo = null,
        int $perPage = 50,
    ): LengthAwarePaginator;

    public function getByAdmin(int $adminId, int $limit = 100): \Illuminate\Support\Collection;

    public function getByTarget(string $targetType, int $targetId): \Illuminate\Support\Collection;

    public function deleteOlderThan(\DateTimeInterface $date): int;
}
```

### Eloquent Repository Implementation

**File**: `app/Repositories/Admin/EloquentAuditLogRepository.php`

```php
<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use App\Contracts\Repositories\AuditLogRepository;
use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class EloquentAuditLogRepository implements AuditLogRepository
{
    public function create(array $data): AuditLog
    {
        return AuditLog::create($data);
    }

    public function findById(int $id): ?AuditLog
    {
        return AuditLog::find($id);
    }

    public function paginate(
        ?int $adminId = null,
        ?string $action = null,
        ?string $targetType = null,
        ?\DateTimeInterface $dateFrom = null,
        ?\DateTimeInterface $dateTo = null,
        int $perPage = 50,
    ): LengthAwarePaginator {
        $query = AuditLog::query()->with('admin');

        if ($adminId) {
            $query->where('admin_id', $adminId);
        }

        if ($action) {
            $query->where('action', $action);
        }

        if ($targetType) {
            $query->where('target_type', $targetType);
        }

        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo);
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function getByAdmin(int $adminId, int $limit = 100): Collection
    {
        return AuditLog::where('admin_id', $adminId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function getByTarget(string $targetType, int $targetId): Collection
    {
        return AuditLog::where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function deleteOlderThan(\DateTimeInterface $date): int
    {
        return AuditLog::where('created_at', '<', $date)->delete();
    }
}
```

---

## Controller

**File**: `app/Http/Controllers/Admin/AuditLogController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Contracts\Repositories\AuditLogRepository;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AuditLogResource;
use App\Jobs\Admin\ExportAuditLogJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogRepository $repository,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $logs = $this->repository->paginate(
            adminId: $request->integer('admin_id'),
            action: $request->string('action')->toString() ?: null,
            targetType: $request->string('target_type')->toString() ?: null,
            dateFrom: $request->date('date_from'),
            dateTo: $request->date('date_to'),
            perPage: $request->integer('per_page', 50),
        );

        return AuditLogResource::collection($logs);
    }

    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'admin_id' => 'nullable|integer|exists:users,id',
            'action' => 'nullable|string|max:100',
            'target_type' => 'nullable|string|max:50',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        ExportAuditLogJob::dispatch(
            adminId: $request->user()->id,
            filters: $request->only(['admin_id', 'action', 'target_type', 'date_from', 'date_to']),
        );

        return response()->json([
            'message' => 'Audit log export started. You will receive an email when complete.',
        ], Response::HTTP_ACCEPTED);
    }
}
```

---

## API Resource

**File**: `app/Http/Resources/Admin/AuditLogResource.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'admin' => [
                'id' => $this->admin->id,
                'name' => "{$this->admin->first_name} {$this->admin->last_name}",
                'email' => $this->admin->email,
            ],
            'action' => $this->action,
            'target' => [
                'type' => $this->target_type,
                'id' => $this->target_id,
                'name' => $this->target_name,
            ],
            'details' => $this->details,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
```

---

## Background Jobs

### Export Audit Log Job

**File**: `app/Jobs/Admin/ExportAuditLogJob.php`

```php
<?php

declare(strict_types=1);

namespace App\Jobs\Admin;

use App\Contracts\Repositories\AuditLogRepository;
use App\Mail\Admin\AuditLogExportReady;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use League\Csv\Writer;

final class ExportAuditLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(
        private readonly int $adminId,
        private readonly array $filters,
    ) {}

    public function handle(AuditLogRepository $repository): void
    {
        $logs = $repository->paginate(
            adminId: $this->filters['admin_id'] ?? null,
            action: $this->filters['action'] ?? null,
            targetType: $this->filters['target_type'] ?? null,
            dateFrom: isset($this->filters['date_from']) ? new \DateTime($this->filters['date_from']) : null,
            dateTo: isset($this->filters['date_to']) ? new \DateTime($this->filters['date_to']) : null,
            perPage: 10000, // Export max 10K rows
        );

        $csv = Writer::createFromString();

        // Header row
        $csv->insertOne([
            'Timestamp',
            'Admin Name',
            'Admin Email',
            'Action',
            'Target Type',
            'Target ID',
            'Target Name',
            'IP Address',
            'User Agent',
        ]);

        // Data rows
        foreach ($logs as $log) {
            $csv->insertOne([
                $log->created_at->toIso8601String(),
                "{$log->admin->first_name} {$log->admin->last_name}",
                $log->admin->email,
                $log->action,
                $log->target_type,
                $log->target_id,
                $log->target_name,
                $log->ip_address,
                $log->user_agent,
            ]);
        }

        // Store file
        $filename = sprintf(
            'audit-log-export-%s-%s.csv',
            now()->format('Y-m-d'),
            uniqid(),
        );

        Storage::disk('local')->put("exports/{$filename}", $csv->toString());

        // Send email to admin
        $admin = User::find($this->adminId);

        if ($admin) {
            Mail::to($admin)->send(new AuditLogExportReady($filename));
        }
    }
}
```

### Archive Audit Logs Job

**File**: `app/Jobs/Admin/ArchiveAuditLogsJob.php`

```php
<?php

declare(strict_types=1);

namespace App\Jobs\Admin;

use App\Contracts\Repositories\AuditLogRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class ArchiveAuditLogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function handle(AuditLogRepository $repository): void
    {
        $retentionYears = config('admin.audit_log_retention_years', 2);
        $cutoffDate = now()->subYears($retentionYears);

        $deletedCount = $repository->deleteOlderThan($cutoffDate);

        Log::info('Audit logs archived', [
            'retention_years' => $retentionYears,
            'cutoff_date' => $cutoffDate->toDateString(),
            'deleted_count' => $deletedCount,
        ]);
    }
}
```

---

## Service Provider Registration

**File**: `app/Providers/AppServiceProvider.php`

```php
<?php

// Add to boot() method
$this->app->bind(
    \App\Contracts\Repositories\AuditLogRepository::class,
    \App\Repositories\Admin\EloquentAuditLogRepository::class,
);
```

---

## Configuration

**File**: `config/admin.php` (create if not exists)

```php
<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Audit Log Retention
    |--------------------------------------------------------------------------
    |
    | Number of years to retain audit logs before archival/deletion.
    | Default: 2 years for compliance (SOC 2, GDPR)
    |
    */
    'audit_log_retention_years' => env('AUDIT_LOG_RETENTION_YEARS', 2),

    /*
    |--------------------------------------------------------------------------
    | Session Timeout
    |--------------------------------------------------------------------------
    |
    | Admin session timeout in minutes.
    | Default: 240 minutes (4 hours)
    |
    */
    'session_timeout_minutes' => env('ADMIN_SESSION_TIMEOUT', 240),

    /*
    |--------------------------------------------------------------------------
    | Impersonation Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum impersonation session duration in minutes.
    | Default: 60 minutes (1 hour)
    |
    */
    'impersonation_timeout_minutes' => env('ADMIN_IMPERSONATION_TIMEOUT', 60),
];
```

---

## Acceptance Criteria

- All admin actions logged automatically via LogAdminAction middleware
- Manual logging available via AuditLogService methods
- Sensitive data (passwords, tokens) redacted from logs
- IP address and user agent captured
- Audit log viewable with filters (admin, action, target, date range)
- Audit log exportable to CSV
- Log retention job deletes logs older than 2 years
- Logging failures do not block admin operations

## Testing Requirements

### Service Tests

**File**: `tests/Unit/Services/Admin/AuditLogServiceTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Admin;

use App\Contracts\Repositories\AuditLogRepository;
use App\Services\Admin\AuditLogService;
use Mockery;
use Tests\TestCase;

final class AuditLogServiceTest extends TestCase
{
    public function test_log_creates_audit_entry(): void
    {
        $repository = Mockery::mock(AuditLogRepository::class);
        $repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(static function (array $data): bool {
                return $data['admin_id'] === 1
                    && $data['action'] === 'tenant_created'
                    && $data['target_type'] === 'tenant';
            }));

        $service = new AuditLogService($repository);

        $service->log(
            adminId: 1,
            action: 'tenant_created',
            targetType: 'tenant',
            targetId: 42,
        );
    }

    public function test_sensitive_data_is_redacted(): void
    {
        $repository = Mockery::mock(AuditLogRepository::class);
        $repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(static function (array $data): bool {
                return $data['details']['password'] === '[REDACTED]';
            }));

        $service = new AuditLogService($repository);

        $service->log(
            adminId: 1,
            action: 'user_created',
            targetType: 'user',
            details: ['email' => 'test@example.com', 'password' => 'secret123'],
        );
    }

    public function test_large_details_are_truncated(): void
    {
        $repository = Mockery::mock(AuditLogRepository::class);
        $repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(static function (array $data): bool {
                return isset($data['details']['truncated'])
                    && $data['details']['truncated'] === true;
            }));

        $service = new AuditLogService($repository);

        $largeDetails = ['data' => str_repeat('x', 15000)]; // >10KB

        $service->log(
            adminId: 1,
            action: 'test',
            targetType: 'test',
            details: $largeDetails,
        );
    }
}
```

### Feature Tests

**File**: `tests/Feature/Admin/AuditLogTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_audit_log(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        AuditLog::factory()->count(5)->create();

        $response = $this->actingAs($admin)->getJson('/api/admin/audit-log');

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
    }

    public function test_audit_log_can_be_filtered_by_admin(): void
    {
        $admin1 = User::factory()->create(['is_admin' => true]);
        $admin2 = User::factory()->create(['is_admin' => true]);

        AuditLog::factory()->create(['admin_id' => $admin1->id]);
        AuditLog::factory()->create(['admin_id' => $admin2->id]);

        $response = $this->actingAs($admin1)
            ->getJson("/api/admin/audit-log?admin_id={$admin1->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_audit_log_can_be_filtered_by_action(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        AuditLog::factory()->create(['action' => 'tenant_created']);
        AuditLog::factory()->create(['action' => 'tenant_suspended']);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/audit-log?action=tenant_created');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_audit_log_can_be_exported(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->postJson('/api/admin/audit-log/export');

        $response->assertStatus(202);
        $response->assertJson(['message' => 'Audit log export started. You will receive an email when complete.']);
    }

    public function test_tenant_action_creates_audit_log(): void
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
}
```

## Edge Cases

1. **Audit log write failure**: Must not block admin action
   - Solution: Try-catch in service, log to file system fallback

2. **Very large details JSON**: Exceeds database column size
   - Solution: Truncate to 10KB max, store summary

3. **Concurrent audit log writes**: Multiple admins active simultaneously
   - Solution: Database handles concurrency, no locking needed

4. **Target entity deleted before log written**: Name unavailable
   - Solution: Graceful fallback to null target_name

5. **Date range query without limit**: Could return millions of rows
   - Solution: Always enforce pagination, max 10K export

## Error Handling

- Service catches all exceptions, logs error, continues execution
- Controller validates all filter parameters
- Export job has 10-minute timeout for large datasets
- Archive job logs deletion count for monitoring

## Performance Considerations

- Indexes on admin_id, action, target_type, created_at
- Pagination enforced (no unbounded queries)
- Async logging via middleware (non-blocking)
- Archive job runs monthly (off-peak hours)
- Export limited to 10K rows (queue for larger)

## Security Checklist

- [ ] Sensitive fields redacted from details JSON
- [ ] IP address captured for all actions
- [ ] Audit log immutable (no update/delete via API)
- [ ] Only admins can view audit log
- [ ] Export requires admin authentication
- [ ] Archive job preserves logs for 2 years minimum
