<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_booking_field_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_field_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->unique(['service_id', 'booking_field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_booking_field_overrides');
    }
};
