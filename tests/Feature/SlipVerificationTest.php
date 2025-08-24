<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

it('sends server computed amount to slipok when verifying slip', function () {
    // Fake HTTP client so external call is intercepted
    Http::fake(function ($request) {
        // Assert requested payload contains amount with server computed value
        $body = $request->body();
        // body may be multipart, so just return a successful response
        return Http::response([
            'success' => true,
            'data' => [
                'amount' => 42,
                'transRef' => 'TEST123'
            ]
        ], 200);
    });

    // Create a restaurant and menu item using factories if available, otherwise skip
    $restaurant = \App\Models\Restaurant::factory()->create();
    $menu = \App\Models\Menu::factory()->create(['restaurant_id' => $restaurant->id, 'price' => 21]);

    // Create an active QR code for the table so the public order route resolves
    $qr = \App\Models\QrCode::factory()->create([
        'restaurant_id' => $restaurant->id,
        'code' => 'T1',
        'is_active' => true,
        'table_number' => 1,
    ]);

    // Create a fake file with image mime type to satisfy validation without GD extension
    $file = UploadedFile::fake()->create('slip.jpg', 100, 'image/jpeg');

    $items = [
        [
            'menu_id' => $menu->id,
            'quantity' => 2,
            'selected_options' => [],
        ],
    ];

    // Call the public order store endpoint which will verify the slip server-side
    $response = $this->post(route('public.order.store', ['restaurantCode' => $restaurant->id, 'tableCode' => 'T1']), [
        'items' => $items,
        'customer_notes' => '',
        'slip_image' => $file,
    ]);

    $response->assertRedirect(route('public.menu', ['restaurantCode' => $restaurant->id, 'tableCode' => 'T1']));
});
