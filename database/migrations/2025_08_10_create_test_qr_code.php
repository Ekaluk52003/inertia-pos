<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Restaurant;
use App\Models\QrCode;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get the first restaurant
        $restaurant = Restaurant::first();
        
        if ($restaurant) {
            // Create a test QR code for the restaurant
            QrCode::create([
                'restaurant_id' => $restaurant->id,
                'table_number' => 1,
                'code' => 'TEST123',
                'is_active' => true,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Delete the test QR code
        QrCode::where('code', 'TEST123')->delete();
    }
};
