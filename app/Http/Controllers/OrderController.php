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

        // We'll provide a table-centric aggregation: group active orders by table_number and compute totals
        $ordersQuery = $restaurant->orders()->with('orderItems')->orderBy('created_at', 'desc');

        // Paginate flat orders to keep existing pagination controls, but we will derive table aggregates from a limited window
        $paginated = $ordersQuery->paginate(50);

        $allOrders = $paginated->getCollection();

        // Preload QR codes for the tables in the paginated window to surface table-level status
        $tableNumbers = $allOrders->pluck('table_number')->unique()->filter()->values()->all();
        $qrCodes = QrCode::where('restaurant_id', $restaurant->id)
            ->whereIn('table_number', $tableNumbers ?: ['-1'])
            ->get()
            ->keyBy('table_number');

        // Compute aggregates per table_number
        $tables = $allOrders->groupBy('table_number')->map(function ($tableOrders, $tableNumber) use ($qrCodes) {
            $ordersArr = $tableOrders->map(function ($order) {
                $orderArray = $order->toArray();
                if (isset($orderArray['order_items'])) {
                    $orderArray['orderItems'] = $orderArray['order_items'];
                    unset($orderArray['order_items']);
                }

                return $orderArray;
            })->values();

            $total = $ordersArr->reduce(function ($sum, $o) {
                return $sum + ((float) ($o['total_amount'] ?? 0));
            }, 0);

            $ordersCount = $ordersArr->count();

            // Last activity is the most recent order created_at
            $lastActivity = $ordersArr->first()['created_at'] ?? null;

            // Determine table status from QR code if available, otherwise derive from orders
            $status = 'active';
            if (isset($qrCodes[$tableNumber])) {
                $status = $qrCodes[$tableNumber]->status ?? 'active';
            } else {
                if ($ordersArr->firstWhere('status', 'billing')) {
                    $status = 'billing';
                } elseif ($ordersArr->firstWhere('status', 'billed')) {
                    $status = 'billed';
                }
            }

            // Compute whether any order is unpaid
            $isPaid = $ordersArr->every(fn ($o) => ($o['is_paid'] ?? false) === true);

            return [
                'table_number' => $tableNumber,
                'orders' => $ordersArr,
                'orders_count' => $ordersCount,
                'total_amount' => $total,
                'last_activity' => $lastActivity,
                'status' => $status,
                'is_paid' => $isPaid,
            ];
        })->values();

        return Inertia::render('Order/Index', [
            'restaurant' => $restaurant,
            // Keep existing pagination meta to avoid breaking the UI; frontend will use `tables` instead of `orders.data`
            'orders' => $paginated,
            'tables' => $tables,
        ]);
    }

    /**
     * Display the specified order.
     */
    public function show(Restaurant $restaurant, Order $order)
    {
        $this->authorize('view', $order);

        // Load relations we need for the view
        $order->load('orderItems.menuItem');

        // Safely attempt to load payments — the payments table may no longer have an order_id
        try {
            $order->setRelation('payments', $order->payments()->get());
        } catch (\Illuminate\Database\QueryException $e) {
            Log::warning('OrderController@show could not eager load payments', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            $order->setRelation('payments', collect([]));
        }

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

        // If this order has a table_number, include all active orders for the same table so the frontend can list them
        if (! empty($order->table_number)) {
            $tableOrders = $restaurant->orders()
                ->where('table_number', $order->table_number)
                ->with('orderItems')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($o) {
                    $arr = $o->toArray();
                    if (isset($arr['order_items'])) {
                        $arr['orderItems'] = $arr['order_items'];
                        unset($arr['order_items']);
                    }

                    return $arr;
                })->values();

            $props['tableOrders'] = $tableOrders;
        } else {
            $props['tableOrders'] = collect([]);
        }

        // If the request includes ?debug=1 return debug payload to the frontend for quick inspection
        if (request()->boolean('debug')) {
            $props['debug'] = $debug;
        }

        return Inertia::render('Order/Show', $props);
    }

    /**
     * Display all orders for a table (dedicated table details page).
     */
    public function tableShow(Restaurant $restaurant, $tableNumber)
    {
        $this->authorize('viewAny', [Order::class, $restaurant]);

        // Eager load menu item details for each order item and any payments
        $tableOrders = $restaurant->orders()
            ->where('table_number', $tableNumber)
            ->with(['orderItems.menuItem'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($o) {
                $arr = $o->toArray();

                // Normalize order_items -> orderItems
                if (isset($arr['order_items'])) {
                    $orderItems = collect($arr['order_items'])->map(function ($it) {
                        // Normalize nested menu_item -> menuItem if present
                        if (isset($it['menu_item'])) {
                            $it['menuItem'] = $it['menu_item'];
                            unset($it['menu_item']);
                        }

                        return $it;
                    })->values()->all();

                    $arr['orderItems'] = $orderItems;
                    unset($arr['order_items']);
                } else {
                    $arr['orderItems'] = [];
                }

                // Ensure payments key exists. Try to load via relationship if not present.
                if (! isset($arr['payments']) || ! is_array($arr['payments'])) {
                    try {
                        $arr['payments'] = $o->payments()->get()->toArray();
                    } catch (\Illuminate\Database\QueryException $e) {
                        Log::warning('OrderController@tableShow could not load payments for order', [
                            'order_id' => $o->id,
                            'error' => $e->getMessage(),
                        ]);
                        $arr['payments'] = [];
                    }
                }

                return $arr;
            })->values();

        // Include QR code/table metadata if available so the frontend has table status
        $qrCode = QrCode::where('restaurant_id', $restaurant->id)
            ->where('table_number', $tableNumber)
            ->first();

        return Inertia::render('Order/TableShow', [
            'restaurant' => $restaurant,
            'table_number' => $tableNumber,
            'qr_code' => $qrCode ? $qrCode->toArray() : null,
            'tableOrders' => $tableOrders,
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

        try {
            $validated = $request->validate($validationRules);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Log validation failures for debugging tests
            Log::debug('OrderController@storeFromMenu validation failed', [
                'errors' => $e->errors(),
                'input' => $request->all(),
            ]);
            throw $e;
        }

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
                    'status' => 'active',
                    'customer_notes' => $validated['customer_notes'] ?? null,
                ];

                $order = $restaurant->orders()->create($orderData);

                // Debug: confirm order creation in logs for test troubleshooting
                Log::debug('OrderController@storeFromMenu created order', [
                    'order_id' => $order->id ?? null,
                    'restaurant_id' => $restaurant->id ?? null,
                    'table_number' => $order->table_number ?? null,
                    'total_amount' => $order->total_amount ?? null,
                ]);

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
                // Payments are now table-level; do not set order_id here
                $payment = Payment::create($paymentData);
            }

            DB::commit();

            // Log commit so tests can verify commit occurred (debugging flaky test)
            Log::debug('OrderController@storeFromMenu committed', [
                'order_id' => $order->id ?? null,
                'restaurant_id' => $restaurant->id ?? null,
                'table_number' => $order->table_number ?? null,
                'total_amount' => $order->total_amount ?? null,
            ]);

            // Broadcast new order event
            broadcast(new NewOrder($order))->toOthers();

            // Load order relationships for the flash data. Loading payments may fail
            // on test DBs where the payments.order_id column was dropped; load payments
            // defensively so we don't trigger a QueryException and rollback after commit.
            $order->load('orderItems');
            try {
                $order->load('payments');
            } catch (\Illuminate\Database\QueryException $e) {
                Log::warning('OrderController@storeFromMenu could not eager load payments', [
                    'order_id' => $order->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }

            // Extra debug: log loaded relations (payments_count may be unavailable)
            Log::debug('OrderController@storeFromMenu loaded relations', [
                'order_id' => $order->id ?? null,
                'orderItems_count' => $order->orderItems ? $order->orderItems->count() : 0,
                'payments_count' => isset($order->payments) && is_countable($order->payments) ? count($order->payments) : null,
            ]);

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

            // Log the exception for debugging
            Log::error('OrderController@storeFromMenu exception - rolling back', [
                'message' => $e->getMessage(),
                'exception' => $e,
                'input' => $request->all(),
            ]);

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
     * Verify a slip image or QR code data without creating an order.
     * Intended for immediate verification after customer uploads a slip.
     */
    public function verifySlip(Request $request)
    {
        $validated = $request->validate([
            // Accept either an uploaded image file OR a qr_code_data string
            'slip_image' => 'required_without:qr_code_data|nullable|file|image|max:5120', // 5MB
            'qr_code_data' => 'required_without:slip_image|nullable|string',
        ]);

        $hasFile = $request->hasFile('slip_image');
        $paymentData = $hasFile ? $request->file('slip_image') : ($validated['qr_code_data'] ?? null);

        try {
            $totalAmount = $request->input('amount', 0);

            $devMode = config('services.slipok.dev_mode', false);
            $okUse = config('services.slipok.ok_use', true);

            if ($devMode || ! $okUse) {
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
                $apiKey = config('services.slipok.api_key');
                $branchId = config('services.slipok.branch_id');

                if (! $apiKey || ! $branchId) {
                    return $this->slipResponse($request, false, 'SlipOK credentials not configured.', null, 500);
                }

                $url = "https://api.slipok.com/api/line/apikey/{$branchId}";
                if ($hasFile && $paymentData) {
                    // Uploaded file path
                    /** @var \Illuminate\Http\UploadedFile $uf */
                    $uf = $paymentData;
                    $binary = file_get_contents($uf->getRealPath());
                    $response = Http::withHeaders(['x-authorization' => $apiKey])
                        ->withOptions(['verify' => false])
                        ->attach('files', $binary, $uf->getClientOriginalName() ?: 'slip.jpg')
                        ->post($url, [
                            'log' => true,
                            'amount' => $totalAmount,
                        ]);
                } elseif (is_string($paymentData) && filter_var($paymentData, FILTER_VALIDATE_URL)) {
                    $response = Http::withHeaders([
                        'x-authorization' => $apiKey,
                        'Content-Type' => 'application/json',
                    ])->withOptions(['verify' => false])->post($url, [
                        'url' => $paymentData,
                        'log' => true,
                        'amount' => $totalAmount,
                    ]);
                } elseif (is_string($paymentData) && str_starts_with($paymentData, 'data:image')) {
                    $imageData = substr($paymentData, strpos($paymentData, ',') + 1);
                    $binary = base64_decode($imageData);
                    if ($binary === false) {
                        return $this->slipResponse($request, false, 'Invalid base64 slip image data.', null, 422);
                    }
                    $response = Http::withHeaders(['x-authorization' => $apiKey])
                        ->withOptions(['verify' => false])
                        ->attach('files', $binary, 'slip.jpg')
                        ->post($url, [
                            'log' => true,
                            'amount' => $totalAmount,
                        ]);
                } else {
                    $response = Http::withHeaders([
                        'x-authorization' => $apiKey,
                        'Content-Type' => 'application/json',
                    ])->withOptions(['verify' => false])->post($url, [
                        'data' => $paymentData,
                        'log' => true,
                        'amount' => $totalAmount,
                    ]);
                }

                if (! $response || ! $response->successful()) {
                    $body = $response ? $response->json() : [];
                    $msg = $body['message'] ?? 'SlipOK request failed';
                    $code = $body['code'] ?? 'UNKNOWN';
                    return $this->slipResponse($request, false, "SlipOK API Error ($code): $msg", null, 502);
                }

                $json = $response->json();
                if (! ($json['success'] ?? false)) {
                    $msg = $json['message'] ?? 'Slip verification failed';
                    $code = $json['code'] ?? 'INVALID';
                    return $this->slipResponse($request, false, "SlipOK Verification Error ($code): $msg", null, 422);
                }

                $slipVerification = $json['data'] ?? [];
                if (isset($slipVerification['amount']) && $totalAmount > 0 && (float) $slipVerification['amount'] !== (float) $totalAmount) {
                    return $this->slipResponse($request, false, 'Amount mismatch: slip shows '.$slipVerification['amount'].' expected '.$totalAmount, null, 422);
                }
            }
            return $this->slipResponse($request, true, 'Slip verified successfully', $slipVerification, 200);
        } catch (\Exception $e) {
            Log::error('verifySlip exception', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->slipResponse($request, false, 'Verification failed: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Helper to return either an Inertia redirect with flash or JSON depending on request type.
     */
    protected function slipResponse(Request $request, bool $success, string $message, $data = null, int $status = 200)
    {
        if ($request->hasHeader('X-Inertia')) {
            if ($success) {
                return back()->with([
                    'slip_verification' => [
                        'message' => $message,
                        'data' => $data,
                    ],
                ]);
            }

            return back()->with([
                'slip_error' => $message,
            ])->withErrors(['slip' => $message]);
        }

        return response()->json([
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ], $status);
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

    /**
     * Request bill for an order (customer-facing).
     */
    public function requestBill(Request $request, $restaurantCode, $tableCode)
    {
        try {
            $qrCode = QrCode::where('code', $tableCode)
                ->where('is_active', true)
                ->firstOrFail();

            $restaurant = $qrCode->restaurant;

            // Verify the restaurant code matches
            if ($restaurant->id != $restaurantCode) {
                abort(404, 'Invalid restaurant code');
            }

            // Prevent duplicate bill requests
            if ($qrCode->status === 'billing') {
                return redirect()->route('public.menu', [
                    'restaurantCode' => $restaurantCode,
                    'tableCode' => $tableCode,
                ])->withErrors(['message' => 'Bill already requested for this table.']);
            }

            // Mark the table (qr_code) as billing. We no longer update each order's status —
            // billing lifecycle is tracked at the QR (table) level.
            $qrCode->update(['status' => 'billing']);

            // Find any orders for the table to validate there's at least one with items
            $orders = $restaurant->orders()
                ->where('table_number', $qrCode->table_number)
                ->get();

            $ordersWithItems = $orders->filter(fn ($o) => $o->orderItems()->exists());

            if ($ordersWithItems->isEmpty()) {
                return redirect()->route('public.menu', [
                    'restaurantCode' => $restaurantCode,
                    'tableCode' => $tableCode,
                ])->withErrors(['message' => 'No active order found.']);
            }

            // Broadcast a single table-level event for staff so they can see the request
            broadcast(new \App\Events\TableBillRequested($qrCode));

            return redirect()->route('public.menu', [
                'restaurantCode' => $restaurantCode,
                'tableCode' => $tableCode,
            ])->with('success', 'Bill requested for table.');

        } catch (\Exception $e) {
            return redirect()->route('public.menu', [
                'restaurantCode' => $restaurantCode,
                'tableCode' => $tableCode,
            ])->withErrors(['message' => 'Failed to request bill: '.$e->getMessage()]);
        }
    }

    /**
     * Mark an order as billed (staff-facing).
     */
    public function markBilled(Restaurant $restaurant, Order $order)
    {
        $this->authorize('update', $order);

        // Mark a single order as paid. We do NOT create Payment rows here —
        // payments are created only on pay_before order creation or when staff checks a table.
        $order->update(['is_paid' => true]);

        // Broadcast an OrderBilled event for compatibility with existing listeners.
        broadcast(new \App\Events\OrderBilled($order));

        return back()->with('success', 'Order marked as paid.');
    }

    /**
     * Mark all orders for a table as paid (staff-facing).
     */
    public function markTablePaid(Restaurant $restaurant, $tableNumber)
    {
        // Deprecated: table-level per-order payment creation removed. Use markTableChecked instead.
        abort(410, 'Deprecated. Use markTableChecked');
    }

    /**
     * Mark all orders for a table as billed (staff-facing).
     */
    public function markTableBilled(Restaurant $restaurant, $tableNumber)
    {
        // Deprecated: Use markTableChecked which creates an aggregated payment.
        abort(410, 'Deprecated. Use markTableChecked');
    }

    /**
     * Staff action: mark an entire table as checked.
     * Creates a single aggregated Payment for the table when the restaurant is not pay_before.
     */
    public function markTableChecked(Restaurant $restaurant, $tableNumber)
    {
        $this->authorize('viewAny', [Order::class, $restaurant]);

        $qrCode = QrCode::where('restaurant_id', $restaurant->id)
            ->where('table_number', $tableNumber)
            ->first();

        // Collect unpaid orders for the table
        $orders = $restaurant->orders()
            ->where('table_number', $tableNumber)
            ->where('is_paid', false)
            ->get();

        // Sum amount for aggregation
        $amount = $orders->reduce(fn ($sum, $o) => $sum + (float) $o->total_amount, 0.0);

        DB::transaction(function () use ($orders, $restaurant, $qrCode, $tableNumber, $amount) {
            if ($restaurant->pay_before) {
                // Orders should already be paid; ensure flag is set and do NOT create payment rows
                foreach ($orders as $o) {
                    $o->update(['is_paid' => true]);
                }
            } else {
                // Create a single aggregated payment record for the table
                // Create a single aggregated payment record for the table if there are unpaid orders
                if ($amount > 0 && $orders->isNotEmpty()) {
                    $firstOrderId = $orders->first()->id;
                    Payment::create([
                        'table_number' => $tableNumber,
                        'trans_ref' => 'table-'.Str::uuid()->toString(),
                        'amount' => $amount,
                        'sender_name' => null,
                        'sender_display_name' => null,
                        'sending_bank' => null,
                        'restaurant_id' => $restaurant->id,
                        'qr_code_id' => $qrCode ? $qrCode->id : null,
                        'status' => 'completed',
                        'payment_details' => null,
                    ]);

                    // Mark orders as paid
                    foreach ($orders as $o) {
                        $o->update(['is_paid' => true]);
                    }
                }
            }

            // Finally, mark the QR code as checked so staff can clean the table
            if ($qrCode) {
                $qrCode->update(['status' => 'checked']);
            }
        });

        return back()->with('success', 'Table marked as checked.');
    }
}
