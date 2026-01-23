<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_image_tag', static function (Blueprint $table): void {
            $table->foreignId('portfolio_image_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portfolio_tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['portfolio_image_id', 'portfolio_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_image_tag');
    }
};
