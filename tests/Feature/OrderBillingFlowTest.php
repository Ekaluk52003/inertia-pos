<?php

use App\Events\OrderBilled;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\QrCode;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('customer can request bill for active order', function () {
    Event::fake();

    // Create user and restaurant
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create([
        'owner_id' => $user->id,
        'pay_before' => false,
    ]);

    // Create QR code for table
    $qrCode = QrCode::factory()->create([
        'restaurant_id' => $restaurant->id,
        'table_number' => '1',
        'is_active' => true,
    ]);

    // Create active order with items
    $order = Order::factory()->create([
        'restaurant_id' => $restaurant->id,
        'table_number' => $qrCode->table_number,
        'status' => 'active',
        'total_amount' => 100.00,
        'is_paid' => false,
    ]);

    // Create order items
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'name' => 'Test Item',
        'quantity' => 1,
        'price' => 100.00,
    ]);

    // Request bill
    $response = $this->post("/public/order/{$restaurant->id}/{$qrCode->code}/request-bill");

    $response->assertRedirect();

    // Verify QR code status changed to billing and table event was broadcast
    $qrCode->refresh();
    expect($qrCode->status)->toBe('billing');

    Event::assertDispatched(App\Events\TableBillRequested::class, function ($event) use ($qrCode) {
        return $event->qrCode->id === $qrCode->id;
    });
});

test('cannot request bill for order without items', function () {
    // Create user and restaurant
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create([
        'owner_id' => $user->id,
        'pay_before' => false,
    ]);

    // Create QR code for table
    $qrCode = QrCode::factory()->create([
        'restaurant_id' => $restaurant->id,
        'table_number' => '1',
        'is_active' => true,
    ]);

    // Create active order WITHOUT items
    $order = Order::factory()->create([
        'restaurant_id' => $restaurant->id,
        'table_number' => $qrCode->table_number,
        'status' => 'active',
        'total_amount' => 0.00,
        'is_paid' => false,
    ]);

    // Try to request bill
    $response = $this->post("/public/order/{$restaurant->id}/{$qrCode->code}/request-bill");

    $response->assertRedirect();
    $response->assertSessionHasErrors(['message']);

    // Verify order status unchanged
    $order->refresh();
    expect($order->status)->toBe('active');
});

test('cannot request bill for non-active order', function () {
    // Create user and restaurant
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create([
        'owner_id' => $user->id,
        'pay_before' => false,
    ]);

    // Create QR code for table
    $qrCode = QrCode::factory()->create([
        'restaurant_id' => $restaurant->id,
        'table_number' => '1',
        'is_active' => true,
    ]);

    // Create order that's already in billing status
    $order = Order::factory()->create([
        'restaurant_id' => $restaurant->id,
        'table_number' => $qrCode->table_number,
        'status' => 'billing',
        'total_amount' => 100.00,
        'is_paid' => false,
    ]);

    // Try to request bill
    $response = $this->post("/public/order/{$restaurant->id}/{$qrCode->code}/request-bill");

    $response->assertRedirect();
    $response->assertSessionHasErrors(['message']);
});

test('staff can mark order as billed', function () {
    Event::fake();

    // Create user and restaurant
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create([
        'owner_id' => $user->id,
    ]);

    // Create order in billing status
    $order = Order::factory()->create([
        'restaurant_id' => $restaurant->id,
        'status' => 'billing',
        'is_paid' => false,
    ]);

    // Staff marks as billed (single order flow)
    $response = $this->actingAs($user)
        ->post("/restaurants/{$restaurant->id}/orders/{$order->id}/mark-billed");

    $response->assertRedirect();

    // Verify order is marked paid and event broadcasted
    $order->refresh();
    expect($order->is_paid)->toBeTrue();

    // Verify event was broadcasted
    Event::assertDispatched(OrderBilled::class, function ($event) use ($order) {
        return $event->order->id === $order->id;
    });
});

test('order model status methods work correctly', function () {
    $order = new Order(['status' => 'active']);
    expect($order->isActive())->toBeTrue();
    expect($order->isBilling())->toBeFalse();
    expect($order->isBilled())->toBeFalse();
    expect($order->isCompleted())->toBeFalse();

    $order->status = 'billing';
    expect($order->isActive())->toBeFalse();
    expect($order->isBilling())->toBeTrue();
    expect($order->isBilled())->toBeFalse();
    expect($order->isCompleted())->toBeFalse();

    $order->status = 'billed';
    expect($order->isActive())->toBeFalse();
    expect($order->isBilling())->toBeFalse();
    expect($order->isBilled())->toBeTrue();
    expect($order->isCompleted())->toBeFalse();

    $order->status = 'completed';
    expect($order->isActive())->toBeFalse();
    expect($order->isBilling())->toBeFalse();
    expect($order->isBilled())->toBeFalse();
    expect($order->isCompleted())->toBeTrue();
});

test('new orders are created with active status', function () {
    // Create user and restaurant
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create([
        'owner_id' => $user->id,
        'pay_before' => false,
    ]);

    // Create QR code for table
    $qrCode = QrCode::factory()->create([
        'restaurant_id' => $restaurant->id,
        'table_number' => '1',
        'is_active' => true,
    ]);

    // Create menu item
    $menuItem = Menu::factory()->create([
        'restaurant_id' => $restaurant->id,
        'name' => 'Test Item',
        'price' => 50.00,
        'category' => 'Main',
        'is_available' => true,
    ]);

    // Submit order
    $response = $this->post("/public/order/{$restaurant->id}/{$qrCode->code}", [
        'items' => [
            [
                'menu_id' => $menuItem->id,
                'quantity' => 1,
                'special_instructions' => null,
                'selected_options' => [],
            ],
        ],
        'customer_notes' => '',
    ]);

    $response->assertRedirect();

    // Verify order was created with active status
    $order = Order::where('restaurant_id', $restaurant->id)
        ->where('table_number', $qrCode->table_number)
        ->latest()
        ->first();

    expect($order)->not->toBeNull();
    expect($order->status)->toBe('active');
});
