<?php

use App\Models\QrCode;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns public menu with restaurant promptPayId and qr_code props', function () {
    // Create restaurant and qr code
    $restaurant = Restaurant::factory()->create([
        'prompt_pay_id' => '0123456789',
    ]);

    $qr = QrCode::factory()->create([
        'restaurant_id' => $restaurant->id,
        'table_number' => 5,
        'is_active' => true,
    ]);

    $response = $this->get("/public/menu/{$restaurant->id}/{$qr->code}");

    $response->assertStatus(200);

    // The Inertia payload is embedded in the response; check for some expected strings
    $content = $response->getContent();
    expect($content)->toContain('promptPayId');
    expect($content)->toContain('qr_code');
});
