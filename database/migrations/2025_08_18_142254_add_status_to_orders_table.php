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
        // First, update existing data to match new enum values
        DB::table('orders')->where('status', 'pending')->update(['status' => 'active']);
        DB::table('orders')->where('status', 'ready')->update(['status' => 'active']);
        DB::table('orders')->where('status', 'cooking')->update(['status' => 'active']);
        DB::table('orders')->where('status', 'cancelled')->update(['status' => 'completed']);

        // Now update the enum
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', ['active', 'billing', 'billed', 'completed'])
                ->default('active')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', ['pending', 'cooking', 'ready', 'completed', 'cancelled'])
                ->default('pending')
                ->change();
        });

        // Revert data mapping
        DB::table('orders')->where('status', 'active')->update(['status' => 'pending']);
        DB::table('orders')->where('status', 'billing')->update(['status' => 'pending']);
        DB::table('orders')->where('status', 'billed')->update(['status' => 'completed']);
    }
};
