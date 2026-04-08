<?php

namespace App\Http\Controllers;

use App\Models\StudentPaymentTerm;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PaymongoWebhookController extends Controller
{
    /**
     * Handle incoming PayMongo webhook events.
     * Must always return 2xx — PayMongo disables your webhook after
     * 3 consecutive events that exhaust 12 retry attempts each.
     */
    public function handle(Request $request): Response
    {
        // 1. Verify the signature FIRST before touching anything
        if (!$this->isValidSignature($request)) {
            Log::warning('PayMongo webhook: invalid signature rejected.');
            // Return 200 anyway — returning 4xx causes retries on a bad request
            return response('Unauthorized', 200);
        }

        $payload = $request->json()->all();
        $eventType = data_get($payload, 'data.attributes.type');

        Log::info('PayMongo webhook received', ['event' => $eventType]);

        match ($eventType) {
            'payment.paid',
            'checkout_session.payment.paid' => $this->handlePaymentPaid($payload),
            'payment.failed'                => $this->handlePaymentFailed($payload),
            default                         => null, // Ignore unknown events
        };

        // Always return 200 to acknowledge receipt
        return response('OK', 200);
    }

    // -------------------------------------------------------------------------

    private function handlePaymentPaid(array $payload): void
    {
        // For checkout_session.payment.paid, the data is nested differently
        $attributes = data_get($payload, 'data.attributes.data.attributes', []);
        $paymentIntentId = data_get($attributes, 'payment_intent_id')
            ?? data_get($payload, 'data.attributes.data.id');

        if (!$paymentIntentId) {
            Log::error('PayMongo webhook: missing payment_intent_id in paid event', $payload);
            return;
        }

        // Look up the StudentPaymentTerm by payment_intent_id
        // (You'll need to store this when creating the payment intent)
        $paymentTerm = StudentPaymentTerm::where('payment_intent_id', $paymentIntentId)->first();

        if (!$paymentTerm) {
            Log::warning('PayMongo webhook: no StudentPaymentTerm found for payment_intent_id', [
                'payment_intent_id' => $paymentIntentId,
            ]);
            return;
        }

        $amountPaid = data_get($attributes, 'amount', 0) / 100; // PayMongo uses centavos

        $paymentTerm->update([
            'status'  => 'paid',
            'balance' => max(0, $paymentTerm->balance - $amountPaid),
            'paid_at' => now(),
        ]);

        Log::info('PayMongo webhook: payment marked as paid', [
            'payment_term_id'   => $paymentTerm->id,
            'payment_intent_id' => $paymentIntentId,
            'amount_paid'       => $amountPaid,
        ]);
    }

    private function handlePaymentFailed(array $payload): void
    {
        $paymentIntentId = data_get($payload, 'data.attributes.data.attributes.payment_intent_id')
            ?? data_get($payload, 'data.attributes.data.id');

        if (!$paymentIntentId) {
            return;
        }

        $paymentTerm = StudentPaymentTerm::where('payment_intent_id', $paymentIntentId)->first();

        if ($paymentTerm) {
            $paymentTerm->update(['status' => 'failed']);

            Log::info('PayMongo webhook: payment marked as failed', [
                'payment_term_id'   => $paymentTerm->id,
                'payment_intent_id' => $paymentIntentId,
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Signature Verification
    // -------------------------------------------------------------------------

    private function isValidSignature(Request $request): bool
    {
        $secret = config('services.paymongo.webhook_secret');

        if (!$secret) {
            Log::error('PayMongo webhook: PAYMONGO_WEBHOOK_SECRET is not set.');
            return false;
        }

        $signatureHeader = $request->header('Paymongo-Signature');

        if (!$signatureHeader) {
            return false;
        }

        // Header format: "t=timestamp,te=test_sig,li=live_sig"
        $parts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = explode('=', $part, 2);
            $parts[$key] = $value;
        }

        $timestamp  = $parts['t'] ?? null;
        $isLiveMode = app()->isProduction();
        $signature  = $isLiveMode ? ($parts['li'] ?? null) : ($parts['te'] ?? null);

        if (!$timestamp || !$signature) {
            return false;
        }

        // Build the signed string: timestamp + "." + raw body
        $rawBody   = $request->getContent();
        $toSign    = $timestamp . '.' . $rawBody;
        $computed  = hash_hmac('sha256', $toSign, $secret);

        return hash_equals($computed, $signature);
    }
}
