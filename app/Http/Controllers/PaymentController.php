<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\StudentAssessment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Luigel\Paymongo\Facades\Paymongo;

class PaymentController extends Controller
{
    // =========================================================================
    // RENDER PAYMENT PAGE (Inertia)
    // =========================================================================
    public function create(Request $request)
    {
        $user    = $request->user();
        $student = $user->student;

        if (!$student) {
            return redirect()->route('student.dashboard')->with('error', 'No student record found.');
        }

        $assessment = $student->assessments()->latest()->first();

        $paymentTerms = $assessment
            ? $assessment->paymentTerms()->orderBy('term_order')->get()->map(fn($t) => [
                'id'          => $t->id,
                'term_name'   => $t->term_name,
                'term_order'  => $t->term_order,
                'percentage'  => $t->percentage,
                'amount'      => $t->amount,
                'balance'     => $t->balance,
                'due_date'    => $t->due_date,
                'status'      => $t->status,
                'remarks'     => $t->remarks,
                'paid_date'   => $t->paid_date,
            ])->toArray()
            : [];

        $pendingApprovalPayments = $student->payments()
            ->where('status', PaymentStatus::PENDING->value)
            ->whereNotNull('reference_number')
            ->latest()
            ->get()
            ->map(fn($p) => [
                'id'               => $p->id,
                'reference'        => $p->reference_number,
                'amount'           => $p->amount,
                'selected_term_id' => $p->student_assessment_id,
                'term_name'        => $p->description,
                'created_at'       => $p->created_at,
            ])->toArray();

        $outstandingBalance = collect($paymentTerms)->sum('balance');

        return inertia('Payment/Create', [
            'studentName'             => $student->full_name ?? $user->name,
            'studentId'               => $student->id,
            'assessmentId'            => $assessment?->id,
            'outstandingBalance'      => $outstandingBalance,
            'paymentTerms'            => $paymentTerms,
            'latestAssessment'        => $assessment,
            'pendingApprovalPayments' => $pendingApprovalPayments,
        ]);
    }

    // =========================================================================
    // GCASH / MAYA — Create Payment Source (redirect-based)
    // =========================================================================
    public function createSource(Request $request)
    {
        $request->validate([
            'amount'                => 'required|numeric|min:100',
            'method'                => 'required|in:gcash,paymaya',
            'student_id'            => 'required|exists:students,id',
            'student_assessment_id' => 'nullable|exists:student_assessments,id',
            'description'           => 'nullable|string|max:255',
        ]);

        $amountInCentavos = (int) ($request->amount * 100);

        try {
            $source = Paymongo::source()->create([
                'type'     => $request->method,
                'amount'   => $amountInCentavos,
                'currency' => 'PHP',
                'redirect' => [
                    'success' => config('app.url') . '/payment/success',
                    'failed'  => config('app.url') . '/payment/failed',
                ],
            ]);

            Payment::create([
                'student_id'            => $request->student_id,
                'student_assessment_id' => $request->student_assessment_id,
                'payment_method'        => $request->method,
                'amount'                => $request->amount,
                'description'           => $request->description ?? 'CCDI Tuition Payment',
                'status'                => PaymentStatus::PENDING->value,
                'paymongo_source_id'    => $source->id,
            ]);

            return response()->json([
                'checkout_url' => $source->redirect['checkout_url'],
                'source_id'    => $source->id,
            ]);

        } catch (\Exception $e) {
            Log::error('PayMongo createSource error: ' . $e->getMessage());
            return response()->json(['message' => 'Payment creation failed. Please try again.'], 500);
        }
    }

    // =========================================================================
    // WEBHOOK — PayMongo calls this when payment is confirmed
    // =========================================================================
    public function webhook(Request $request)
    {
        // Verify webhook signature
        $payload   = $request->getContent();
        $sigHeader = $request->header('Paymongo-Signature');
        $secret    = env('PAYMONGO_WEBHOOK_SECRET');

        if ($secret && $sigHeader) {
            $parts     = collect(explode(',', $sigHeader))->mapWithKeys(function ($part) {
                [$k, $v] = explode('=', $part, 2);
                return [$k => $v];
            });
            $timestamp = $parts->get('t');
            $computed  = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

            if (!hash_equals($computed, $parts->get('te'))) {
                Log::warning('PayMongo webhook signature mismatch.');
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $data      = $request->input('data.attributes');
        $eventType = $data['type'] ?? null;

        Log::info('PayMongo webhook received: ' . $eventType);

        // ── GCash / Maya source became chargeable ──────────────────────────
        if ($eventType === 'source.chargeable') {
            $sourceId = $data['data']['id'] ?? null;
            $payment  = Payment::where('paymongo_source_id', $sourceId)->first();

            if ($payment && $payment->status === PaymentStatus::PENDING->value) {
                try {
                    $charge = Paymongo::payment()->create([
                        'amount'      => (int) ($payment->amount * 100),
                        'currency'    => 'PHP',
                        'source'      => ['id' => $sourceId, 'type' => 'source'],
                        'description' => $payment->description ?? 'CCDI Tuition Payment',
                    ]);

                    $payment->update([
                        'status'               => PaymentStatus::COMPLETED->value,
                        'paymongo_payment_id'  => $charge->id,
                        'paid_at'              => now(),
                    ]);

                    Log::info("Payment #{$payment->id} marked as completed via GCash/Maya.");
                } catch (\Exception $e) {
                    Log::error('PayMongo charge creation failed: ' . $e->getMessage());
                    $payment->update(['status' => PaymentStatus::FAILED->value]);
                }
            }
        }

        // ── Payment paid (Checkout Session flow) ──────────────────────────
        if ($eventType === 'payment.paid') {
            $paymentId = $data['data']['id'] ?? null;
            $payment   = Payment::where('paymongo_payment_id', $paymentId)->first();

            if ($payment) {
                $payment->update([
                    'status'  => PaymentStatus::COMPLETED->value,
                    'paid_at' => now(),
                ]);
            }
        }

        // ── Payment failed ─────────────────────────────────────────────────
        if ($eventType === 'payment.failed') {
            $paymentId = $data['data']['id'] ?? null;
            $payment   = Payment::where('paymongo_payment_id', $paymentId)->first();

            if ($payment) {
                $payment->update(['status' => PaymentStatus::FAILED->value]);
            }
        }

        return response()->json(['received' => true], 200);
    }

    // =========================================================================
    // PNB BANK TRANSFER — Student submits proof
    // =========================================================================
    public function submitBankTransfer(Request $request)
    {
        $request->validate([
            'student_id'            => 'required|exists:students,id',
            'student_assessment_id' => 'nullable|exists:student_assessments,id',
            'amount'                => 'required|numeric|min:1',
            'reference_number'      => 'required|string|max:100',
            'proof_of_payment'      => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'description'           => 'nullable|string|max:255',
        ]);

        $proofPath = $request->file('proof_of_payment')
            ->store('payment_proofs', 'public');

        $payment = Payment::create([
            'student_id'            => $request->student_id,
            'student_assessment_id' => $request->student_assessment_id,
            'payment_method'        => 'bank_transfer',
            'amount'                => $request->amount,
            'description'           => $request->description ?? 'PNB Bank Transfer',
            'status'                => PaymentStatus::PENDING->value,
            'reference_number'      => $request->reference_number,
            'proof_of_payment'      => $proofPath,
        ]);

        return response()->json([
            'message'    => 'Bank transfer submitted. Awaiting verification by accounting.',
            'payment_id' => $payment->id,
        ], 201);
    }

    // =========================================================================
    // ACCOUNTING — Verify / reject bank transfer
    // =========================================================================
    public function verifyBankTransfer(Request $request, Payment $payment)
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
            'notes'  => 'nullable|string|max:500',
        ]);

        $isApproved = $request->action === 'approve';

        $payment->update([
            'status'  => $isApproved ? PaymentStatus::COMPLETED->value : PaymentStatus::FAILED->value,
            'paid_at' => $isApproved ? now() : null,
            'notes'   => $request->notes,
        ]);

        return response()->json([
            'message' => 'Payment ' . ($isApproved ? 'approved' : 'rejected') . ' successfully.',
            'payment' => $payment->fresh(),
        ]);
    }

    // =========================================================================
    // GET PNB Bank Details (for frontend display)
    // =========================================================================
    public function getBankDetails()
    {
        return response()->json([
            'bank'           => 'Philippine National Bank (PNB)',
            'account_name'   => env('PNB_ACCOUNT_NAME', 'CCDI School'),
            'account_number' => env('PNB_ACCOUNT_NUMBER', ''),
            'branch'         => env('PNB_BRANCH', ''),
        ]);
    }

    // =========================================================================
    // GET Payment Status (for polling after redirect)
    // =========================================================================
    public function checkStatus(Request $request)
    {
        $request->validate([
            'source_id' => 'required|string',
        ]);

        $payment = Payment::where('paymongo_source_id', $request->source_id)->first();

        if (!$payment) {
            return response()->json(['status' => 'not_found'], 404);
        }

        return response()->json([
            'status'     => $payment->status,
            'payment_id' => $payment->id,
            'paid_at'    => $payment->paid_at,
        ]);
    }
}