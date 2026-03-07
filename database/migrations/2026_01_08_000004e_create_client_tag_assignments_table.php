<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_tag_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_tag_id')->constrained('client_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['client_id', 'client_tag_id'], 'client_tag_assignments_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_tag_assignments');
    }
};
