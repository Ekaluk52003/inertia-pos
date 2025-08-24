<?php

use App\Http\Controllers\OrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

function callVerifySlipPayload(mixed $payload, float $expectedAmount = 0.0): array
{
    $controller = new OrderController();
    $method = new ReflectionMethod(OrderController::class, 'verifySlipPayload');
    $method->setAccessible(true);
    $request = Request::create('/', 'POST');

    /** @var array $result */
    $result = $method->invoke($controller, $request, $payload, $expectedAmount);
    return $result;
}

it('returns dev verification when dev_mode enabled (no HTTP call)', function () {
    // Arrange
    config([
        'services.slipok.dev_mode' => true,
        'services.slipok.ok_use' => true, // ignored when dev_mode true
    ]);

    // Act
    $result = callVerifySlipPayload('RAWQRDATA', 123.45);

    // Assert
    expect($result)
        ->toBeArray()
        ->and($result['amount'])
        ->toBe(123.45)
        ->and($result['sender']['name'])
        ->toBe('DEV MODE')
        ->and($result['sendingBank'])
        ->toBe('DEV BANK');
});

it('verifies via SlipOK API for raw QR payload and matches amount', function () {
    // Arrange
    config([
        'services.slipok.dev_mode' => false,
        'services.slipok.ok_use' => true,
        'services.slipok.api_key' => 'test-key',
        'services.slipok.branch_id' => 'branch-1',
    ]);

    $expectedAmount = 250.0;
    Http::fake([
        'https://api.slipok.com/api/line/apikey/*' => Http::response([
            'success' => true,
            'data' => [
                'transRef' => 'ABC123',
                'amount' => $expectedAmount,
                'sender' => [
                    'name' => 'John Doe',
                    'displayName' => 'John D.',
                ],
                'sendingBank' => 'TestBank',
            ],
        ], 200),
    ]);

    // Act
    $payload = 'QRRAW-DATA-STRING';
    $result = callVerifySlipPayload($payload, $expectedAmount);

    // Assert basic fields
    expect($result)
        ->toBeArray()
        ->and($result['transRef'])
        ->toBe('ABC123')
        ->and((float) $result['amount'])
        ->toBe($expectedAmount);

    // Assert request contents
    Http::assertSent(function ($request) use ($expectedAmount, $payload) {
        $json = $request->data();
        return ($request->url() !== null)
            && str_contains($request->url(), 'https://api.slipok.com/api/line/apikey/')
            && ($json['data'] ?? null) === $payload
            && (float) ($json['amount'] ?? 0) === $expectedAmount;
    });
});

it('throws when amount does not match verification result', function () {
    // Arrange
    config([
        'services.slipok.dev_mode' => false,
        'services.slipok.ok_use' => true,
        'services.slipok.api_key' => 'test-key',
        'services.slipok.branch_id' => 'branch-1',
    ]);

    Http::fake([
        'https://api.slipok.com/api/line/apikey/*' => Http::response([
            'success' => true,
            'data' => [
                'transRef' => 'XYZ999',
                'amount' => 99.99, // mismatched!
                'sender' => ['name' => 'A', 'displayName' => 'B'],
                'sendingBank' => 'C',
            ],
        ], 200),
    ]);

    // Act + Assert
    expect(function () {
        callVerifySlipPayload('RAW', 100.00);
    })->toThrow(Exception::class);
});
