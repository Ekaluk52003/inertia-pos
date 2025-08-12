<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Payment;
use App\Models\QrCode;
use App\Events\NewOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Http\RedirectResponse;

class OrderController extends Controller
{
    /**
                    
     */
    public function index(Restaurant $restaurant)
    {
        $this->authorize('viewAny', [Order::class, $restaurant]);

        $orders = $restaurant->orders()
            ->with('orderItems')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Order/Index', [
            'restaurant' => $restaurant,
            'orders' => $orders,
        ]);
    }

    /**
     * Display the specified order.
     */
    public function show(Restaurant $restaurant, Order $order)
    {
        $this->authorize('view', $order);

        $order->load('orderItems.menuItem', 'payments');

        return Inertia::render('Order/Show', [
            'restaurant' => $restaurant,
            'order' => $order,
        ]);
    }

    /**
     * Update the status of an order item.
     */
    public function updateItemStatus(Request $request, Restaurant $restaurant, Order $order)
    {
        $this->authorize('update', $order);

        $validated = $request->validate([
            'order_item_id' => 'required|exists:order_items,id',
            'status' => 'required|in:pending,cooking,ready,served',
        ]);

        $orderItem = $order->orderItems()->findOrFail($validated['order_item_id']);
        $orderItem->update(['status' => $validated['status']]);

        return back()->with('success', 'Order item status updated.');
    }

    /**
     * Mark an order as paid.
     */
    public function markAsPaid(Restaurant $restaurant, Order $order)
    {
        $this->authorize('update', $order);

        $order->update(['is_paid' => true]);

        return back()->with('success', 'Order marked as paid.');
    }

    /**
     * Display the kitchen view for a restaurant.
     */
    public function kitchenView(Restaurant $restaurant)
    {
        

        // Get all orders without any filtering first
        $allOrders = $restaurant->orders()
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get();
            
   
        // Now load the order items separately
        $allOrders->load('orderItems');       
        
      
        
        // Group orders by table number and ensure proper camelCase keys for Vue
        $ordersByTable = $allOrders->groupBy('table_number')->map(function ($tableOrders) {
            return [
                'table_number' => $tableOrders->first()->table_number,
                'orders' => $tableOrders->map(function ($order) {
                    // Convert the order to an array
                    $orderArray = $order->toArray();
                    
                    // Ensure orderItems is properly set (camelCase for Vue)
                    if (isset($orderArray['order_items'])) {
                        $orderArray['orderItems'] = $orderArray['order_items'];
                        unset($orderArray['order_items']);
                    }
                    
                    return $orderArray;
                })->values()
            ];
        })->values();
       

        return Inertia::render('Kitchen/Show', [
            'restaurant' => $restaurant,
            'ordersByTable' => $ordersByTable,
            'activeOrders' => $allOrders, // Keep for backward compatibility
        ]);
    }

    /**
     * Create a new order from the public menu (customer-facing).
     */
    public function storeFromMenu(Request $request, $restaurantCode, $tableCode)
    {
        try {
            // Debug incoming request
            Log::info('Order request received:', [
                'request_data' => $request->all(),
                'restaurant_code' => $restaurantCode,
                'table_code' => $tableCode
            ]);

            $qrCode = QrCode::where('code', $tableCode)
                ->where('is_active', true)
                ->firstOrFail();

            Log::info('QR Code found:', ['qr_code' => $qrCode->toArray()]);

            $restaurant = $qrCode->restaurant;
            Log::info('Restaurant found:', ['restaurant' => $restaurant->toArray()]);
        } catch (\Exception $e) {
            Log::error('Error in initial setup:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }

        // Build validation rules based on restaurant settings
        $validationRules = [
            'items' => 'required|array',
            'items.*.menu_id' => 'required|exists:menus,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.special_instructions' => 'nullable|string',
            'customer_notes' => 'nullable|string'
        ];

        // Only require payment verification for restaurants with pay_before enabled
        if ($restaurant->pay_before) {
            $validationRules['slip_image'] = 'required_without:qr_code_data|string|nullable';
            $validationRules['qr_code_data'] = 'required_without:slip_image|string|nullable';
        }

        $validated = $request->validate($validationRules);

        // Calculate total amount
        $totalAmount = 0;
        $orderItems = [];

        foreach ($validated['items'] as $item) {
            try {
                Log::info('Processing menu item:', ['item' => $item]);
                
                $menuItem = $restaurant->menuItems()->findOrFail($item['menu_id']);
                Log::info('Found menu item:', ['menu_item' => $menuItem->toArray()]);
                
                $totalAmount += $menuItem->price * $item['quantity'];

                $orderItems[] = [
                    'menu_id' => $menuItem->id,
                    'name' => $menuItem->name,
                    'quantity' => $item['quantity'],
                    'price' => $menuItem->price,
                    'status' => 'pending',
                    'special_instructions' => $item['special_instructions'] ?? null,
                ];
                
                Log::info('Order item prepared:', ['order_item' => end($orderItems)]);
            } catch (\Exception $e) {
                Log::error('Error processing menu item:', [
                    'item' => $item,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        }

        // Handle payment status based on restaurant settings
        $isPaid = false;
        $paymentData = null;

        if ($restaurant->pay_before) {
            // Only verify payment if payment data is provided
            if (isset($validated['slip_image']) || isset($validated['qr_code_data'])) {
                try {
                $paymentData = $validated['slip_image'] ?? $validated['qr_code_data'];

                // Call SlipOK API service for verification
                $slipVerification = app(\App\Services\SlipOkService::class)->verifyPayment(
                    $paymentData,
                    $totalAmount
                );

                // Check if payment has already been used
                $existingPayment = Payment::where('trans_ref', $slipVerification['transRef'])->first();
                if ($existingPayment) {
                    $restaurant->load('menuItems');

                    return Inertia::render('Customer', [
                        'restaurant' => [
                            'id' => $restaurant->id,
                            'name' => $restaurant->name,
                            'description' => $restaurant->description,
                            'payBefore' => $restaurant->pay_before,
                            'menuItems' => $restaurant->menuItems,
                        ],
                        'table' => [
                            'number' => $qrCode->table_number,
                            'code' => $tableCode
                        ],
                        'error' => 'This payment has already been used.',
                        'menuItems' => $restaurant->menuItems,
                        'categories' => $restaurant->menuItems->pluck('category')->unique()->values()->all(),
                        'cart' => $validated['items']
                    ]);
                }

                $paymentData = [
                    'table_number' => $qrCode->table_number,
                    'amount' => $totalAmount,
                    'trans_ref' => $slipVerification['transRef'],
                    'sender_name' => $slipVerification['sender']['name'] ?? null,
                    'sender_display_name' => $slipVerification['sender']['displayName'] ?? null,
                    'sending_bank' => $slipVerification['sendingBank'] ?? null,
                    'restaurant_id' => $restaurant->id,
                    'qr_code_id' => $qrCode->id,
                ];

                $isPaid = true;
            } catch (\Exception $e) {
                $restaurant->load('menuItems');

                return Inertia::render('Customer', [
                    'restaurant' => [
                        'id' => $restaurant->id,
                        'name' => $restaurant->name,
                        'description' => $restaurant->description,
                        'payBefore' => $restaurant->pay_before,
                        'menuItems' => $restaurant->menuItems,
                    ],
                    'table' => [
                        'number' => $qrCode->table_number,
                        'code' => $tableCode
                    ],
                    'error' => 'Payment verification failed: ' . $e->getMessage(),
                    'menuItems' => $restaurant->menuItems,
                    'categories' => $restaurant->menuItems->pluck('category')->unique()->values()->all(),
                    'cart' => $validated['items']
                ]);
                }
            }
        }

        // Create the order and payment in a transaction
        DB::beginTransaction();
        try {
            Log::info('Creating order with data:', [
                'table_number' => $qrCode->table_number,
                'total_amount' => $totalAmount,
                'is_paid' => $isPaid,
                'customer_notes' => $validated['customer_notes'] ?? null,
                'order_items' => $orderItems
            ]);

            try {
                $orderData = [
                    'table_number' => $qrCode->table_number,
                    'code' => Str::uuid()->toString(),
                    'total_amount' => $totalAmount,
                    'is_paid' => $isPaid,
                    'status' => 'pending',
                    'customer_notes' => $validated['customer_notes'] ?? null,
                ];
                
                Log::info('Attempting to create order with:', ['order_data' => $orderData]);
                
                $order = $restaurant->orders()->create($orderData);
                
                Log::info('Order created successfully:', ['order' => $order->toArray()]);
            } catch (\Exception $e) {
                Log::error('Failed to create order:', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'order_data' => $orderData ?? null
                ]);
                throw $e;
            }

            // Create order items
            $createdItems = $order->orderItems()->createMany($orderItems);
            Log::info('Order items created:', ['items' => $createdItems->toArray()]);

            // Create payment record if payment was verified
            if ($paymentData) {
                $paymentData['order_id'] = $order->id;
                $payment = Payment::create($paymentData);
                Log::info('Payment record created:', ['payment' => $payment->toArray()]);
            }

            DB::commit();

            // Broadcast new order event
            broadcast(new NewOrder($order))->toOthers();

            // Load order relationships for the flash data
            $order->load('orderItems', 'payments');
            
            // Redirect back to the menu page with a flash message
            return Redirect::back()->with([
                'success' => 'Order created successfully',
                'activeOrder' => $order,
                'flash_order_created' => true
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order creation failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Redirect back to the menu page with error flash message
            return redirect()->route('public.menu', [
                'restaurantCode' => $restaurantCode,
                'tableCode' => $tableCode
            ])->with([
                'error' => 'Failed to create order: ' . $e->getMessage(),
                'cart' => $validated['items']
            ]);
        }
    }

    /**
     * Get the status of an order (for customer tracking).
     */
    public function getOrderStatus($orderCode)
    {
        $order = Order::where('code', $orderCode)
            ->with(['orderItems', 'restaurant'])
            ->firstOrFail();

        return Inertia::render('Customer', [
            'restaurant' => $order->restaurant,
            'table' => [
                'number' => $order->table_number,
                'code' => $orderCode
            ],
            'activeOrder' => $order,
            'menuItems' => $order->restaurant->menuItems,
            'categories' => $order->restaurant->menuItems->pluck('category')->unique()
        ]);
    }
}
