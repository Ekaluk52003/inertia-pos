<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BillController extends Controller
{
    /**
     * Display a listing of the bills for a restaurant.
     */
    public function index(Restaurant $restaurant)
    {
        $this->authorize('viewAny', [Bill::class, $restaurant]);

        $bills = $restaurant->bills()
            ->with('order')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Bill/Index', [
            'restaurant' => $restaurant,
            'bills' => $bills,
        ]);
    }

    /**
     * Display the specified bill.
     */
    public function show(Restaurant $restaurant, Bill $bill)
    {
        $this->authorize('view', $bill);

        $bill->load('order.orderItems');

        return Inertia::render('Bill/Show', [
            'restaurant' => $restaurant,
            'bill' => $bill,
        ]);
    }

    /**
     * Generate a bill for an order.
     */
    public function generate(Restaurant $restaurant, Order $order)
    {
        $this->authorize('create', [Bill::class, $restaurant]);

        // Check if a bill already exists for this order
        if ($order->bill()->exists()) {
            return redirect()->route('bills.show', [$restaurant, $order->bill])
                ->with('info', 'Bill already exists for this order.');
        }

        // Create a new bill
        $bill = $order->bill()->create([
            'restaurant_id' => $restaurant->id,
            'code' => 'BILL-' . Str::random(8),
            'total_amount' => $order->total_amount,
            'status' => $order->is_paid ? 'paid' : 'pending',
        ]);

        return redirect()->route('bills.show', [$restaurant, $bill])
            ->with('success', 'Bill generated successfully.');
    }

    /**
     * Update the status of a bill.
     */
    public function updateStatus(Request $request, Restaurant $restaurant, Bill $bill)
    {
        $this->authorize('update', $bill);

        $validated = $request->validate([
            'status' => 'required|in:pending,paid,cancelled',
        ]);

        $bill->update(['status' => $validated['status']]);

        // If bill is marked as paid, also mark the order as paid
        if ($validated['status'] === 'paid') {
            $bill->order()->update(['is_paid' => true]);
        }

        return back()->with('success', 'Bill status updated.');
    }

    /**
     * Request a bill for an order (customer-facing).
     */
    public function requestBill($orderCode)
    {
        $order = Order::where('code', $orderCode)->firstOrFail();
        $restaurant = $order->restaurant;

        // Create a bill if it doesn't exist
        if (!$order->bill()->exists()) {
            $bill = $order->bill()->create([
                'restaurant_id' => $restaurant->id,
                'code' => 'BILL-' . Str::random(8),
                'total_amount' => $order->total_amount,
                'status' => $order->is_paid ? 'paid' : 'pending',
            ]);
        } else {
            $bill = $order->bill;
        }

        return response()->json([
            'message' => 'Bill requested successfully',
            'bill' => $bill,
        ]);
    }
}
