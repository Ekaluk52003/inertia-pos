<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use App\Models\QrCode;
use App\Models\Menu;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicController extends Controller
{
    /**
     * Display the public menu for a restaurant via QR code.
     *
     * @param string $restaurantCode
     * @param string $tableCode
     * @return \Inertia\Response
     */
    public function publicMenu($restaurantCode, $tableCode)
    {
        // Find the QR code by its unique code
        $qrCode = QrCode::where('code', $tableCode)
            ->where('is_active', true)
            ->firstOrFail();
        
        // Get the associated restaurant
        $restaurant = $qrCode->restaurant;
        
        // Verify the restaurant code matches
        if ($restaurant->id != $restaurantCode) {
            abort(404, 'Invalid restaurant code');
        }
        
        // Get menu items for the restaurant
        $menuItems = $restaurant->menuItems()
            ->where('is_available', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();
        
        // Group menu items by category
        $categories = $menuItems->pluck('category')->unique()->values();
        
        // Check if there's an active order for this table
        $activeOrder = Order::where('restaurant_id', $restaurant->id)
            ->where('table_number', $qrCode->table_number)
            ->where('is_paid', false)
            ->latest()
            ->first();
        
        return Inertia::render('Customer', [
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'description' => $restaurant->description,
                'payBefore' => $restaurant->pay_before,
            ],
            'table' => [
                'number' => $qrCode->table_number,
                'code' => $qrCode->code,
            ],
            'menuItems' => $menuItems,
            'categories' => $categories,
            'activeOrder' => $activeOrder,
        ]);
    }
}
