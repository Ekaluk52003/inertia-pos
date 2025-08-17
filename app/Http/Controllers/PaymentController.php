<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

        // Build the base query so we can reuse it for an explicit total count
        $paymentsQuery = Payment::whereHas('order', function ($query) use ($restaurant) {
            $query->where('restaurant_id', $restaurant->id);
        })->with('order')->orderBy('created_at', 'desc');

    // Paginate the results for the view (3 per page while testing pagination)
    $payments = $paymentsQuery->paginate(3);

        // Also compute explicit totals to compare against the paginator's total()
        $paymentsTotalViaOrder = Payment::whereHas('order', function ($query) use ($restaurant) {
            $query->where('restaurant_id', $restaurant->id);
        })->count();

        // Some records might have a direct restaurant_id on the payments table — count those too
        $paymentsTotalDirect = Payment::where('restaurant_id', $restaurant->id)->count();

        // Ensure table_number is available from order relationship
        $payments->getCollection()->transform(function ($payment) {
            if (!$payment->table_number && $payment->order) {
                $payment->table_number = $payment->order->table_number;
            }
            return $payment;
        });

        // Add some debugging information
        Log::info('Payments page accessed', [
            'restaurant_id' => $restaurant->id,
            'restaurant_name' => $restaurant->name,
            // Number of items on this page
            'payments_count' => $payments->count(),
            // Paginator's idea of the total matching the query used for paginate()
            'paginator_total' => $payments->total(),
            // Explicit counts for diagnostics
            'payments_total_via_order_whereHas' => $paymentsTotalViaOrder,
            'payments_total_direct_restaurant_id' => $paymentsTotalDirect,
        ]);

        return Inertia::render('Payment/Index', [
            'restaurant' => $restaurant,
            'payments' => $payments,
            // Provide an explicit integer total so the frontend or debugging can rely on it
            'total_payments' => $paymentsTotalViaOrder,
            'total_payments_direct' => $paymentsTotalDirect,
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
