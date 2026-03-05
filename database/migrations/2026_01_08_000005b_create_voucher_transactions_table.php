<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['issue', 'redeem', 'restore', 'adjust']);
            $table->decimal('amount', 10, 2);
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['voucher_id', 'type']);
            $table->index(['appointment_id', 'type']);
            $table->unique(['voucher_id', 'appointment_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_transactions');
    }
};
