<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
    // Get all paid orders
    $paidOrders = Order::where('is_paid', true)->get();

        foreach ($paidOrders as $order) {
            Payment::create([
                'trans_ref' => 'TXN'.Str::random(8),
                'amount' => $order->total_amount,
                'sender_name' => $this->getRandomName(),
                'status' => 'completed',
                'payment_details' => json_encode([
                    'payment_method' => $this->getRandomPaymentMethod(),
                    'transaction_time' => $order->updated_at->toDateTimeString(),
                ]),
                'created_at' => $order->updated_at,
                'updated_at' => $order->updated_at,
            ]);
        }
    }

    /**
     * Get a random customer name.
     */
    private function getRandomName(): string
    {
        $firstNames = ['John', 'Jane', 'Michael', 'Sarah', 'David', 'Lisa', 'Robert', 'Emily', 'William', 'Olivia'];
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Miller', 'Davis', 'Garcia', 'Rodriguez', 'Wilson'];

        return $firstNames[array_rand($firstNames)].' '.$lastNames[array_rand($lastNames)];
    }

    /**
     * Get a random payment method.
     */
    private function getRandomPaymentMethod(): string
    {
        $methods = ['PromptPay', 'Credit Card', 'Cash', 'Mobile Banking'];

        return $methods[array_rand($methods)];
    }
}
