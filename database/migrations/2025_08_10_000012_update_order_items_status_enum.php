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
        Schema::table('order_items', function (Blueprint $table) {
            // Drop the existing status enum column
            $table->dropColumn('status');
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Re-create with updated enum values
            $table->enum('status', ['pending', 'cooking', 'ready', 'served', 'completed'])
                ->default('pending')
                ->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Drop the updated status enum column
            $table->dropColumn('status');
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Restore original enum values
            $table->enum('status', ['pending', 'cooking', 'ready', 'served'])->default('pending')->after('price');
        });
    }
};
