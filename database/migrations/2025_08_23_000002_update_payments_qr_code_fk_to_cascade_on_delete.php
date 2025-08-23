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
            try {
                $table->dropForeign(['qr_code_id']);
            } catch (\Throwable $e) {
                // ignore if not exists
            }

            // Keep column nullable for flexibility, but enforce cascade on delete
            $table->foreign('qr_code_id')
                ->references('id')
                ->on('qr_codes')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            try {
                $table->dropForeign(['qr_code_id']);
            } catch (\Throwable $e) {
                // ignore
            }

            // Revert to null on delete (previous behavior)
            $table->foreign('qr_code_id')
                ->references('id')
                ->on('qr_codes')
                ->nullOnDelete();
        });
    }
};
