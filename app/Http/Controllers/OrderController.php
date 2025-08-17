<?php

namespace App\Http\Controllers;

use App\Events\NewOrder;
use App\Models\Order;
use App\Models\Payment;
use App\Models\QrCode;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function index(Restaurant $restaurant)
    {
        $this->authorize('viewAny', [Order::class, $restaurant]);

        $orders = $restaurant->orders()
            ->with('orderItems')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        // Ensure frontend-friendly camelCase keys exist (orderItems) so Vue can read them as props
        $orders->getCollection()->transform(function ($order) {
            // If relation loaded, set a camelCase attribute for the serializer
            if ($order->relationLoaded('orderItems')) {
                $order->setAttribute('orderItems', $order->orderItems->toArray());
            }

            return $order;
        });

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

        // Load relations we need for the view
        $order->load('orderItems.menuItem', 'payments');

        // Build a normalized array so the frontend always receives predictable keys
        $orderArray = $order->toArray();

        // Normalize order_items -> orderItems (camelCase) if present
        if (isset($orderArray['order_items'])) {
            $orderArray['orderItems'] = $orderArray['order_items'];
        } elseif ($order->relationLoaded('orderItems')) {
            $orderArray['orderItems'] = $order->orderItems->toArray();
        } else {
            $orderArray['orderItems'] = [];
        }

        // Ensure payments key exists and is an array
        if (isset($orderArray['payments']) && is_array($orderArray['payments'])) {
            // already fine
        } elseif ($order->relationLoaded('payments')) {
            $orderArray['payments'] = $order->payments->toArray();
        } else {
            $orderArray['payments'] = [];
        }

        // Debug info for troubleshooting missing items
        $debug = [
            'order_id' => $order->id,
            'order_code' => $order->code ?? null,
            'orderItems_count' => is_array($orderArray['orderItems']) ? count($orderArray['orderItems']) : 0,
            'payments_count' => is_array($orderArray['payments']) ? count($orderArray['payments']) : 0,
            'sample_first_item' => isset($orderArray['orderItems'][0]) ? array_slice($orderArray['orderItems'][0], 0, 6) : null,
        ];

        // Log debug information (will appear in storage/logs/laravel.log)
        Log::debug('OrderController@show debug', $debug);

        $props = [
            'restaurant' => $restaurant,
            'order' => $orderArray,
        ];

        // If the request includes ?debug=1 return debug payload to the frontend for quick inspection
        if (request()->boolean('debug')) {
            $props['debug'] = $debug;
        }

        return Inertia::render('Order/Show', $props);
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

    }

    /**
     * Mark an order as paid.
     */
    public function markAsPaid(Restaurant $restaurant, Order $order)
    {
        $this->authorize('update', $order);

        DB::transaction(function () use ($order, $restaurant) {
            // mark order paid
            $order->update(['is_paid' => true]);

            // create a corresponding payment record; omit sender/trans_ref/sending_bank as requested
            // generate a unique trans_ref (staff-created, non-qrcode) to avoid unique constraint collisions
            $generatedTransRef = 'non-qrcode-'.Str::uuid()->toString();
            $amount = request()->input('amount', $order->total_amount);

            Payment::create([
                'order_id' => $order->id,
                'table_number' => $order->table_number,
                // mark as non-qrcode since this payment was created by staff action
                'trans_ref' => $generatedTransRef,
                'amount' => $amount,
                'sender_name' => null,
                'sender_display_name' => null,
                'sending_bank' => null,
                'restaurant_id' => $restaurant->id,
                'qr_code_id' => null,
                'status' => 'completed',
                'payment_details' => null,
            ]);
        });

        if (request()->wantsJson()) {
            return response()->json(['message' => 'Order marked as paid.']);
        }

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
                })->values(),
            ];
        })->values();

        // Format activeOrders with proper camelCase keys for Vue
        $formattedActiveOrders = $allOrders->map(function ($order) {
            $orderArray = $order->toArray();

            // Ensure orderItems is properly set (camelCase for Vue)
            if (isset($orderArray['order_items'])) {
                $orderArray['orderItems'] = $orderArray['order_items'];
                unset($orderArray['order_items']);
            }

            return $orderArray;
        });

        return Inertia::render('Kitchen/Show', [
            'restaurant' => $restaurant,
            'ordersByTable' => $ordersByTable,
            'activeOrders' => $formattedActiveOrders, // Now properly formatted
        ]);
    }

    /**
     * Create a new order from the public menu (customer-facing).
     */
    public function storeFromMenu(Request $request, $restaurantCode, $tableCode)
    {
        try {

            $qrCode = QrCode::where('code', $tableCode)
                ->where('is_active', true)
                ->firstOrFail();

            $restaurant = $qrCode->restaurant;

        } catch (\Exception $e) {

            throw $e;
        }

        // Build validation rules based on restaurant settings
        $validationRules = [
            'items' => 'required|array',
            'items.*.menu_id' => 'required|exists:menus,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.special_instructions' => 'nullable|string',
            'items.*.selected_options' => 'nullable|array',
            'customer_notes' => 'nullable|string',
        ];

        // Only require payment verification for restaurants with pay_before enabled
        if ($restaurant->pay_before) {
            $validationRules['slip_image'] = 'required_without:qr_code_data|string|nullable';
            $validationRules['qr_code_data'] = 'required_without:slip_image|string|nullable';
        }

        $validated = $request->validate($validationRules);

        // Calculate total amount (includes base price + all selected options)
        $totalAmount = 0;
        $orderItems = [];

        foreach ($validated['items'] as $item) {
            try {
                $menuItem = $restaurant->menuItems()->findOrFail($item['menu_id']);

                $quantity = (int) $item['quantity'];
                $baseUnitPrice = (float) $menuItem->price;

                // Securely recompute additional option price; do not trust client-sent aggregated price
                $selectedOptionsInput = isset($item['selected_options']) && is_array($item['selected_options'])
                    ? $item['selected_options']
                    : [];

                $computedAdditionalPerUnit = 0.0;
                $normalizedSelectedOptions = [];

                if (! empty($selectedOptionsInput)) {
                    // Index option groups by name for quick lookup
                    $optionGroups = [];
                    if (is_array($menuItem->options)) {
                        foreach ($menuItem->options as $group) {
                            if (isset($group['name'])) {
                                $optionGroups[$group['name']] = $group;
                            }
                        }
                    }

                    foreach ($selectedOptionsInput as $selOpt) {
                        $optionName = $selOpt['option_name'] ?? null;
                        $choices = isset($selOpt['choices']) && is_array($selOpt['choices']) ? $selOpt['choices'] : [];
                        if (! $optionName) {
                            continue; // skip invalid
                        }
                        $group = $optionGroups[$optionName] ?? null;
                        $additionalForThisOption = 0.0;
                        if ($group) {
                            // Support both 'values' and 'choices'
                            $valueList = [];
                            if (isset($group['values']) && is_array($group['values'])) {
                                $valueList = $group['values'];
                            } elseif (isset($group['choices']) && is_array($group['choices'])) {
                                $valueList = $group['choices'];
                            }
                            foreach ($choices as $choiceName) {
                                foreach ($valueList as $val) {
                                    if (($val['name'] ?? null) === $choiceName) {
                                        $additionalForThisOption += (float) ($val['price'] ?? 0);
                                        break;
                                    }
                                }
                            }
                        }
                        $computedAdditionalPerUnit += $additionalForThisOption;
                        $normalizedSelectedOptions[] = [
                            'option_name' => $optionName,
                            'choices' => $choices,
                            'additional_price' => $additionalForThisOption, // recomputed, not trusted from client
                        ];
                    }
                }

                $finalUnitPrice = $baseUnitPrice + $computedAdditionalPerUnit;
                $lineTotal = $finalUnitPrice * $quantity;
                // Debug: log per-item price calculation to help trace mismatches
                Log::debug('OrderController@storeFromMenu price calc', [
                    'menu_id' => $menuItem->id,
                    'menu_name' => $menuItem->name ?? null,
                    'base_unit_price' => $baseUnitPrice,
                    'computed_additional_per_unit' => $computedAdditionalPerUnit,
                    'final_unit_price' => $finalUnitPrice,
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                    'selected_options_input' => $selectedOptionsInput,
                    'normalized_selected_options' => $normalizedSelectedOptions,
                ]);
                $totalAmount += $lineTotal; // totalAmount includes all base prices + option prices

                $orderItems[] = [
                    'menu_id' => $menuItem->id,
                    'name' => $menuItem->name,
                    'quantity' => $quantity,
                    'price' => $finalUnitPrice, // store unit price including options
                    'status' => 'pending',
                    'special_instructions' => $item['special_instructions'] ?? null,
                    'options' => $normalizedSelectedOptions,
                ];

            } catch (\Exception $e) {
                Log::error('Error processing menu item:', [
                    'item' => $item,
                    'error' => $e->getMessage(),
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

                    // Check if we're in development mode
                    $devMode = config('services.slipok.dev_mode', false);
                    $okUse = config('services.slipok.ok_use', true);

                    if ($devMode || ! $okUse) {
                        Log::info('SLIPOK SIMULATION: Bypassing external API (dev mode or SLIPOK_OK_USE=false).');

                        $slipVerification = [
                            'transRef' => 'DEV_'.\Illuminate\Support\Str::uuid()->toString(),
                            'amount' => $totalAmount,
                            'sender' => [
                                'name' => 'DEV MODE',
                                'displayName' => 'Development Test',
                            ],
                            'sendingBank' => 'DEV BANK',
                        ];
                    } else {
                        // Real SlipOK API call
                        $apiKey = config('services.slipok.api_key');
                        $branchId = config('services.slipok.branch_id');

                        if (! $apiKey || ! $branchId) {
                            throw new \Exception('SlipOK credentials not configured.');
                        }

                        $url = "https://api.slipok.com/api/line/apikey/{$branchId}";
                        $response = null;

                        if (filter_var($paymentData, FILTER_VALIDATE_URL)) {
                            // Remote image URL
                            $response = Http::withHeaders([
                                'x-authorization' => $apiKey,
                                'Content-Type' => 'application/json',
                            ])->withOptions(['verify' => false]) // TODO: Remove in production - use proper SSL certificates
                                ->post($url, [
                                    'url' => $paymentData,
                                    'log' => true,
                                    'amount' => $totalAmount,
                                ]);
                        } elseif (str_starts_with($paymentData, 'data:image')) {
                            // Base64 data URL image - convert to file
                            $imageData = substr($paymentData, strpos($paymentData, ',') + 1);
                            $binary = base64_decode($imageData);
                            if ($binary === false) {
                                throw new \Exception('Invalid base64 slip image data.');
                            }
                            $response = Http::withHeaders(['x-authorization' => $apiKey])
                                ->withOptions(['verify' => false]) // TODO: Remove in production - use proper SSL certificates
                                ->attach('files', $binary, 'slip.jpg')
                                ->post($url, [
                                    'log' => true,
                                    'amount' => $totalAmount,
                                ]);
                        } else {
                            // QR raw text data
                            $response = Http::withHeaders([
                                'x-authorization' => $apiKey,
                                'Content-Type' => 'application/json',
                            ])->withOptions(['verify' => false]) // TODO: Remove in production - use proper SSL certificates
                                ->post($url, [
                                    'data' => $paymentData,
                                    'log' => true,
                                    'amount' => $totalAmount,
                                ]);
                        }

                        Log::info('SlipOK raw response', [
                            'status' => $response ? $response->status() : null,
                            'body' => $response ? $response->body() : null,
                        ]);

                        if (! $response || ! $response->successful()) {
                            $body = $response ? $response->json() : [];
                            $msg = $body['message'] ?? 'SlipOK request failed';
                            $code = $body['code'] ?? 'UNKNOWN';
                            throw new \Exception("SlipOK API Error ($code): $msg");
                        }

                        $json = $response->json();
                        if (! ($json['success'] ?? false)) {
                            $msg = $json['message'] ?? 'Slip verification failed';
                            $code = $json['code'] ?? 'INVALID';
                            throw new \Exception("SlipOK Verification Error ($code): $msg");
                        }

                        $slipVerification = $json['data'] ?? [];
                        if (isset($slipVerification['amount']) && (float) $slipVerification['amount'] !== (float) $totalAmount) {
                            throw new \Exception('Amount mismatch: slip shows '.$slipVerification['amount'].' expected '.$totalAmount);
                        }
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
                    return redirect()->route('public.menu', [
                        'restaurantCode' => $restaurantCode,
                        'tableCode' => $tableCode,
                    ])->withErrors([
                        isset($validated['slip_image']) ? 'slip_image' : 'qr_code_data' => 'Payment verification failed: '.$e->getMessage(),
                    ])->with([
                        'cart' => $validated['items'],
                    ]);
                }
            }
        }

        // Create the order and payment in a transaction
        DB::beginTransaction();
        try {


            try {
                // Sanity check: recompute total from built orderItems and compare to $totalAmount
                $computedFromItems = 0;
                foreach ($orderItems as $oi) {
                    $computedFromItems += ((float) ($oi['price'] ?? 0)) * ((int) ($oi['quantity'] ?? 1));
                }
                if (abs($computedFromItems - $totalAmount) > 0.001) {
                    Log::warning('OrderController@storeFromMenu total mismatch', [
                        'totalAmount' => $totalAmount,
                        'computedFromItems' => $computedFromItems,
                        'orderItems' => $orderItems,
                    ]);
                }

                $orderData = [
                    'table_number' => $qrCode->table_number,
                    'code' => Str::uuid()->toString(),
                    'total_amount' => $totalAmount,
                    'is_paid' => $isPaid,
                    'status' => 'pending',
                    'customer_notes' => $validated['customer_notes'] ?? null,
                ];

                $order = $restaurant->orders()->create($orderData);

                // Persist order items (we built $orderItems earlier)
                if (! empty($orderItems)) {
                    // Ensure menu_id and options shapes are compatible with OrderItem fillable/casts
                    $order->orderItems()->createMany($orderItems);
                }

            } catch (\Exception $e) {
                throw $e;
            }

            // Create payment record if payment was verified
            if ($paymentData) {
                $paymentData['order_id'] = $order->id;
                $payment = Payment::create($paymentData);
            }

            DB::commit();

            // Broadcast new order event
            broadcast(new NewOrder($order))->toOthers();

            // Load order relationships for the flash data
            $order->load('orderItems', 'payments');

            // Redirect to the public menu (cannot redirect back to POST-only route)
            return redirect()->route('public.menu', [
                'restaurantCode' => $restaurantCode,
                'tableCode' => $tableCode,
            ])->with([
                'success' => 'Order created successfully',
                'activeOrder' => $order,
                'flash_order_created' => true,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            // Redirect back to the menu page with error flash message
            return redirect()->route('public.menu', [
                'restaurantCode' => $restaurantCode,
                'tableCode' => $tableCode,
            ])->with([
                'error' => 'Failed to create order: '.$e->getMessage(),
                'cart' => $validated['items'],
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
                'code' => $orderCode,
            ],
            'activeOrder' => $order,
            'menuItems' => $order->restaurant->menuItems,
            'categories' => $order->restaurant->menuItems->pluck('category')->unique(),
        ]);
    }
}
