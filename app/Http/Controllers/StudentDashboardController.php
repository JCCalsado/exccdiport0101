<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Notification;
use App\Models\StudentAssessment;
use App\Models\PaymentReminder;

class StudentDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Ensure account exists
        $account = $user->account()->with('transactions')->first();
        if (! $account) {
            $account = $user->account()->create(['balance' => 0]);
        }

        // Latest assessment + payment terms (source of truth for balances)
        $latestAssessment = StudentAssessment::where('user_id', $user->id)
            ->with('paymentTerms')
            ->latest('created_at')
            ->first();

        $paymentTerms     = collect([]);
        $remainingBalance = 0;

        if ($latestAssessment) {
            $paymentTerms     = $latestAssessment->paymentTerms()->orderBy('term_order')->get();
            $remainingBalance = $paymentTerms->sum('balance');
        }

        $totalCharges  = $user->transactions()->where('kind', 'charge')->sum('amount');
        $totalPayments = $user->transactions()->where('kind', 'payment')->where('status', 'paid')->sum('amount');

        if ($paymentTerms->isEmpty()) {
            $remainingBalance = max(0, $totalCharges - $totalPayments);
        }

        $pendingChargesCount = $user->transactions()
            ->where('kind', 'charge')
            ->where('status', 'pending')
            ->count();

        $notifications = Notification::active()
            ->forUser($user->id)
            ->withinDateRange()
            ->forDueDateTrigger($user)
            ->orderByDesc('created_at')
            ->take(5)
            ->get()
            ->map(fn ($n) => [
                'id'              => $n->id,
                'title'           => $n->title,
                'message'         => $n->message,
                'type'            => $n->type,
                'start_date'      => $n->start_date,
                'end_date'        => $n->end_date,
                'due_date'        => $n->due_date,          // ← the actual payment deadline
                'payment_term_id' => $n->payment_term_id,  // ← links to the term for Pay Now
                'target_role'     => $n->target_role,
                'is_active'       => $n->is_active,
                'is_complete'     => $n->is_complete,
                'dismissed_at'    => $n->dismissed_at,
                'created_at'      => $n->created_at,
            ]);

        $recentTransactions = $user->transactions()
            ->orderByDesc('created_at')
            ->take(5)
            ->get()
            ->map(fn ($txn) => [
                'id'         => $txn->id,
                'reference'  => $txn->reference,
                'type'       => $txn->type ?: 'General',
                'amount'     => $txn->amount,
                'status'     => $txn->status,
                'created_at' => $txn->created_at,
            ]);

        $totalFees = $latestAssessment
            ? (float) $latestAssessment->total_assessment
            : (float) $totalCharges;

        $unreadReminders = PaymentReminder::where('user_id', $user->id)
            ->where('status', '!=', PaymentReminder::STATUS_DISMISSED)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'id'                  => $r->id,
                'type'                => $r->type,
                'message'             => $r->message,
                'outstanding_balance' => (float) $r->outstanding_balance,
                'status'              => $r->status,
                'read_at'             => $r->read_at,
                'sent_at'             => $r->sent_at,
                'trigger_reason'      => $r->trigger_reason,
            ]);

        $unreadReminderCount = PaymentReminder::where('user_id', $user->id)
            ->where('status', PaymentReminder::STATUS_SENT)
            ->count();

        return Inertia::render('Student/Dashboard', [
            'account'             => $account,
            'notifications'       => $notifications,
            'recentTransactions'  => $recentTransactions,
            'latestAssessment'    => $latestAssessment ? [
                'id'                => $latestAssessment->id,
                'assessment_number' => $latestAssessment->assessment_number,
                'total_assessment'  => (float) $latestAssessment->total_assessment,
                'status'            => $latestAssessment->status,
                'created_at'        => $latestAssessment->created_at,
            ] : null,
            'paymentTerms'        => $paymentTerms->map(fn ($t) => [
                'id'         => $t->id,
                'term_name'  => $t->term_name,
                'term_order' => $t->term_order,
                'percentage' => $t->percentage,
                'amount'     => (float) $t->amount,
                'balance'    => (float) $t->balance,
                'due_date'   => $t->due_date,
                'status'     => $t->status,
                'remarks'    => $t->remarks,
                'paid_date'  => $t->paid_date,
            ])->toArray(),
            'stats'               => [
                'total_fees'            => $totalFees,
                'total_paid'            => (float) $totalPayments,
                'remaining_balance'     => (float) $remainingBalance,
                'pending_charges_count' => $pendingChargesCount,
            ],
            'paymentReminders'    => $unreadReminders,
            'unreadReminderCount' => $unreadReminderCount,
        ]);
    }
}