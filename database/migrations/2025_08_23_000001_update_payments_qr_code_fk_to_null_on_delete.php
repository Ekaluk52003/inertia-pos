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
        Schema::table('payments', function (Blueprint $table) {
            // Drop existing foreign key (name may vary; dropping by column is safe)
            try {
                $table->dropForeign(['qr_code_id']);
            } catch (\Throwable $e) {
                // ignore if it doesn't exist; we'll re-create it below
            }

            // Ensure column is nullable, then add FK with nullOnDelete
            $table->unsignedBigInteger('qr_code_id')->nullable()->change();
            $table->foreign('qr_code_id')
                ->references('id')
                ->on('qr_codes')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Revert to restrictive FK (no action on delete)
            try {
                $table->dropForeign(['qr_code_id']);
            } catch (\Throwable $e) {
                // ignore
            }

            $table->unsignedBigInteger('qr_code_id')->nullable()->change();
            $table->foreign('qr_code_id')
                ->references('id')
                ->on('qr_codes');
        });
    }
};
