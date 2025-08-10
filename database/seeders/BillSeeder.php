<?php

namespace Database\Seeders;

use App\Models\Bill;
use App\Models\Order;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all completed and paid orders
        $paidOrders = Order::where('is_paid', true)->get();

        foreach ($paidOrders as $order) {
            Bill::create([
                'restaurant_id' => $order->restaurant_id,
                'order_id' => $order->id,
                'code' => Str::random(8),
                'total_amount' => $order->total_amount,
                'status' => 'paid',
                'created_at' => $order->updated_at,
                'updated_at' => $order->updated_at,
            ]);
        }

        // Get some unpaid orders to create pending bills
        $unpaidOrders = Order::where('is_paid', false)
            ->where('status', '!=', 'pending')
            ->take(3)
            ->get();

        foreach ($unpaidOrders as $order) {
            Bill::create([
                'restaurant_id' => $order->restaurant_id,
                'order_id' => $order->id,
                'code' => Str::random(8),
                'total_amount' => $order->total_amount,
                'status' => 'pending',
                'created_at' => $order->updated_at,
                'updated_at' => $order->updated_at,
            ]);
        }
    }
}
