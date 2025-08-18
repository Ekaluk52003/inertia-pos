<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For SQLite compatibility, we need to recreate the column
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite approach: drop and recreate column
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('status');
            });

            Schema::table('orders', function (Blueprint $table) {
                $table->enum('status', ['active', 'billing', 'billed', 'completed', 'pending', 'cooking', 'ready', 'cancelled'])
                    ->default('active')
                    ->after('is_paid');
            });
        } else {
            // MySQL approach: use MODIFY
            DB::statement("ALTER TABLE orders MODIFY status ENUM('active', 'billing', 'billed', 'completed', 'pending', 'cooking', 'ready', 'cancelled') DEFAULT 'active'");
        }

        // Update existing orders to have active status if they're currently pending
        DB::table('orders')
            ->where('status', 'pending')
            ->update(['status' => 'active']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite approach: drop and recreate column
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('status');
            });

            Schema::table('orders', function (Blueprint $table) {
                $table->enum('status', ['pending', 'cooking', 'ready', 'completed', 'cancelled'])
                    ->default('pending')
                    ->after('is_paid');
            });
        } else {
            // MySQL approach: use MODIFY
            DB::statement("ALTER TABLE orders MODIFY status ENUM('pending', 'cooking', 'ready', 'completed', 'cancelled') DEFAULT 'pending'");
        }
    }
};
