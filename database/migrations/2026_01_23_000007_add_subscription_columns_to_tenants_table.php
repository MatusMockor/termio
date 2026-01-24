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
