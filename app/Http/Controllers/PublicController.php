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
                // Convert orderItems to items for frontend consistency
                $order->items = $order->orderItems->map(function($item) {
                    // Ensure options are properly formatted
                    if ($item->options && is_array($item->options)) {
                        // Create a new options array with the correct property names
                        $formattedOptions = [];
                        foreach ($item->options as $option) {
                            $formattedOption = $option;
                            if (isset($option['optionName']) && !isset($option['option_name'])) {
                                $formattedOption['option_name'] = $option['optionName'];
                                unset($formattedOption['optionName']);
                            }
                            if (isset($option['additionalPrice']) && !isset($option['additional_price'])) {
                                $formattedOption['additional_price'] = $option['additionalPrice'];
                                unset($formattedOption['additionalPrice']);
                            }
                            $formattedOptions[] = $formattedOption;
                        }
                        // Set the formatted options on the item
                        $item->options = $formattedOptions;
                    }
                    return $item;
                });
                return $order;
            });
            
        // Load the active order's items if it exists
        if ($activeOrder) {
            $activeOrder->load(['orderItems' => function($query) {
                $query->orderBy('created_at', 'desc');
            }]);
            
            // Convert orderItems to items for frontend consistency
            $activeOrder->items = $activeOrder->orderItems->map(function($item) {
                // Ensure options are properly formatted
                if ($item->options && is_array($item->options)) {
                    // Create a new options array with the correct property names
                    $formattedOptions = [];
                    foreach ($item->options as $option) {
                        $formattedOption = $option;
                        if (isset($option['optionName']) && !isset($option['option_name'])) {
                            $formattedOption['option_name'] = $option['optionName'];
                            unset($formattedOption['optionName']);
                        }
                        if (isset($option['additionalPrice']) && !isset($option['additional_price'])) {
                            $formattedOption['additional_price'] = $option['additionalPrice'];
                            unset($formattedOption['additionalPrice']);
                        }
                        $formattedOptions[] = $formattedOption;
                    }
                    // Set the formatted options on the item
                    $item->options = $formattedOptions;
                }
                return $item;
            });
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
            // Make sure we have items for frontend consistency
            if ($activeOrder->orderItems && !isset($activeOrder->items)) {
                $activeOrder->items = $activeOrder->orderItems->map(function($item) {
                    // Ensure options are properly formatted
                    if ($item->options && is_array($item->options)) {
                        // Create a new options array with the correct property names
                        $formattedOptions = [];
                        foreach ($item->options as $option) {
                            $formattedOption = $option;
                            if (isset($option['optionName']) && !isset($option['option_name'])) {
                                $formattedOption['option_name'] = $option['optionName'];
                                unset($formattedOption['optionName']);
                            }
                            if (isset($option['additionalPrice']) && !isset($option['additional_price'])) {
                                $formattedOption['additional_price'] = $option['additionalPrice'];
                                unset($formattedOption['additionalPrice']);
                            }
                            $formattedOptions[] = $formattedOption;
                        }
                        // Set the formatted options on the item
                        $item->options = $formattedOptions;
                    }
                    return $item;
                });
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
                'promptPayId' => $restaurant->prompt_pay_id,
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
