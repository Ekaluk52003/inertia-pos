<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PaymentController extends Controller
{
    /**
     * Display a listing of the payments for a restaurant.
     */
    public function index(Restaurant $restaurant)
    {
        $this->authorize('viewAny', [Payment::class, $restaurant]);

        $payments = Payment::whereHas('order', function ($query) use ($restaurant) {
            $query->where('restaurant_id', $restaurant->id);
        })
        ->with('order')
        ->orderBy('created_at', 'desc')
        ->paginate(15);

        return Inertia::render('Payment/Index', [
            'restaurant' => $restaurant,
            'payments' => $payments,
        ]);
    }

    /**
     * Process a payment for an order.
     */
    public function processPayment(Request $request, Order $order)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'sender_name' => 'nullable|string|max:255',
            'payment_details' => 'nullable|json',
        ]);

        // Ensure the amount matches the order total
        if ($validated['amount'] != $order->total_amount) {
            return response()->json([
                'message' => 'Payment amount does not match order total',
            ], 422);
        }

        // Create the payment record
        $payment = $order->payments()->create([
            'trans_ref' => 'PAY-' . Str::random(10),
            'amount' => $validated['amount'],
            'sender_name' => $validated['sender_name'] ?? null,
            'status' => 'completed',
            'payment_details' => $validated['payment_details'] ?? null,
        ]);

        // Mark the order as paid
        $order->update(['is_paid' => true]);

        return response()->json([
            'message' => 'Payment processed successfully',
            'payment' => $payment,
        ]);
    }

    /**
     * Display the specified payment.
     */
    public function show(Restaurant $restaurant, Payment $payment)
    {
        $this->authorize('view', $payment);

        $payment->load('order');

        return Inertia::render('Payment/Show', [
            'restaurant' => $restaurant,
            'payment' => $payment,
        ]);
    }
}
