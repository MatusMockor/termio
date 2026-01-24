# Database Schema and Migrations

**PRD Source**: `prds/2026-01-subscription-pricing-system.md`
**Category**: Database
**Complexity**: Large
**Dependencies**: None
**Status**: Not Started

## Technical Overview

**Summary**: Design and implement database schema for subscription management including plans, subscriptions, invoices, payment methods, and usage tracking. This is the foundational task that all other subscription features depend on.

**Architecture Impact**: Adds 5 new tables and modifies the `tenants` table to support subscription relationships. Integrates with Laravel Cashier's expected schema.

**Risk Assessment**:
- **Medium**: Schema changes to existing `tenants` table require careful migration
- **Low**: New tables have no existing dependencies

## Data Layer

### New Tables

#### `plans` Table

```sql
CREATE TABLE plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,                    -- FREE, EASY, SMART, STANDARD, PREMIUM
    slug VARCHAR(50) NOT NULL UNIQUE,             -- free, easy, smart, standard, premium
    description TEXT NULL,
    monthly_price DECIMAL(8, 2) NOT NULL,         -- EUR amount (0.00 for FREE)
    yearly_price DECIMAL(8, 2) NOT NULL,          -- EUR amount (0.00 for FREE)
    stripe_monthly_price_id VARCHAR(255) NULL,    -- Stripe Price ID for monthly
    stripe_yearly_price_id VARCHAR(255) NULL,     -- Stripe Price ID for yearly
    features JSON NOT NULL,                       -- Feature flags object
    limits JSON NOT NULL,                         -- Usage limits object
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    is_public BOOLEAN NOT NULL DEFAULT TRUE,      -- Show on pricing page
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    INDEX idx_plans_slug (slug),
    INDEX idx_plans_active (is_active),
    INDEX idx_plans_sort (sort_order)
);
```

**Features JSON Structure**:
```json
{
    "online_booking_widget": true,
    "manual_reservations": true,
    "calendar_view": "basic|advanced",
    "client_database": "basic|advanced",
    "email_confirmations": true,
    "email_reminders": true,
    "sms_reminders": false,
    "custom_logo": false,
    "custom_colors": false,
    "custom_booking_url": false,
    "custom_domain": false,
    "white_label": false,
    "google_calendar_sync": false,
    "payment_gateway": false,
    "api_access": false,
    "zapier_integration": false,
    "multi_language": false,
    "staff_permissions": false,
    "client_segmentation": false,
    "waitlist_management": false,
    "recurring_appointments": false,
    "gift_vouchers": false,
    "reports_statistics": "basic|full"
}
```

**Limits JSON Structure**:
```json
{
    "reservations_per_month": 150,
    "users": 1,
    "locations": 1,
    "services": 10,
    "sms_credits_per_month": 0
}
```

#### `subscriptions` Table (Laravel Cashier compatible)

```sql
CREATE TABLE subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(255) NOT NULL DEFAULT 'default',  -- Cashier requirement
    stripe_id VARCHAR(255) NOT NULL UNIQUE,        -- Stripe subscription ID
    stripe_status VARCHAR(255) NOT NULL,           -- active, canceled, past_due, etc.
    stripe_price VARCHAR(255) NULL,                -- Current Stripe Price ID
    billing_cycle ENUM('monthly', 'yearly') NOT NULL DEFAULT 'monthly',
    quantity INT NOT NULL DEFAULT 1,
    trial_ends_at TIMESTAMP NULL,
    ends_at TIMESTAMP NULL,                        -- Scheduled cancellation date
    scheduled_plan_id BIGINT UNSIGNED NULL,        -- For scheduled downgrades
    scheduled_change_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE RESTRICT,
    FOREIGN KEY (scheduled_plan_id) REFERENCES plans(id) ON DELETE SET NULL,

    INDEX idx_subscriptions_tenant (tenant_id),
    INDEX idx_subscriptions_status (stripe_status),
    INDEX idx_subscriptions_trial (trial_ends_at),
    INDEX idx_subscriptions_ends (ends_at)
);
```

#### `subscription_items` Table (Laravel Cashier requirement)

```sql
CREATE TABLE subscription_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscription_id BIGINT UNSIGNED NOT NULL,
    stripe_id VARCHAR(255) NOT NULL UNIQUE,
    stripe_product VARCHAR(255) NOT NULL,
    stripe_price VARCHAR(255) NOT NULL,
    quantity INT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,

    UNIQUE INDEX idx_subscription_items_unique (subscription_id, stripe_price)
);
```

#### `invoices` Table

```sql
CREATE TABLE invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    subscription_id BIGINT UNSIGNED NULL,
    stripe_invoice_id VARCHAR(255) NULL UNIQUE,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,   -- INV-YYYY-MM-NNNN

    -- Amounts
    amount_net DECIMAL(10, 2) NOT NULL,
    vat_rate DECIMAL(5, 2) NOT NULL DEFAULT 0.00, -- 20.00 for 20%
    vat_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    amount_gross DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'EUR',

    -- Customer details (snapshot at invoice time)
    customer_name VARCHAR(255) NOT NULL,
    customer_address TEXT NULL,
    customer_country VARCHAR(2) NULL,             -- ISO country code
    customer_vat_id VARCHAR(50) NULL,

    -- Line items
    line_items JSON NOT NULL,                     -- Array of line item objects

    -- Status
    status ENUM('draft', 'open', 'paid', 'void', 'uncollectible') NOT NULL DEFAULT 'draft',
    paid_at TIMESTAMP NULL,

    -- Files
    pdf_path VARCHAR(500) NULL,

    -- Metadata
    notes TEXT NULL,
    billing_period_start DATE NULL,
    billing_period_end DATE NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL,

    INDEX idx_invoices_tenant (tenant_id),
    INDEX idx_invoices_status (status),
    INDEX idx_invoices_number (invoice_number),
    INDEX idx_invoices_created (created_at)
);
```

**Line Items JSON Structure**:
```json
[
    {
        "description": "SMART Plan - Monthly Subscription",
        "quantity": 1,
        "unit_price": 11.90,
        "amount": 11.90,
        "period_start": "2026-01-01",
        "period_end": "2026-01-31"
    },
    {
        "description": "Proration credit - EASY Plan",
        "quantity": 1,
        "unit_price": -3.50,
        "amount": -3.50
    }
]
```

#### `payment_methods` Table

```sql
CREATE TABLE payment_methods (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    stripe_payment_method_id VARCHAR(255) NOT NULL UNIQUE,
    type VARCHAR(50) NOT NULL DEFAULT 'card',     -- card, sepa_debit, etc.
    card_brand VARCHAR(50) NULL,                  -- visa, mastercard, etc.
    card_last4 VARCHAR(4) NULL,
    card_exp_month TINYINT UNSIGNED NULL,
    card_exp_year SMALLINT UNSIGNED NULL,
    is_default BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,

    INDEX idx_payment_methods_tenant (tenant_id),
    INDEX idx_payment_methods_default (tenant_id, is_default)
);
```

#### `usage_records` Table

```sql
CREATE TABLE usage_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    period VARCHAR(7) NOT NULL,                   -- YYYY-MM format
    reservations_count INT UNSIGNED NOT NULL DEFAULT 0,
    reservations_limit INT UNSIGNED NOT NULL,     -- Snapshot of limit at period start
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,

    UNIQUE INDEX idx_usage_tenant_period (tenant_id, period),
    INDEX idx_usage_period (period)
);
```

### Modified Tables

#### `tenants` Table Modifications

```sql
ALTER TABLE tenants
    ADD COLUMN stripe_id VARCHAR(255) NULL UNIQUE AFTER status,
    ADD COLUMN pm_type VARCHAR(255) NULL AFTER stripe_id,
    ADD COLUMN pm_last_four VARCHAR(4) NULL AFTER pm_type,
    ADD COLUMN country VARCHAR(2) NULL AFTER phone,
    ADD COLUMN vat_id VARCHAR(50) NULL AFTER country,
    ADD COLUMN vat_id_verified_at TIMESTAMP NULL AFTER vat_id,
    ADD INDEX idx_tenants_stripe (stripe_id);
```

## Migration Files

### Migration 1: Create Plans Table

**File**: `database/migrations/2026_01_23_000001_create_plans_table.php`

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
        Schema::create('plans', static function (Blueprint $table): void {
            $table->id();
            $table->string('name', 50);
            $table->string('slug', 50)->unique();
            $table->text('description')->nullable();
            $table->decimal('monthly_price', 8, 2);
            $table->decimal('yearly_price', 8, 2);
            $table->string('stripe_monthly_price_id')->nullable();
            $table->string('stripe_yearly_price_id')->nullable();
            $table->json('features');
            $table->json('limits');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->timestamps();

            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
```

### Migration 2: Create Subscriptions Table

**File**: `database/migrations/2026_01_23_000002_create_subscriptions_table.php`

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
        Schema::create('subscriptions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('type')->default('default');
            $table->string('stripe_id')->unique();
            $table->string('stripe_status');
            $table->string('stripe_price')->nullable();
            $table->enum('billing_cycle', ['monthly', 'yearly'])->default('monthly');
            $table->integer('quantity')->default(1);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('scheduled_plan_id')
                ->nullable()
                ->constrained('plans')
                ->nullOnDelete();
            $table->timestamp('scheduled_change_at')->nullable();
            $table->timestamps();

            $table->index('stripe_status');
            $table->index('trial_ends_at');
            $table->index('ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
```

### Migration 3: Create Subscription Items Table

**File**: `database/migrations/2026_01_23_000003_create_subscription_items_table.php`

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
        Schema::create('subscription_items', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_id')->unique();
            $table->string('stripe_product');
            $table->string('stripe_price');
            $table->integer('quantity')->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'stripe_price']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_items');
    }
};
```

### Migration 4: Create Invoices Table

**File**: `database/migrations/2026_01_23_000004_create_invoices_table.php`

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
        Schema::create('invoices', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('stripe_invoice_id')->nullable()->unique();
            $table->string('invoice_number', 50)->unique();

            $table->decimal('amount_net', 10, 2);
            $table->decimal('vat_rate', 5, 2)->default(0.00);
            $table->decimal('vat_amount', 10, 2)->default(0.00);
            $table->decimal('amount_gross', 10, 2);
            $table->string('currency', 3)->default('EUR');

            $table->string('customer_name');
            $table->text('customer_address')->nullable();
            $table->string('customer_country', 2)->nullable();
            $table->string('customer_vat_id', 50)->nullable();

            $table->json('line_items');

            $table->enum('status', ['draft', 'open', 'paid', 'void', 'uncollectible'])
                ->default('draft');
            $table->timestamp('paid_at')->nullable();

            $table->string('pdf_path', 500)->nullable();
            $table->text('notes')->nullable();
            $table->date('billing_period_start')->nullable();
            $table->date('billing_period_end')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
```

### Migration 5: Create Payment Methods Table

**File**: `database/migrations/2026_01_23_000005_create_payment_methods_table.php`

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
        Schema::create('payment_methods', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_payment_method_id')->unique();
            $table->string('type', 50)->default('card');
            $table->string('card_brand', 50)->nullable();
            $table->string('card_last4', 4)->nullable();
            $table->unsignedTinyInteger('card_exp_month')->nullable();
            $table->unsignedSmallInteger('card_exp_year')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
```

### Migration 6: Create Usage Records Table

**File**: `database/migrations/2026_01_23_000006_create_usage_records_table.php`

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
        Schema::create('usage_records', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7); // YYYY-MM
            $table->unsignedInteger('reservations_count')->default(0);
            $table->unsignedInteger('reservations_limit');
            $table->timestamps();

            $table->unique(['tenant_id', 'period']);
            $table->index('period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_records');
    }
};
```

### Migration 7: Add Subscription Columns to Tenants

**File**: `database/migrations/2026_01_23_000007_add_subscription_columns_to_tenants_table.php`

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
            $table->string('stripe_id')->nullable()->unique()->after('status');
            $table->string('pm_type')->nullable()->after('stripe_id');
            $table->string('pm_last_four', 4)->nullable()->after('pm_type');
            $table->string('country', 2)->nullable()->after('phone');
            $table->string('vat_id', 50)->nullable()->after('country');
            $table->timestamp('vat_id_verified_at')->nullable()->after('vat_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', static function (Blueprint $table): void {
            $table->dropColumn([
                'stripe_id',
                'pm_type',
                'pm_last_four',
                'country',
                'vat_id',
                'vat_id_verified_at',
            ]);
        });
    }
};
```

### Seeder: Default Plans

**File**: `database/seeders/PlanSeeder.php`

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

final class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'FREE',
                'slug' => 'free',
                'description' => 'Perfect for trying out Termio',
                'monthly_price' => 0.00,
                'yearly_price' => 0.00,
                'sort_order' => 0,
                'features' => [
                    'online_booking_widget' => true,
                    'manual_reservations' => true,
                    'calendar_view' => 'basic',
                    'client_database' => 'basic',
                    'email_confirmations' => true,
                    'email_reminders' => true,
                    'sms_reminders' => false,
                    'custom_logo' => false,
                    'custom_colors' => false,
                    'custom_booking_url' => false,
                    'custom_domain' => false,
                    'white_label' => false,
                    'google_calendar_sync' => false,
                    'payment_gateway' => false,
                    'api_access' => false,
                    'zapier_integration' => false,
                    'multi_language' => false,
                    'staff_permissions' => false,
                    'client_segmentation' => false,
                    'waitlist_management' => false,
                    'recurring_appointments' => false,
                    'gift_vouchers' => false,
                    'reports_statistics' => 'basic',
                ],
                'limits' => [
                    'reservations_per_month' => 150,
                    'users' => 1,
                    'locations' => 1,
                    'services' => 10,
                    'sms_credits_per_month' => 0,
                ],
            ],
            [
                'name' => 'EASY',
                'slug' => 'easy',
                'description' => 'For solo practitioners getting started',
                'monthly_price' => 5.90,
                'yearly_price' => 49.00,
                'sort_order' => 1,
                'features' => [
                    'online_booking_widget' => true,
                    'manual_reservations' => true,
                    'calendar_view' => 'advanced',
                    'client_database' => 'basic',
                    'email_confirmations' => true,
                    'email_reminders' => true,
                    'sms_reminders' => false,
                    'custom_logo' => true,
                    'custom_colors' => true,
                    'custom_booking_url' => true,
                    'custom_domain' => false,
                    'white_label' => false,
                    'google_calendar_sync' => true,
                    'payment_gateway' => true,
                    'api_access' => false,
                    'zapier_integration' => false,
                    'multi_language' => true,
                    'staff_permissions' => false,
                    'client_segmentation' => false,
                    'waitlist_management' => false,
                    'recurring_appointments' => true,
                    'gift_vouchers' => false,
                    'reports_statistics' => 'full',
                ],
                'limits' => [
                    'reservations_per_month' => 350,
                    'users' => 1,
                    'locations' => 1,
                    'services' => -1, // unlimited
                    'sms_credits_per_month' => 0,
                ],
            ],
            [
                'name' => 'SMART',
                'slug' => 'smart',
                'description' => 'Best value for growing businesses',
                'monthly_price' => 11.90,
                'yearly_price' => 99.00,
                'sort_order' => 2,
                'features' => [
                    'online_booking_widget' => true,
                    'manual_reservations' => true,
                    'calendar_view' => 'advanced',
                    'client_database' => 'advanced',
                    'email_confirmations' => true,
                    'email_reminders' => true,
                    'sms_reminders' => true,
                    'custom_logo' => true,
                    'custom_colors' => true,
                    'custom_booking_url' => true,
                    'custom_domain' => true,
                    'white_label' => false,
                    'google_calendar_sync' => true,
                    'payment_gateway' => true,
                    'api_access' => true,
                    'zapier_integration' => true,
                    'multi_language' => true,
                    'staff_permissions' => true,
                    'client_segmentation' => true,
                    'waitlist_management' => true,
                    'recurring_appointments' => true,
                    'gift_vouchers' => true,
                    'reports_statistics' => 'full',
                ],
                'limits' => [
                    'reservations_per_month' => 1500,
                    'users' => 3,
                    'locations' => 2,
                    'services' => -1,
                    'sms_credits_per_month' => 50,
                ],
            ],
            [
                'name' => 'STANDARD',
                'slug' => 'standard',
                'description' => 'For established businesses with teams',
                'monthly_price' => 24.90,
                'yearly_price' => 199.00,
                'sort_order' => 3,
                'features' => [
                    'online_booking_widget' => true,
                    'manual_reservations' => true,
                    'calendar_view' => 'advanced',
                    'client_database' => 'advanced',
                    'email_confirmations' => true,
                    'email_reminders' => true,
                    'sms_reminders' => true,
                    'custom_logo' => true,
                    'custom_colors' => true,
                    'custom_booking_url' => true,
                    'custom_domain' => true,
                    'white_label' => false,
                    'google_calendar_sync' => true,
                    'payment_gateway' => true,
                    'api_access' => true,
                    'zapier_integration' => true,
                    'multi_language' => true,
                    'staff_permissions' => true,
                    'client_segmentation' => true,
                    'waitlist_management' => true,
                    'recurring_appointments' => true,
                    'gift_vouchers' => true,
                    'reports_statistics' => 'full',
                ],
                'limits' => [
                    'reservations_per_month' => -1, // unlimited
                    'users' => 10,
                    'locations' => 10,
                    'services' => -1,
                    'sms_credits_per_month' => 200,
                ],
            ],
            [
                'name' => 'PREMIUM',
                'slug' => 'premium',
                'description' => 'Enterprise features with priority support',
                'monthly_price' => 49.90,
                'yearly_price' => 449.00,
                'sort_order' => 4,
                'features' => [
                    'online_booking_widget' => true,
                    'manual_reservations' => true,
                    'calendar_view' => 'advanced',
                    'client_database' => 'advanced',
                    'email_confirmations' => true,
                    'email_reminders' => true,
                    'sms_reminders' => true,
                    'custom_logo' => true,
                    'custom_colors' => true,
                    'custom_booking_url' => true,
                    'custom_domain' => true,
                    'white_label' => true,
                    'google_calendar_sync' => true,
                    'payment_gateway' => true,
                    'api_access' => true,
                    'zapier_integration' => true,
                    'multi_language' => true,
                    'staff_permissions' => true,
                    'client_segmentation' => true,
                    'waitlist_management' => true,
                    'recurring_appointments' => true,
                    'gift_vouchers' => true,
                    'reports_statistics' => 'full',
                ],
                'limits' => [
                    'reservations_per_month' => -1,
                    'users' => -1,
                    'locations' => -1,
                    'services' => -1,
                    'sms_credits_per_month' => -1, // unlimited
                ],
            ],
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );
        }
    }
}
```

## Testing Strategy

### E2E Test
- `TestDatabaseSchema` covering migrations run successfully, rollback works
- Verify: All tables created with correct columns, indexes, and foreign keys

### Manual Verification
- Run migrations on fresh database
- Verify foreign key constraints work correctly
- Test cascade delete behavior

## Implementation Steps

1. **Small** - Create Plan model with PHPDoc annotations
2. **Small** - Create Subscription model with PHPDoc annotations
3. **Small** - Create Invoice model with PHPDoc annotations
4. **Small** - Create PaymentMethod model with PHPDoc annotations
5. **Small** - Create UsageRecord model with PHPDoc annotations
6. **Medium** - Create all migration files (7 migrations)
7. **Small** - Update Tenant model with new columns and relationships
8. **Medium** - Create PlanSeeder with all 5 plans
9. **Small** - Create model factories for testing
10. **Small** - Run Pint and verify code style

## Cross-Task Dependencies

- **Blocks**: All other subscription tasks depend on this schema
- **Parallel work**: None - this must complete first
