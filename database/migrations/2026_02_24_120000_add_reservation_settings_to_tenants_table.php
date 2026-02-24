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
            $table->unsignedSmallInteger('reservation_lead_time_hours')
                ->default((int) config('reservation.defaults.lead_time_hours'))
                ->after('timezone');
            $table->unsignedSmallInteger('reservation_max_days_in_advance')
                ->default((int) config('reservation.defaults.max_days_in_advance'))
                ->after('reservation_lead_time_hours');
            $table->unsignedSmallInteger('reservation_slot_interval_minutes')
                ->default((int) config('reservation.defaults.slot_interval_minutes'))
                ->after('reservation_max_days_in_advance');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', static function (Blueprint $table): void {
            $table->dropColumn([
                'reservation_lead_time_hours',
                'reservation_max_days_in_advance',
                'reservation_slot_interval_minutes',
            ]);
        });
    }
};
