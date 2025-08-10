<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Drop the existing status enum column
            $table->dropColumn('status');
        });

        Schema::table('orders', function (Blueprint $table) {
            // Re-create with updated enum values
            $table->enum('status', ['pending', 'cooking', 'ready', 'completed', 'cancelled'])
                ->default('pending')
                ->after('is_paid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Drop the updated status enum column
            $table->dropColumn('status');
        });

        Schema::table('orders', function (Blueprint $table) {
            // Restore original enum values
            $table->enum('status', ['open', 'closed'])->default('open')->after('is_paid');
        });
    }
};
