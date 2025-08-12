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
        
        // Fetch the most recent active order for this table
        $activeOrder = Order::where('restaurant_id', $restaurant->id)
            ->where('table_number', $qrCode->table_number)
            ->where('is_paid', false)
            ->latest()
            ->first();
            
        // Fetch all orders for this table for order history
        $orderHistory = Order::where('restaurant_id', $restaurant->id)
            ->where('table_number', $qrCode->table_number)
            ->with(['orderItems' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])
            ->latest()
            ->get()
            ->map(function($order) {
                // Convert orderItems to order_items for frontend consistency
                $order->order_items = $order->orderItems;
                return $order;
            });
            
        // Load the active order's items if it exists
        if ($activeOrder) {
            $activeOrder->load(['orderItems' => function($query) {
                $query->orderBy('created_at', 'desc');
            }]);
            
            // Convert orderItems to order_items for frontend consistency
            $activeOrder->order_items = $activeOrder->orderItems;
        }
        
        // Check for flash messages from the order controller
        $flashData = [];
        
        if (session('success')) {
            $flashData['success'] = session('success');
        }
        
        if (session('error')) {
            $flashData['error'] = session('error');
        }
        
        // If we have a flashed activeOrder from a new order submission, use that instead
        if (session('activeOrder')) {
            $activeOrder = session('activeOrder');
            // Make sure we have order_items for frontend consistency
            if ($activeOrder->orderItems && !isset($activeOrder->order_items)) {
                $activeOrder->order_items = $activeOrder->orderItems;
            }
        }
        
        // If we have flashed cart items from a failed order, pass them to the frontend
        $cart = session('cart') ?? [];
        
        return Inertia::render('Customer', array_merge([
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
            'orderHistory' => $orderHistory,
            'categories' => $categories,
            'activeOrder' => $activeOrder,
            'cart' => $cart,
        ], $flashData));
    }
}
