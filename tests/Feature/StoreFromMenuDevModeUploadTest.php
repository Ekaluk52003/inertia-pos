<?php

use App\Models\Menu;
use App\Models\QrCode;
use App\Models\Restaurant;
use Illuminate\Support\Facades\Config;

it('creates an order with slip verification in dev_mode using qr_code_data', function () {
    // Enable dev mode so we bypass SlipOK
    Config::set('services.slipok.dev_mode', true);
    Config::set('services.slipok.ok_use', false);

    $restaurant = Restaurant::factory()->create([
        'pay_before' => true,
    ]);

    // Create a menu item
    $menu = $restaurant->menuItems()->create([
        'name' => 'Pad Thai',
        'price' => 100,
        'category' => 'Food',
        'options' => [],
    ]);

    // Create QR code for table
    $qr = QrCode::factory()->create([
        'restaurant_id' => $restaurant->id,
        'table_number' => 'A1',
        'is_active' => true,
    ]);

    $response = $this->post(route('public.order.store', [
        'restaurantCode' => $restaurant->id,
        'tableCode' => $qr->code,
    ]), [
        'items' => [
            [
                'menu_id' => $menu->id,
                'quantity' => 1,
                'special_instructions' => null,
                'selected_options' => [],
            ],
        ],
        'qr_code_data' => 'DEV_MODE_TEST_DATA',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('orders', [
        'restaurant_id' => $restaurant->id,
        'table_number' => 'A1',
        'is_paid' => true,
    ]);
});
