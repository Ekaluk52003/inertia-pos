<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: change to string to allow safe data migration
        Schema::table('orders', function (Blueprint $table) {
            $table->string('status')->default('active')->change();
        });

        // Step 2: map values
        DB::table('orders')->where('status', 'paid')->update(['status' => 'billed']);
        DB::table('orders')->where('status', 'unpaid')->update(['status' => 'active']);

        // Step 3: change back to enum used by legacy code/tests
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', ['active', 'billing', 'billed', 'completed'])->default('active')->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', ['paid', 'unpaid'])->default('unpaid')->change();
        });

        DB::table('orders')->where('status', 'billed')->update(['status' => 'paid']);
        DB::table('orders')->where('status', 'active')->update(['status' => 'unpaid']);
    }
};
