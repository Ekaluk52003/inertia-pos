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
            // Drop foreign key if exists and then drop the column
            if (Schema::hasColumn('payments', 'order_id')) {
                // Attempt to drop FK if it exists; name may vary so use doctrine if possible
                try {
                    $table->dropForeign(['order_id']);
                } catch (\Throwable $e) {
                    // Ignore - foreign key may not exist or has a different name
                }

                $table->dropColumn('order_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'order_id')) {
                $table->foreignId('order_id')->nullable()->constrained()->onDelete('cascade');
            }
        });
    }
};
