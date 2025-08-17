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
            $table->string('table_number')->nullable()->after('order_id');
            $table->string('sender_display_name')->nullable()->after('sender_name');
            $table->string('sending_bank')->nullable()->after('sender_display_name');
            $table->foreignId('restaurant_id')->nullable()->constrained()->after('sending_bank');
            $table->foreignId('qr_code_id')->nullable()->constrained()->after('restaurant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['qr_code_id']);
            $table->dropForeign(['restaurant_id']);
            $table->dropColumn([
                'table_number',
                'sender_display_name',
                'sending_bank',
                'restaurant_id',
                'qr_code_id',
            ]);
        });
    }
};
