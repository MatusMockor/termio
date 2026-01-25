<?php

declare(strict_types=1);

use App\Enums\BusinessType;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->timestamp('onboarding_completed_at')->nullable()->after('business_type');
            $table->string('onboarding_step')->nullable()->after('onboarding_completed_at');
            $table->jsonb('onboarding_data')->nullable()->after('onboarding_step');

            $table->index('onboarding_completed_at');
            $table->index('onboarding_step');
        });

        // Set default business_type for existing records
        Tenant::query()
            ->where(function ($query): void {
                $query->whereNull('business_type')
                    ->orWhere('business_type', '');
            })
            ->update(['business_type' => BusinessType::HairBeauty]);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropIndex(['onboarding_completed_at']);
            $table->dropIndex(['onboarding_step']);
            $table->dropColumn(['onboarding_completed_at', 'onboarding_step', 'onboarding_data']);
        });
    }
};
