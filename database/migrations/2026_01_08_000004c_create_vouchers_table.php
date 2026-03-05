<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->decimal('initial_amount', 10, 2);
            $table->decimal('balance_amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->timestamp('expires_at')->nullable();
            $table->enum('status', ['active', 'inactive']);
            $table->string('issued_to_name')->nullable();
            $table->string('issued_to_email')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
