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
        // Step 1: change column to string to allow transitional values
        Schema::table('orders', function (Blueprint $table) {
            $table->string('status')->default('unpaid')->change();
        });

        // Step 2: map legacy statuses to paid/unpaid
        DB::table('orders')->whereIn('status', ['billed', 'completed'])->update(['status' => 'paid']);
        DB::table('orders')->whereIn('status', ['active', 'billing', 'pending', 'cooking', 'ready', 'open', 'closed'])->update(['status' => 'unpaid']);

        // Step 3: change to strict enum paid/unpaid
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', ['paid', 'unpaid'])->default('unpaid')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert enum back to previous set in a safe way
        Schema::table('orders', function (Blueprint $table) {
            $table->string('status')->default('active')->change();
        });

        // Map back values
        DB::table('orders')->where('status', 'paid')->update(['status' => 'billed']);
        DB::table('orders')->where('status', 'unpaid')->update(['status' => 'active']);

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', ['active', 'billing', 'billed', 'completed'])->default('active')->change();
        });
    }
};
