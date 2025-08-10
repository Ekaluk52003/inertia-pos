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
        // Add foreign key constraint to restaurants table for owner_id
        Schema::table('restaurants', function (Blueprint $table) {
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Add foreign key constraint to users table for restaurant_id
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('restaurant_id')->references('id')->on('restaurants')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove foreign key constraint from restaurants table
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
        });

        // Remove foreign key constraint from users table
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['restaurant_id']);
        });
    }
};
