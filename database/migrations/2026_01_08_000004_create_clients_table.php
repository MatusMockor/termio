<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('phone_normalized')->nullable();
            $table->string('email')->nullable();
            $table->string('email_normalized')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_blacklisted')->default(false);
            $table->boolean('is_whitelisted')->default(false);
            $table->text('booking_control_note')->nullable();
            $table->unsignedInteger('no_show_count')->default(0);
            $table->unsignedInteger('late_cancellation_count')->default(0);
            $table->timestamp('last_no_show_at')->nullable();
            $table->timestamp('last_late_cancellation_at')->nullable();
            $table->integer('total_visits')->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->timestamp('last_visit_at')->nullable();
            $table->enum('status', ['active', 'inactive', 'vip'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'email']);
            $table->index(['tenant_id', 'phone_normalized'], 'clients_tenant_phone_normalized_idx');
            $table->index(['tenant_id', 'email_normalized'], 'clients_tenant_email_normalized_idx');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'is_blacklisted'], 'clients_tenant_blacklisted_idx');
            $table->index(['tenant_id', 'is_whitelisted'], 'clients_tenant_whitelisted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
