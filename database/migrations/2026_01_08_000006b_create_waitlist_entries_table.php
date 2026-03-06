<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('preferred_staff_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->date('preferred_date')->nullable();
            $table->time('time_from')->nullable();
            $table->time('time_to')->nullable();
            $table->string('client_name');
            $table->string('client_phone', 20);
            $table->string('client_email')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'contacted', 'converted', 'cancelled'])->default('pending');
            $table->enum('source', ['public', 'owner'])->default('owner');
            $table->foreignId('converted_appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'service_id']);
            $table->index(['tenant_id', 'preferred_date']);
            $table->index(['tenant_id', 'preferred_staff_id']);
            $table->index(
                ['tenant_id', 'status', 'service_id', 'preferred_date'],
                'wl_tenant_status_service_date_idx',
            );
            $table->index(
                ['tenant_id', 'status', 'service_id', 'preferred_staff_id'],
                'wl_tenant_status_service_staff_idx',
            );
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
