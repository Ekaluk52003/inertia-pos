<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class OrderController extends Controller
{
    /**
     * Display a listing of the orders for a restaurant.
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
        $this->authorize('viewKitchen', $restaurant);

        $activeOrders = $restaurant->orders()
            ->where('status', 'open')
            ->with(['orderItems' => function ($query) {
                $query->whereIn('status', ['pending', 'cooking', 'ready'])
                    ->orderBy('created_at');
            }])
            ->get();

        return Inertia::render('Kitchen/Show', [
            'restaurant' => $restaurant,
            'activeOrders' => $activeOrders,
        ]);
    }

    /**
     * Create a new order from the public menu (customer-facing).
     */
    public function storeFromMenu(Request $request, $restaurantCode, $tableCode)
    {
        $qrCode = QrCode::where('code', $tableCode)
            ->where('is_active', true)
            ->firstOrFail();

        $restaurant = $qrCode->restaurant;

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.menu_id' => 'required|exists:menus,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.special_instructions' => 'nullable|string',
            'customer_notes' => 'nullable|string',
        ]);

        // Calculate total amount
        $totalAmount = 0;
        $orderItems = [];

        foreach ($validated['items'] as $item) {
            $menuItem = $restaurant->menuItems()->findOrFail($item['menu_id']);
            $totalAmount += $menuItem->price * $item['quantity'];

            $orderItems[] = [
                'menu_id' => $menuItem->id,
                'name' => $menuItem->name,
                'quantity' => $item['quantity'],
                'price' => $menuItem->price,
                'status' => 'pending',
                'special_instructions' => $item['special_instructions'] ?? null,
            ];
        }

        // Create the order
        $order = $restaurant->orders()->create([
            'table_number' => $qrCode->table_number,
            'code' => Str::uuid()->toString(),
            'total_amount' => $totalAmount,
            'is_paid' => false,
            'status' => 'open',
            'customer_notes' => $validated['customer_notes'] ?? null,
        ]);

        // Create order items
        $order->orderItems()->createMany($orderItems);

        return response()->json([
            'message' => 'Order created successfully',
            'order' => $order->load('orderItems'),
        ]);
    }

    /**
     * Get the status of an order (for customer tracking).
     */
    public function getOrderStatus($orderCode)
    {
        $order = Order::where('code', $orderCode)->firstOrFail();
        $order->load('orderItems');

        return response()->json([
            'order' => $order,
        ]);
    }
}
