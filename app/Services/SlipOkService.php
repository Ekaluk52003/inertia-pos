<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SlipOkService
{
    private $apiKey;
    private $branchId;
    private $useDevMode;

    public function __construct()
    {
        $this->apiKey = config('services.slipok.api_key');
        $this->branchId = config('services.slipok.branch_id');
        $this->useDevMode = config('services.slipok.dev_mode', false);
    }

    public function verifyPayment($paymentData, $expectedAmount = null)
    {
        if ($this->useDevMode) {
            Log::info('DEV MODE: Bypassing actual Slipok API call and simulating success.');
            return [
                'transRef' => 'DEV_' . Str::uuid()->toString(),
                'amount' => $expectedAmount,
                'sender' => [
                    'name' => 'DEV MODE',
                    'displayName' => 'Development Test'
                ],
                'sendingBank' => 'DEV BANK'
            ];
        }

        if (!$this->apiKey || !$this->branchId) {
            throw new \Exception("Payment provider is not configured on the server.");
        }

        try {
            $url = "https://api.slipok.com/api/line/apikey/{$this->branchId}";
            $headers = [
                'x-authorization: ' . $this->apiKey
            ];

            $fields = [
                'log' => true
            ];

            if ($expectedAmount) {
                $fields['amount'] = $expectedAmount;
            }

            if (filter_var($paymentData, FILTER_VALIDATE_URL)) {
                // URL-based verification
                $fields['url'] = $paymentData;
                $headers[] = 'Content-Type: multipart/form-data';
            } elseif (str_starts_with($paymentData, 'data:image')) {
                // Base64 image verification
                $imageData = substr($paymentData, strpos($paymentData, ',') + 1);
                $tempFile = tempnam(sys_get_temp_dir(), 'slip_');
                file_put_contents($tempFile, base64_decode($imageData));
                $fields['files'] = new \CURLFile($tempFile);
                $headers[] = 'Content-Type: multipart/form-data';
            } else {
                // QR code data verification
                $fields['data'] = $paymentData;
                $headers[] = 'Content-Type: multipart/form-data';
            }

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => $fields,
                CURLOPT_HTTPHEADER => $headers,
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            // Clean up temp file if it was created
            if (isset($tempFile) && file_exists($tempFile)) {
                unlink($tempFile);
            }

            if (!$response) {
                $error = curl_error($curl);
                $errno = curl_errno($curl);
                curl_close($curl);
                throw new \Exception("CURL Error ($errno): $error");
            }

            curl_close($curl);
            $jsonResponse = json_decode($response);

            if ($httpCode !== 200 || $jsonResponse->success !== true) {
                $errorMessage = isset($jsonResponse->message) ? $jsonResponse->message : 'Unknown error';
                $errorCode = isset($jsonResponse->code) ? $jsonResponse->code : 'Unknown code';
                throw new \Exception("API Error ($errorCode): $errorMessage");
            }

            Log::info('SlipOK verification response:', (array)$jsonResponse);

            // Verify amount if needed
            if ($expectedAmount && $jsonResponse->data->amount != $expectedAmount) {
                throw new \Exception(
                    "Amount mismatch: slip shows {$jsonResponse->data->amount}, but order total is {$expectedAmount}."
                );
            }

            return (array)$jsonResponse->data;
        } catch (\Exception $e) {
            Log::error('SlipOK API error: ' . $e->getMessage());
            throw new \Exception($e->getMessage());
        }
    }
}
