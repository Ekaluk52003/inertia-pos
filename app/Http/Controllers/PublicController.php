<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\Order;
use App\Models\QrCode;
use App\Models\Restaurant;
use Inertia\Inertia;

class PublicController extends Controller
{
    /**
     * Display the public menu for a restaurant via QR code.
     *
     * @param  string  $restaurantCode
     * @param  string  $tableCode
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
            ->get()
            ->map(function ($item) {
                // Ensure we have the full URL for the image path
                if ($item->image_path) {
                    // image_path accessor in the Menu model will handle the URL generation
                    $item->image_path = $item->image_path;
                }

                return $item;
            });

        // Group menu items by category
        $categories = $menuItems->pluck('category')->unique()->values();

        // Prefer orders explicitly tied to this QR code (new behavior). For legacy orders
        // where qr_code_id is null, fall back to table_number but only include orders
        // created after the QR was generated so old tables won't surface.
        $baseQuery = Order::where('restaurant_id', $restaurant->id)
            ->where(function ($q) use ($qrCode) {
                $q->where('qr_code_id', $qrCode->id)
                  ->orWhere(function ($sub) use ($qrCode) {
                      $sub->whereNull('qr_code_id')
                          ->where('table_number', $qrCode->table_number)
                          ->where('created_at', '>=', $qrCode->created_at);
                  });
            });

        // Fetch the most recent unpaid order for this QR/table
        // Note: orders.status column was removed; use is_paid to determine unpaid orders.
        $activeOrder = (clone $baseQuery)
            ->with('qrCode')
            ->where('is_paid', false)
            ->latest()
            ->first();

        // Fetch all orders for this QR/table for order history
        $orderHistory = (clone $baseQuery)
            ->with([
                'qrCode',
                'orderItems' => function ($query) {
                    $query->orderBy('created_at', 'desc');
                }
            ])
            ->latest()
            ->get()
            ->map(function ($order) {
                // Convert orderItems to items for frontend consistency
                $order->items = $order->orderItems->map(function ($item) {
                    // Ensure options are properly formatted
                    if ($item->options && is_array($item->options)) {
                        // Create a new options array with the correct property names
                        $formattedOptions = [];
                        foreach ($item->options as $option) {
                            $formattedOption = $option;
                            if (isset($option['optionName']) && ! isset($option['option_name'])) {
                                $formattedOption['option_name'] = $option['optionName'];
                                unset($formattedOption['optionName']);
                            }
                            if (isset($option['additionalPrice']) && ! isset($option['additional_price'])) {
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
            $activeOrder->load(['orderItems' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }]);

            // Convert orderItems to items for frontend consistency
            $activeOrder->items = $activeOrder->orderItems->map(function ($item) {
                // Ensure options are properly formatted
                if ($item->options && is_array($item->options)) {
                    // Create a new options array with the correct property names
                    $formattedOptions = [];
                    foreach ($item->options as $option) {
                        $formattedOption = $option;
                        if (isset($option['optionName']) && ! isset($option['option_name'])) {
                            $formattedOption['option_name'] = $option['optionName'];
                            unset($formattedOption['optionName']);
                        }
                        if (isset($option['additionalPrice']) && ! isset($option['additional_price'])) {
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
            if ($activeOrder->orderItems && ! isset($activeOrder->items)) {
                $activeOrder->items = $activeOrder->orderItems->map(function ($item) {
                    // Ensure options are properly formatted
                    if ($item->options && is_array($item->options)) {
                        // Create a new options array with the correct property names
                        $formattedOptions = [];
                        foreach ($item->options as $option) {
                            $formattedOption = $option;
                            if (isset($option['optionName']) && ! isset($option['option_name'])) {
                                $formattedOption['option_name'] = $option['optionName'];
                                unset($formattedOption['optionName']);
                            }
                            if (isset($option['additionalPrice']) && ! isset($option['additional_price'])) {
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
            // include table and qr_code info so frontend can react to QR status (billing/checked/etc.)
            'table' => [
                'number' => $qrCode->table_number,
                'code' => $qrCode->code,
                'qr_code' => [
                    'id' => $qrCode->id,
                    'status' => $qrCode->status,
                    'is_active' => (bool) $qrCode->is_active,
                ],
            ],
            // top-level compatibility prop: some pages read page.props.qr_code
            'qr_code' => [
                'id' => $qrCode->id,
                'status' => $qrCode->status,
                'table_number' => $qrCode->table_number,
                'is_active' => (bool) $qrCode->is_active,
            ],
            'menuItems' => $menuItems,
            'orderHistory' => $orderHistory,
            'categories' => $categories,
            'activeOrder' => $activeOrder,
            'cart' => $cart,
        ], $flashData));
    }
}
