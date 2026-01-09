<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('working_hours', function (Blueprint $table): void {
            // Drop the old foreign key and index
            $table->dropForeign(['staff_id']);
            $table->dropUnique(['tenant_id', 'staff_id', 'day_of_week']);

            // Add new foreign key referencing staff_profiles
            $table->foreign('staff_id')
                ->references('id')
                ->on('staff_profiles')
                ->nullOnDelete();

            // Re-create unique constraint
            $table->unique(['tenant_id', 'staff_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::table('working_hours', function (Blueprint $table): void {
            $table->dropForeign(['staff_id']);
            $table->dropUnique(['tenant_id', 'staff_id', 'day_of_week']);

            $table->foreign('staff_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->unique(['tenant_id', 'staff_id', 'day_of_week']);
        });
    }
};
