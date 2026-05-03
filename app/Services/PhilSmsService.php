<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PhilSmsService
{
    private string $token;
    private string $senderId;
    private string $baseUrl = 'https://dashboard.philsms.com/api/v3';
    private bool $enabled;

    public function __construct()
    {
        $this->token    = config('services.philsms.token', '');
        $this->senderId = config('services.philsms.sender_id', 'PhilSMS');
        $this->enabled  = (bool) config('services.philsms.enabled', false);
    }

    public function send(string $phone, string $message): bool
    {
        if (! $this->enabled || empty($this->token)) {
            Log::info('PhilSMS disabled or no token', ['phone' => $phone]);
            return false;
        }

        $phone = $this->normalizePhone($phone);

        if (! $phone) {
            Log::warning('PhilSMS: invalid phone number', ['raw' => $phone]);
            return false;
        }

        try {
            $response = Http::withToken($this->token)
                ->timeout(10)
                ->post($this->baseUrl . '/sms/send', [
                    'sender_id' => $this->senderId,
                    'message'   => $message,
                    'recipient' => $phone,
                ]);

            $body = $response->json();

            if ($response->successful() && ($body['status'] ?? '') === 'success') {
                Log::info('PhilSMS: SMS sent', ['phone' => $phone]);
                return true;
            }

            Log::error('PhilSMS: send failed', [
                'phone'    => $phone,
                'status'   => $response->status(),
                'response' => $body,
            ]);
            return false;

        } catch (\Throwable $e) {
            Log::error('PhilSMS: exception', ['phone' => $phone, 'error' => $e->getMessage()]);
            return false;
        }
    }

    private function normalizePhone(string $phone): ?string
    {
        $phone = preg_replace('/\D/', '', $phone);

        // Convert to +63 format required by PhilSMS
        if (strlen($phone) === 11 && str_starts_with($phone, '09')) {
            return '+63' . substr($phone, 1); // 09XX → +639XX
        }
        if (strlen($phone) === 12 && str_starts_with($phone, '639')) {
            return '+' . $phone; // 639XX → +639XX
        }
        if (strlen($phone) === 10 && str_starts_with($phone, '9')) {
            return '+63' . $phone; // 9XX → +639XX
        }

        return null;
    }
}
