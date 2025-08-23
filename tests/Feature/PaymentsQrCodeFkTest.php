<?php

use App\Models\QrCode;
use App\Models\Restaurant;
use App\Models\Payment;
use Illuminate\Support\Str;

it('deletes payments when deleting a qr code (cascade)', function () {
    // Arrange: create a restaurant and QR code
    $restaurant = Restaurant::factory()->create();
    $qr = QrCode::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    // Create a payment referencing the QR code
    $payment = Payment::create([
        'table_number' => (string) ($qr->table_number ?? '1'),
        'trans_ref' => 't-'.Str::uuid()->toString(),
        'amount' => 123.45,
        'sender_name' => null,
        'sender_display_name' => null,
        'sending_bank' => null,
        'restaurant_id' => $restaurant->id,
        'qr_code_id' => $qr->id,
        'status' => 'completed',
        'payment_details' => null,
    ]);

    expect(Payment::query()->whereKey($payment->id)->exists())->toBeTrue();

    // Act: delete the QR code
    $qr->delete();

    // Assert: payment is deleted (cascade)
    expect(Payment::query()->whereKey($payment->id)->exists())->toBeFalse();
});
