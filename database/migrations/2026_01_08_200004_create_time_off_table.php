<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_off', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->date('date');
            $table->time('start_time')->nullable(); // null = all day
            $table->time('end_time')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_off');
    }
};
