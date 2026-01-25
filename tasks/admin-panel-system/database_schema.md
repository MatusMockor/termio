# Database Schema - Admin Panel System

**PRD Source**: `prds/2026-01-admin-panel-system.md`
**Category**: Database
**Complexity**: Large
**Dependencies**: None
**Status**: Not Started

## Technical Overview

**Summary**: Create database schema for admin panel system including 4 new tables (audit_logs, feature_flags, platform_settings, impersonation_sessions) and modifications to existing tenants table for suspend/delete functionality.

**Architecture Impact**: Adds audit trail, feature flag management, platform configuration, and impersonation tracking capabilities. Modifies tenant model to support soft deletes and suspension.

**Risk Assessment**:
- **Medium**: Audit log table can grow large - needs indexing strategy
- **Low**: Migration order matters - must run after tenants/users tables exist
- **Low**: Soft delete on tenants affects existing queries

## Database Tables

### New Tables

#### 1. audit_logs

Stores all admin actions for compliance and security auditing.

**Migration File**: `database/migrations/2026_01_26_000001_create_audit_logs_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 100);
            $table->string('target_type', 50);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('target_name')->nullable();
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Indexes for fast querying
            $table->index('admin_id');
            $table->index('action');
            $table->index(['target_type', 'target_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
```

**Schema Details**:
- `id`: Primary key
- `admin_id`: Foreign key to users table (which admin performed action)
- `action`: Action type (e.g., 'tenant_suspended', 'subscription_cancelled')
- `target_type`: Entity type affected (e.g., 'tenant', 'user', 'subscription')
- `target_id`: Entity ID affected
- `target_name`: Human-readable name for display
- `details`: JSON with action-specific data
- `ip_address`: Admin's IP address (IPv4 or IPv6)
- `user_agent`: Browser/client info
- `created_at`: Timestamp (no updated_at - immutable log)

**Indexes**:
- `admin_id` - filter by admin user
- `action` - filter by action type
- `target_type + target_id` - find all actions on specific entity
- `created_at` - chronological sorting and date range queries

**Storage Estimate**:
- ~500 bytes per row
- Expected: 100 actions/day = 36,500 rows/year = ~18 MB/year
- 2-year retention = ~36 MB (negligible)

---

#### 2. feature_flags

Stores platform-wide feature flags for gradual rollout and plan-based access control.

**Migration File**: `database/migrations/2026_01_26_000002_create_feature_flags_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flags', static function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('key', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(false);
            $table->foreignId('minimum_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->timestamps();

            $table->index('enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
    }
};
```

**Schema Details**:
- `id`: Primary key
- `name`: Display name (e.g., "Google Calendar Sync")
- `key`: Code key (e.g., "google_calendar_sync")
- `description`: Feature description for admin UI
- `enabled`: Global enable/disable toggle
- `minimum_plan_id`: Minimum plan required (null = all plans)
- `timestamps`: Created/updated tracking

**Indexes**:
- `enabled` - fast filtering for active features

**Seeder Data** (examples):
```php
[
    ['name' => 'Google Calendar Sync', 'key' => 'google_calendar_sync', 'enabled' => true, 'minimum_plan_id' => 3],
    ['name' => 'SMS Reminders', 'key' => 'sms_reminders', 'enabled' => true, 'minimum_plan_id' => 2],
    ['name' => 'API Access', 'key' => 'api_access', 'enabled' => true, 'minimum_plan_id' => 4],
    ['name' => 'White Label', 'key' => 'white_label', 'enabled' => false, 'minimum_plan_id' => 5],
]
```

---

#### 3. platform_settings

Key-value store for global platform configuration.

**Migration File**: `database/migrations/2026_01_26_000003_create_platform_settings_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', static function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value');
            $table->string('type', 50)->default('string');
            $table->text('description')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
```

**Schema Details**:
- `id`: Primary key
- `key`: Unique setting key (e.g., 'platform_name', 'stripe_public_key')
- `value`: Setting value (stored as text, type determines parsing)
- `type`: Data type (string, number, boolean, json)
- `description`: Admin-facing description
- `updated_at`: Last modification timestamp (no created_at needed)

**Indexes**:
- `key` - fast lookup by key

**Seeder Data** (examples):
```php
[
    ['key' => 'platform_name', 'value' => 'Termio', 'type' => 'string', 'description' => 'Platform name shown in emails and UI'],
    ['key' => 'support_email', 'value' => 'support@termio.com', 'type' => 'string', 'description' => 'Support contact email'],
    ['key' => 'stripe_test_mode', 'value' => 'true', 'type' => 'boolean', 'description' => 'Enable Stripe test mode'],
    ['key' => 'max_free_users', 'value' => '2', 'type' => 'number', 'description' => 'Maximum users for FREE plan'],
    ['key' => 'max_free_appointments', 'value' => '50', 'type' => 'number', 'description' => 'Maximum appointments per month for FREE plan'],
]
```

---

#### 4. impersonation_sessions

Tracks admin impersonation sessions for security and auditing.

**Migration File**: `database/migrations/2026_01_26_000004_create_impersonation_sessions_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impersonation_sessions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->string('ip_address', 45)->nullable();

            $table->index('admin_id');
            $table->index('user_id');
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonation_sessions');
    }
};
```

**Schema Details**:
- `id`: Primary key
- `admin_id`: Foreign key to admin user
- `user_id`: Foreign key to impersonated user
- `started_at`: Session start timestamp
- `ended_at`: Session end timestamp (null = active session)
- `ip_address`: Admin's IP address

**Indexes**:
- `admin_id` - find sessions by admin
- `user_id` - find who impersonated a user
- `started_at` - chronological sorting

**Usage Notes**:
- Active sessions have `ended_at = NULL`
- Query for active session: `WHERE ended_at IS NULL AND admin_id = ?`
- Session timeout: 1 hour (enforced in application logic)

---

### Modified Tables

#### Tenants Table Modifications

Add support for soft deletes and suspension.

**Migration File**: `database/migrations/2026_01_26_000005_add_admin_fields_to_tenants_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', static function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('name');
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', static function (Blueprint $table): void {
            $table->dropColumn('is_active');
            $table->dropSoftDeletes();
        });
    }
};
```

**New Fields**:
- `is_active`: Boolean flag for suspension (false = suspended)
- `deleted_at`: Soft delete timestamp (null = not deleted)

**Indexes**:
- `is_active` - filter active/suspended tenants

**Impact on Existing Queries**:
- Must add `withTrashed()` to see soft-deleted tenants
- Default scope hides soft-deleted records
- `is_active = false` blocks tenant login but keeps data

---

## Model Updates

### AuditLog Model

**File**: `app/Models/AuditLog.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $admin_id
 * @property string $action
 * @property string $target_type
 * @property int|null $target_id
 * @property string|null $target_name
 * @property array|null $details
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Carbon\Carbon $created_at
 *
 * @property-read User $admin
 */
final class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'admin_id',
        'action',
        'target_type',
        'target_id',
        'target_name',
        'details',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
```

---

### FeatureFlag Model

**File**: `app/Models/FeatureFlag.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string $key
 * @property string|null $description
 * @property bool $enabled
 * @property int|null $minimum_plan_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Plan|null $minimumPlan
 */
final class FeatureFlag extends Model
{
    protected $fillable = [
        'name',
        'key',
        'description',
        'enabled',
        'minimum_plan_id',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function minimumPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'minimum_plan_id');
    }
}
```

---

### PlatformSetting Model

**File**: `app/Models/PlatformSetting.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string $value
 * @property string $type
 * @property string|null $description
 * @property \Carbon\Carbon $updated_at
 */
final class PlatformSetting extends Model
{
    public const CREATED_AT = null;

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];

    protected $casts = [
        'updated_at' => 'datetime',
    ];

    public function getTypedValue(): mixed
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'number' => is_numeric($this->value) ? (float) $this->value : 0,
            'json' => json_decode($this->value, true, 512, JSON_THROW_ON_ERROR),
            default => $this->value,
        };
    }
}
```

---

### ImpersonationSession Model

**File**: `app/Models/ImpersonationSession.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $admin_id
 * @property int $user_id
 * @property \Carbon\Carbon $started_at
 * @property \Carbon\Carbon|null $ended_at
 * @property string|null $ip_address
 *
 * @property-read User $admin
 * @property-read User $user
 */
final class ImpersonationSession extends Model
{
    public const CREATED_AT = 'started_at';

    public const UPDATED_AT = null;

    protected $fillable = [
        'admin_id',
        'user_id',
        'started_at',
        'ended_at',
        'ip_address',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }
}
```

---

### Tenant Model Updates

**File**: `app/Models/Tenant.php` (modifications)

Add soft delete trait and is_active field:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 *
 * ... (existing properties)
 */
final class Tenant extends Model
{
    use SoftDeletes; // Add this trait

    protected $fillable = [
        'name',
        'is_active', // Add this field
        // ... existing fields
    ];

    protected $casts = [
        'is_active' => 'boolean', // Add this cast
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime', // Add this cast
        // ... existing casts
    ];

    public function isSuspended(): bool
    {
        return !$this->is_active;
    }

    public function suspend(): void
    {
        $this->is_active = false;
        $this->save();
    }

    public function activate(): void
    {
        $this->is_active = true;
        $this->save();
    }
}
```

---

## Acceptance Criteria

- All 4 new tables created successfully
- Indexes applied for query performance
- Models created with PHPDoc annotations
- Tenant model has soft delete trait
- Tenant model has `is_active` field
- All foreign keys have proper cascade/null behavior
- Migrations are reversible (down methods work)
- Seeders for feature_flags and platform_settings

## Testing Requirements

### Migration Tests

```php
// Test: AuditLog migration creates table
public function test_audit_logs_table_is_created(): void
{
    $this->assertTrue(Schema::hasTable('audit_logs'));
    $this->assertTrue(Schema::hasColumns('audit_logs', [
        'id', 'admin_id', 'action', 'target_type', 'target_id',
        'target_name', 'details', 'ip_address', 'user_agent', 'created_at',
    ]));
}

// Test: FeatureFlag migration creates table
public function test_feature_flags_table_is_created(): void
{
    $this->assertTrue(Schema::hasTable('feature_flags'));
    $this->assertTrue(Schema::hasColumns('feature_flags', [
        'id', 'name', 'key', 'description', 'enabled', 'minimum_plan_id',
        'created_at', 'updated_at',
    ]));
}

// Test: PlatformSetting migration creates table
public function test_platform_settings_table_is_created(): void
{
    $this->assertTrue(Schema::hasTable('platform_settings'));
    $this->assertTrue(Schema::hasColumns('platform_settings', [
        'id', 'key', 'value', 'type', 'description', 'updated_at',
    ]));
}

// Test: ImpersonationSession migration creates table
public function test_impersonation_sessions_table_is_created(): void
{
    $this->assertTrue(Schema::hasTable('impersonation_sessions'));
    $this->assertTrue(Schema::hasColumns('impersonation_sessions', [
        'id', 'admin_id', 'user_id', 'started_at', 'ended_at', 'ip_address',
    ]));
}

// Test: Tenant soft delete
public function test_tenant_soft_delete_works(): void
{
    $tenant = Tenant::factory()->create();

    $tenant->delete();

    $this->assertSoftDeleted($tenant);
    $this->assertNull(Tenant::find($tenant->id));
    $this->assertNotNull(Tenant::withTrashed()->find($tenant->id));
}

// Test: Tenant suspension
public function test_tenant_can_be_suspended(): void
{
    $tenant = Tenant::factory()->create(['is_active' => true]);

    $tenant->suspend();

    $this->assertFalse($tenant->is_active);
    $this->assertTrue($tenant->isSuspended());
}
```

### Model Tests

```php
// Test: AuditLog relationships
public function test_audit_log_belongs_to_admin(): void
{
    $admin = User::factory()->create(['is_admin' => true]);
    $log = AuditLog::factory()->create(['admin_id' => $admin->id]);

    $this->assertTrue($log->admin->is($admin));
}

// Test: FeatureFlag relationships
public function test_feature_flag_belongs_to_plan(): void
{
    $plan = Plan::factory()->create();
    $flag = FeatureFlag::factory()->create(['minimum_plan_id' => $plan->id]);

    $this->assertTrue($flag->minimumPlan->is($plan));
}

// Test: PlatformSetting typed values
public function test_platform_setting_returns_typed_boolean(): void
{
    $setting = PlatformSetting::factory()->create([
        'key' => 'test_bool',
        'value' => 'true',
        'type' => 'boolean',
    ]);

    $this->assertTrue($setting->getTypedValue());
}

public function test_platform_setting_returns_typed_number(): void
{
    $setting = PlatformSetting::factory()->create([
        'key' => 'test_num',
        'value' => '42',
        'type' => 'number',
    ]);

    $this->assertSame(42.0, $setting->getTypedValue());
}

// Test: ImpersonationSession active check
public function test_impersonation_session_is_active(): void
{
    $session = ImpersonationSession::factory()->create(['ended_at' => null]);

    $this->assertTrue($session->isActive());
}

public function test_impersonation_session_is_not_active_when_ended(): void
{
    $session = ImpersonationSession::factory()->create(['ended_at' => now()]);

    $this->assertFalse($session->isActive());
}
```

## Edge Cases

1. **Audit log growth**: Table can grow to millions of rows over time
   - Solution: Indexes on all query columns, archive after 2 years

2. **Tenant soft delete cascade**: What happens to related data?
   - Solution: Appointments, clients, services remain (marked as archived)
   - Users are soft deleted separately

3. **Feature flag changes**: What if feature disabled while tenant using it?
   - Solution: Immediate effect - tenant loses access (show upgrade prompt)

4. **Platform setting race conditions**: Multiple admins editing same setting
   - Solution: Last write wins, log both changes to audit log

5. **Impersonation session timeout**: Admin session expires during impersonation
   - Solution: Auto-end impersonation, redirect to login

## Error Handling

- Migration rollback must work perfectly (down methods)
- Foreign key constraints must not block legitimate deletes
- JSON parsing errors on audit log details must not fail queries
- Soft delete queries must always use `withTrashed()` explicitly

## Performance Considerations

- Audit log queries must use date range limits (prevent full table scan)
- Feature flag cache (30-second TTL) to avoid repeated queries
- Platform settings cache (5-minute TTL) for static config
- Impersonation session lookup optimized (index on admin_id + ended_at IS NULL)

## Rollout Plan

1. Run migrations on staging database
2. Verify all tables and indexes created
3. Run seeders for feature flags and platform settings
4. Test soft delete on test tenant
5. Deploy to production during low-traffic window
6. Monitor audit log write performance
