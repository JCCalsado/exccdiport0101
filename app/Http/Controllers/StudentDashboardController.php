<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\StudentAssessment;
use App\Models\PaymentReminder;

class StudentDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Get account with transactions
        $account = $user->account()->with('transactions')->first();

        if (! $account) {
            $account = $user->account()->create(['balance' => 0]);
        }

        // Get latest assessment with payment terms (MOST ACCURATE DATA)
        $latestAssessment = StudentAssessment::where('user_id', $user->id)
            ->with('paymentTerms')
            ->latest('created_at')
            ->first();

        // Calculate remaining balance from payment terms (if available)
        $remainingBalance = 0;
        $paymentTerms     = collect([]);

        if ($latestAssessment) {
            $paymentTerms = $latestAssessment->paymentTerms()
                ->orderBy('term_order')
                ->get();

            $remainingBalance = $paymentTerms->sum('balance');
        }

        // Fallback to transaction-based calculation if no payment terms
        if ($paymentTerms->isEmpty()) {
            $totalCharges  = $user->transactions()->where('kind', 'charge')->sum('amount');
            $totalPayments = $user->transactions()
                ->where('kind', 'payment')
                ->where('status', 'paid')
                ->sum('amount');
            $remainingBalance = max(0, $totalCharges - $totalPayments);
        } else {
            $totalCharges  = $user->transactions()->where('kind', 'charge')->sum('amount');
            $totalPayments = $user->transactions()
                ->where('kind', 'payment')
                ->where('status', 'paid')
                ->sum('amount');
        }

        $pendingChargesCount = $user->transactions()
            ->where('kind', 'charge')
            ->where('status', 'pending')
            ->count();

        // ── FIX Bug 1 ────────────────────────────────────────────────────────
        // Include ALL fields the frontend needs to correctly filter and render
        // notifications: dismissed_at (so the Dashboard can hide dismissed ones),
        // type (so the banner can pick the right colour), and created_at (sorting).
        // Previously these three were missing from the map(), causing dismissed
        // banners to reappear on every Dashboard load and losing type-based styling.
        // ─────────────────────────────────────────────────────────────────────
        $notifications = Notification::active()
            ->forUser($user->id)
            ->withinDateRange()
            ->forDueDateTrigger($user)
            ->orderByDesc('created_at')
            ->take(5)
            ->get()
            ->map(function ($notification) {
                return [
                    'id'           => $notification->id,
                    'title'        => $notification->title,
                    'message'      => $notification->message,
                    'type'         => $notification->type,          // ← was missing
                    'start_date'   => $notification->start_date,
                    'end_date'     => $notification->end_date,
                    'target_role'  => $notification->target_role,
                    'is_active'    => $notification->is_active,
                    'is_complete'  => $notification->is_complete,
                    'dismissed_at' => $notification->dismissed_at,  // ← was missing
                    'created_at'   => $notification->created_at,    // ← was missing
                ];
            });

        $recentTransactions = $user->transactions()
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($txn) {
                return [
                    'id'         => $txn->id,
                    'reference'  => $txn->reference,
                    'type'       => $txn->type ?: 'General',
                    'amount'     => $txn->amount,
                    'status'     => $txn->status,
                    'created_at' => $txn->created_at,
                ];
            });

        $totalFees = $latestAssessment
            ? (float) $latestAssessment->total_assessment
            : (float) ($totalCharges ?? 0);

        $unreadReminders = PaymentReminder::where('user_id', $user->id)
            ->where('status', '!=', PaymentReminder::STATUS_DISMISSED)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function ($reminder) {
                return [
                    'id'                  => $reminder->id,
                    'type'                => $reminder->type,
                    'message'             => $reminder->message,
                    'outstanding_balance' => (float) $reminder->outstanding_balance,
                    'status'              => $reminder->status,
                    'read_at'             => $reminder->read_at,
                    'sent_at'             => $reminder->sent_at,
                    'trigger_reason'      => $reminder->trigger_reason,
                ];
            });

        $unreadReminderCount = PaymentReminder::where('user_id', $user->id)
            ->where('status', PaymentReminder::STATUS_SENT)
            ->count();

        return Inertia::render('Student/Dashboard', [
            'account'              => $account,
            'notifications'        => $notifications,
            'recentTransactions'   => $recentTransactions,
            'latestAssessment'     => $latestAssessment ? [
                'id'                => $latestAssessment->id,
                'assessment_number' => $latestAssessment->assessment_number,
                'total_assessment'  => (float) $latestAssessment->total_assessment,
                'status'            => $latestAssessment->status,
                'created_at'        => $latestAssessment->created_at,
            ] : null,
            'paymentTerms'         => $paymentTerms->map(function ($term) {
                return [
                    'id'         => $term->id,
                    'term_name'  => $term->term_name,
                    'term_order' => $term->term_order,
                    'percentage' => $term->percentage,
                    'amount'     => (float) $term->amount,
                    'balance'    => (float) $term->balance,
                    'due_date'   => $term->due_date,
                    'status'     => $term->status,
                    'remarks'    => $term->remarks,
                    'paid_date'  => $term->paid_date,
                ];
            })->toArray(),
            'stats'                => [
                'total_fees'            => $totalFees,
                'total_paid'            => (float) ($totalPayments ?? 0),
                'remaining_balance'     => (float) $remainingBalance,
                'pending_charges_count' => $pendingChargesCount,
            ],
            'paymentReminders'     => $unreadReminders,
            'unreadReminderCount'  => $unreadReminderCount,
        ]);
    }
}