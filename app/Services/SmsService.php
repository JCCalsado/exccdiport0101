<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
class SmsService
{
    private string $apiKey;
    private string $senderName;
    private string $baseUrl = 'https://api.semaphore.co/api/v4/messages';
    public function __construct()
    {
        $this->apiKey = config('services.semaphore.api_key');
        $this->senderName = config('services.semaphore.sender_name', 'CCDI');
    }
    public function send(string $number, string $message): bool
    {
        try {
            $response = Http::post($this->baseUrl, [
                'apikey' => $this->apiKey,
                'number' => $number,
                'message' => $message,
                'sendername' => $this->senderName,
            ]);
            if ($response->successful()) {
                Log::info('SMS sent', ['number' => $number]);
                return true;
            }
            Log::error('SMS failed', ['response' => $response->body()]);
            return false;
        } catch (\Exception $e) {
            Log::error('SMS error', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
