<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $thaiRestaurant = Restaurant::where('name', 'Thai Delight')->first();
        $sushiRestaurant = Restaurant::where('name', 'Sushi Express')->first();

        $thaiMenuItems = Menu::where('restaurant_id', $thaiRestaurant->id)->get();
        $sushiMenuItems = Menu::where('restaurant_id', $sushiRestaurant->id)->get();

        // Create orders for Thai restaurant
        $this->createOrdersForRestaurant($thaiRestaurant, $thaiMenuItems, 5);

        // Create orders for Sushi restaurant
        $this->createOrdersForRestaurant($sushiRestaurant, $sushiMenuItems, 4);
    }

    /**
     * Create orders for a restaurant.
     */
    private function createOrdersForRestaurant($restaurant, $menuItems, $count): void
    {
    $statuses = ['pending', 'cooking', 'ready', 'completed'];

        for ($i = 1; $i <= $count; $i++) {
            $tableNumber = rand(1, 10);
            $isPaid = rand(0, 1) === 1;
            $orderStatus = $isPaid ? 'completed' : $statuses[array_rand($statuses)];

            // Create an order from today
            $order = Order::create([
                'restaurant_id' => $restaurant->id,
                'table_number' => $tableNumber,
                'code' => Str::random(8),
                'total_amount' => 0, // Will be calculated after adding items
                'is_paid' => $isPaid,
                // status removed: table/qr_code status used for lifecycle
                'customer_notes' => rand(0, 1) === 1 ? 'Please make it not too spicy.' : null,
                'created_at' => Carbon::now()->subHours(rand(1, 8)),
                'updated_at' => Carbon::now()->subHours(rand(0, 1)),
            ]);

            // Add 2-5 random menu items to the order
            $orderTotal = 0;
            $itemCount = rand(2, 5);

            $selectedItems = $menuItems->random($itemCount);

            foreach ($selectedItems as $menuItem) {
                $quantity = rand(1, 3);
                $itemStatus = $isPaid ? 'completed' : $statuses[array_rand($statuses)];
                $itemPrice = $menuItem->price;
                $itemTotal = $itemPrice * $quantity;
                $orderTotal += $itemTotal;

                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_id' => $menuItem->id,
                    'name' => $menuItem->name,
                    'quantity' => $quantity,
                    'price' => $itemPrice,
                    // item status kept for kitchen workflow if present
                    'status' => $itemStatus,
                    'special_instructions' => rand(0, 3) === 0 ? 'Extra sauce please.' : null,
                ]);
            }

            // Update the order total
            $order->update(['total_amount' => $orderTotal]);
        }

        // Create some orders from yesterday
        for ($i = 1; $i <= 3; $i++) {
            $tableNumber = rand(1, 10);

            $order = Order::create([
                'restaurant_id' => $restaurant->id,
                'table_number' => $tableNumber,
                'code' => Str::random(8),
                'total_amount' => 0,
                'is_paid' => true,
                // status removed: completed implied by is_paid
                'customer_notes' => null,
                'created_at' => Carbon::yesterday()->addHours(rand(10, 20)),
                'updated_at' => Carbon::yesterday()->addHours(rand(20, 23)),
            ]);

            // Add 1-4 random menu items to the order
            $orderTotal = 0;
            $itemCount = rand(1, 4);

            $selectedItems = $menuItems->random($itemCount);

            foreach ($selectedItems as $menuItem) {
                $quantity = rand(1, 2);
                $itemPrice = $menuItem->price;
                $itemTotal = $itemPrice * $quantity;
                $orderTotal += $itemTotal;

                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_id' => $menuItem->id,
                    'name' => $menuItem->name,
                    'quantity' => $quantity,
                    'price' => $itemPrice,
                    'status' => 'completed',
                    'special_instructions' => null,
                ]);
            }

            // Update the order total
            $order->update(['total_amount' => $orderTotal]);
        }
    }
}
