<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Map existing status values to the new canonical set before changing the column type.
        // Assumptions:
        // - values indicating an active/ordering session map to 'ordering' (e.g. 'active','open','pending')
        // - billing stays 'billing'
        // - completed/checked/closed/available/billed map to 'checked'

        DB::table('qr_codes')->whereIn('status', ['active', 'open', 'pending'])->update(['status' => 'ordering']);
        DB::table('qr_codes')->whereIn('status', ['billing'])->update(['status' => 'billing']);
        DB::table('qr_codes')->whereIn('status', ['checked', 'billed', 'completed', 'closed', 'available'])->update(['status' => 'checked']);

        // Alter the column to an ENUM limited to the three allowed values.
        DB::statement("ALTER TABLE qr_codes MODIFY status ENUM('ordering','billing','checked') DEFAULT 'ordering'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert the column back to a string with the previous default. Existing values will remain.
        DB::statement("ALTER TABLE qr_codes MODIFY status VARCHAR(255) DEFAULT 'available'");
    }
};
