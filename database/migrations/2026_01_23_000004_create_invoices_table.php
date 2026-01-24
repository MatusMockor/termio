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
