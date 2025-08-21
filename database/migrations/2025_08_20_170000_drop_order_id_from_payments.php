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
        // Only attempt to drop the column if it exists to avoid breaking test DBs
        if (Schema::hasColumn('payments', 'order_id')) {
            Schema::table('payments', function (Blueprint $table) {
                // Attempt to drop foreign key constraint (uses conventional name)
                try {
                    $table->dropForeign(['order_id']);
                } catch (\Exception $e) {
                    // ignore if foreign key does not exist
                }

                // Drop the column
                try {
                    $table->dropColumn('order_id');
                } catch (\Exception $e) {
                    // ignore failures (some DB drivers / sqlite may behave differently)
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate nullable order_id with FK for rollback safety
        if (! Schema::hasColumn('payments', 'order_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->unsignedBigInteger('order_id')->nullable()->after('id');
                $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            });
        }
    }
};
