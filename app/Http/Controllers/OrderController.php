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

        // Preload QR codes for the orders in the paginated window and aggregate by qr_code_id.
        // Orders without a qr_code_id will be grouped under a legacy key so we don't lose them.
        $qrIds = $allOrders->pluck('qr_code_id')->filter()->unique()->values()->all();
        $qrCodes = QrCode::where('restaurant_id', $restaurant->id)
            ->whereIn('id', $qrIds ?: [-1])
            ->get()
            ->keyBy('id');

        // Compute aggregates per QR code id. Legacy orders (no qr_code_id) are grouped under 'legacy:{table_number}'.
        $tables = $allOrders->groupBy(function ($order) {
            return $order->qr_code_id ?? 'legacy:'.$order->table_number;
        })->map(function ($groupOrders, $groupKey) use ($qrCodes) {
            $ordersArr = $groupOrders->map(function ($order) {
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

            // Resolve QR code if this group maps to an actual qr id
            $qrCode = is_string($groupKey) && str_starts_with($groupKey, 'legacy:') ? null : ($qrCodes[$groupKey] ?? null);

            // Determine table status from QR code if available. We no longer rely on
            // individual orders' status strings; the QR (table) lifecycle is the source
            // of truth for table-level state. If QR is missing, default to 'active' and
            // rely on is_paid to indicate payment state.
            $status = $qrCode ? ($qrCode->status ?? 'active') : 'active';

            // Compute whether any order is unpaid
            $isPaid = $ordersArr->every(fn ($o) => ($o['is_paid'] ?? false) === true);

            return [
                'qr_code_id' => $qrCode ? $qrCode->id : null,
                'table_number' => $qrCode ? $qrCode->table_number : ($ordersArr->first()['table_number'] ?? null),
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
    public function tableShow(Request $request, Restaurant $restaurant, $tableNumber)
    {
        $this->authorize('viewAny', [Order::class, $restaurant]);

        // Prefer explicit qrCodeId query parameter when the frontend provides it.
        // Otherwise resolve a QR code for this table if present (use any QR, not only active).
        $qrCode = null;
        $qrCodeId = $request->input('qrCodeId');

        if ($qrCodeId) {
            $qrCode = QrCode::where('restaurant_id', $restaurant->id)
                ->where('id', $qrCodeId)
                ->first();
        }

        if (! $qrCode) {
            // We intentionally do not filter by is_active here — show orders tied to the QR regardless of its active flag.
            $qrCode = QrCode::where('restaurant_id', $restaurant->id)
                ->where('table_number', $tableNumber)
                ->orderBy('created_at', 'desc')
                ->first();
        }

        // Build base orders query with eager loading
        $ordersQuery = $restaurant->orders()->with(['orderItems.menuItem'])->orderBy('created_at', 'desc');

        if ($qrCode) {
            // Only include orders explicitly tied to this QR record.
            // Do NOT include legacy orders by table_number; we don't need that fallback.
            $ordersQuery->where('qr_code_id', $qrCode->id);
        } else {
            // No QR record found: fall back to table_number (legacy behavior)
            $ordersQuery->where('table_number', $tableNumber);
        }

        $tableOrders = $ordersQuery->get()->map(function ($o) {
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
            // Accept an uploaded image file OR a qr_code_data string (aligns with verifySlip)
            $validationRules['slip_image'] = 'required_without:qr_code_data|nullable|file|image|max:5120'; // 5MB
            $validationRules['qr_code_data'] = 'required_without:slip_image|nullable|string';
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

    // Build normalized order items and compute total using shared helper
    [$totalAmount, $orderItems] = $this->buildOrderItemsAndTotal($restaurant, $validated['items']);

    // Handle payment status based on restaurant settings
    $isPaid = false;
    // Data to persist into payments table when verification passes
    $paymentRecordData = null;

        if ($restaurant->pay_before) {
            // Only verify payment if payment data is provided
            if ($request->hasFile('slip_image') || isset($validated['slip_image']) || isset($validated['qr_code_data'])) {
                try {
                    // Prefer uploaded file when present; otherwise use string payload (url/base64/qr text)
                    $hasFile = $request->hasFile('slip_image');
                    $verificationPayload = $hasFile ? $request->file('slip_image') : ($validated['slip_image'] ?? $validated['qr_code_data']);

                    // Use shared verification helper
                    $slipVerification = $this->verifySlipPayload($request, $verificationPayload, (float) $totalAmount);

                    $paymentRecordData = [
                        'table_number' => $qrCode->table_number,
                        'amount' => $totalAmount,
                        'trans_ref' => $slipVerification['transRef'] ?? null,
                        'sender_name' => $slipVerification['sender']['name'] ?? null,
                        'sender_display_name' => $slipVerification['sender']['displayName'] ?? null,
                        'sending_bank' => $slipVerification['sendingBank'] ?? null,
                        'restaurant_id' => $restaurant->id,
                        'qr_code_id' => $qrCode->id,
                    ];

                    $isPaid = true;
                } catch (\Throwable $e) {
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
                    'qr_code_id' => $qrCode->id,
                    'code' => Str::uuid()->toString(),
                    'total_amount' => $totalAmount,
                    'is_paid' => $isPaid,
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
            if ($paymentRecordData) {
                // Payments are now table-level; do not set order_id here
                $payment = Payment::create($paymentRecordData);
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
     * Intended for resturant pay_after and record the payment.
     */
    public function verifySlip(Request $request)
    {
        // Items are optional from client now. For pay_after restaurants we'll compute
        // the expected amount server-side from DB orders for the table/QR.
        $validated = $request->validate([
            'restaurantCode' => 'nullable',
            'tableCode' => 'nullable',
            'slip_image' => 'required_without:qr_code_data|nullable|file|image|max:5120', // 5MB
            'qr_code_data' => 'required_without:slip_image|nullable|string',
        ]);

        $hasFile = $request->hasFile('slip_image');
        $payload = $hasFile ? $request->file('slip_image') : ($validated['qr_code_data'] ?? null);

        // Require restaurant/table context to compute the expected amount
        $restaurant = null;
        $qrCode = null;
        $restaurantCode = $validated['restaurantCode'] ?? null;
        $tableCode = $validated['tableCode'] ?? null;

        if ($restaurantCode) {
            $restaurant = Restaurant::find($restaurantCode);
        }

        if ($tableCode) {
            $qrQuery = QrCode::query()->where('code', $tableCode);
            if ($restaurant) {
                $qrQuery->where('restaurant_id', $restaurant->id);
            }
            $qrCode = $qrQuery->orderBy('created_at', 'desc')->first();
            if (! $restaurant && $qrCode) {
                $restaurant = $qrCode->restaurant;
            }
        }

        if (! $restaurant || ! $qrCode) {
            return $this->slipResponse($request, false, 'Missing restaurant or table context for slip verification.', null, 422);
        }

        // Compute expected amount from unpaid orders for this table (prefer qr_code_id, fallback table_number)
        $ordersQuery = Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->where(function ($q) use ($qrCode) {
                $q->where('qr_code_id', $qrCode->id)
                    ->orWhere('table_number', $qrCode->table_number);
            })
            ->where('is_paid', false);

        $expectedAmount = (float) $ordersQuery->sum('total_amount');

        if ($expectedAmount <= 0) {
            return $this->slipResponse($request, false, 'No unpaid orders found for this table.', null, 422);
        }

        // Verify slip with expected amount. Catch any verification exceptions and
        // return a graceful slipResponse so the client receives a structured
        // error instead of a 500 Internal Server Error page.
        try {
            $verification = $this->verifySlipPayload($request, $payload, $expectedAmount);
        } catch (\Throwable $e) {
            Log::error('verifySlip exception', ['message' => $e->getMessage()]);
            return $this->slipResponse($request, false, 'Payment verification failed: '.$e->getMessage(), null, 422);
        }

        // If verification succeeds, create a payment record and mark all relevant orders as paid
        $payment = null;
    DB::transaction(function () use ($ordersQuery, $restaurant, $qrCode, $expectedAmount, $verification, &$payment) {
            // Create aggregated payment for the table
            $payment = Payment::create([
                'table_number' => $qrCode->table_number,
                'trans_ref' => $verification['transRef'] ?? ('table-'.Str::uuid()->toString()),
                'amount' => $expectedAmount,
                'sender_name' => $verification['sender']['name'] ?? null,
                'sender_display_name' => $verification['sender']['displayName'] ?? null,
                'sending_bank' => $verification['sendingBank'] ?? null,
                'restaurant_id' => $restaurant->id,
                'qr_code_id' => $qrCode->id,
                'status' => 'completed',
                'payment_details' => null,
            ]);

            // Mark all unpaid orders for the table as paid
            $orders = $ordersQuery->get();
            foreach ($orders as $o) {
                $o->update(['is_paid' => true]);
            }

            // Mark the QR code as checked (status value supported by enum) since payment has been recorded
            $qrCode->update(['status' => 'checked']);
        });

        // Return success response including some payment info
        return $this->slipResponse($request, true, 'Slip verified and payment recorded.', [
            'verification' => $verification,
            'payment_id' => $payment ? $payment->id : null,
            'amount' => $expectedAmount,
        ], 200);

    }

    /**
     * Public action: customer taps "Check" to mark their table QR status as checked.
     */
    public function publicCheck(Request $request)
    {
        $validated = $request->validate([
            'restaurantCode' => 'required',
            'tableCode' => 'required',
        ]);

        $restaurant = Restaurant::find($validated['restaurantCode']);
        if (! $restaurant) {
            return back()->withErrors(['message' => 'Restaurant not found.']);
        }

        $qrCode = QrCode::where('restaurant_id', $restaurant->id)
            ->where('code', $validated['tableCode'])
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $qrCode) {
            return back()->withErrors(['message' => 'Table not found.']);
        }

        $qrCode->update(['status' => 'checked']);

        return back()->with('success', 'Table checked.');
    }

    /**
     * Perform slip verification (DEV bypass supported). Returns verification array on success or throws on failure. Also create payment record
     * @param  Request  $request  used to inspect headers/context
     * @param  mixed    $payload  UploadedFile|string (url, base64 data url, raw QR text)
     * @param  float    $expectedAmount Amount that must match verification result (0 to skip strict check)
     * @return array<string, mixed>
     * @throws \Throwable on failure
     */
    protected function verifySlipPayload(Request $request, $payload, float $expectedAmount = 0): array
    {
        $devMode = config('services.slipok.dev_mode', false);
        $okUse = config('services.slipok.ok_use', true);

        if ($devMode || ! $okUse) {
            return [
                'transRef' => 'DEV_'.\Illuminate\Support\Str::uuid()->toString(),
                'amount' => $expectedAmount,
                'sender' => [
                    'name' => 'DEV MODE',
                    'displayName' => 'Development Test',
                ],
                'sendingBank' => 'DEV BANK',
            ];
        }

        $apiKey = config('services.slipok.api_key');
        $branchId = config('services.slipok.branch_id');
        if (! $apiKey || ! $branchId) {
            throw new \Exception('SlipOK credentials not configured.');
        }

        $url = "https://api.slipok.com/api/line/apikey/{$branchId}";
        $response = null;

        if ($payload instanceof \Illuminate\Http\UploadedFile) {
            $binary = file_get_contents($payload->getRealPath());
            $response = Http::withHeaders(['x-authorization' => $apiKey])
                ->withOptions(['verify' => false])
                ->attach('files', $binary, $payload->getClientOriginalName() ?: 'slip.jpg')
                ->post($url, [
                    'log' => true,
                    'amount' => $expectedAmount,
                ]);
        } elseif (is_string($payload) && filter_var($payload, FILTER_VALIDATE_URL)) {
            $response = Http::withHeaders([
                'x-authorization' => $apiKey,
                'Content-Type' => 'application/json',
            ])->withOptions(['verify' => false])->post($url, [
                'url' => $payload,
                'log' => true,
                'amount' => $expectedAmount,
            ]);
        } elseif (is_string($payload) && str_starts_with($payload, 'data:image')) {
            $imageData = substr($payload, strpos($payload, ',') + 1);
            $binary = base64_decode($imageData);
            if ($binary === false) {
                throw new \Exception('Invalid base64 slip image data.');
            }
            $response = Http::withHeaders(['x-authorization' => $apiKey])
                ->withOptions(['verify' => false])
                ->attach('files', $binary, 'slip.jpg')
                ->post($url, [
                    'log' => true,
                    'amount' => $expectedAmount,
                ]);
        } else {
            $response = Http::withHeaders([
                'x-authorization' => $apiKey,
                'Content-Type' => 'application/json',
            ])->withOptions(['verify' => false])->post($url, [
                'data' => $payload,
                'log' => true,
                'amount' => $expectedAmount,
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

        $verified = $json['data'] ?? [];
        if (isset($verified['amount']) && $expectedAmount > 0 && (float) $verified['amount'] !== (float) $expectedAmount) {
            throw new \Exception('Amount mismatch: slip shows '.$verified['amount'].' expected '.$expectedAmount);
        }

        return $verified;
    }

    /**
     * Build normalized order items and compute total amount from a restaurant's menu data.
     * Returns [totalAmount, orderItemsArray].
     */
    protected function buildOrderItemsAndTotal(Restaurant $restaurant, array $items): array
    {
        $totalAmount = 0.0;
        $orderItems = [];

        foreach ($items as $item) {
            try {
                $menuItem = $restaurant->menuItems()->find($item['menu_id'] ?? null);
                if (! $menuItem) {
                    continue;
                }

                $quantity = (int) ($item['quantity'] ?? 1);
                $baseUnitPrice = (float) $menuItem->price;

                $selectedOptionsInput = isset($item['selected_options']) && is_array($item['selected_options'])
                    ? $item['selected_options']
                    : [];

                $computedAdditionalPerUnit = 0.0;
                $normalizedSelectedOptions = [];

                if (! empty($selectedOptionsInput)) {
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
                            continue;
                        }
                        $group = $optionGroups[$optionName] ?? null;
                        $additionalForThisOption = 0.0;
                        if ($group) {
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
                            'additional_price' => $additionalForThisOption,
                        ];
                    }
                }

                $finalUnitPrice = $baseUnitPrice + $computedAdditionalPerUnit;
                $lineTotal = $finalUnitPrice * $quantity;

                $totalAmount += $lineTotal;

                $orderItems[] = [
                    'menu_id' => $menuItem->id,
                    'name' => $menuItem->name,
                    'quantity' => $quantity,
                    'price' => $finalUnitPrice,
                    'status' => 'pending',
                    'special_instructions' => $item['special_instructions'] ?? null,
                    'options' => $normalizedSelectedOptions,
                ];
            } catch (\Throwable $e) {
                Log::warning('buildOrderItemsAndTotal error', ['item' => $item, 'error' => $e->getMessage()]);
                // skip problematic item
            }
        }

        return [$totalAmount, $orderItems];
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

        // Include QR code/table metadata if available so the frontend has table status
        // (we may have already resolved active QR above)
        if (! isset($qrCode)) {
            // Prefer the most recent QR record for this table regardless of is_active.
            // This mirrors tableShow behaviour and ensures we update the correct QR even
            // if previous logic did not deactivate older QRs.
            $qrCode = QrCode::where('restaurant_id', $restaurant->id)
                ->where('table_number', $tableNumber)
                ->orderBy('created_at', 'desc')
                ->first();
        }

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
