<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Fee;
use App\Models\StudentAssessment;
use App\Models\Notification;

class StudentAccountController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if (! $user->account) {
            $user->account()->create(['balance' => 0]);
        }

        $user->load(['transactions' => fn ($q) => $q->orderByDesc('created_at')]);

        $year  = now()->year;
        $month = now()->month;

        if ($month >= 6 && $month <= 10) {
            $semester = '1st Sem';
        } elseif ($month >= 11 || $month <= 3) {
            $semester = '2nd Sem';
        } else {
            $semester = 'Summer';
        }

        $fees = Fee::active()
            ->where('year_level', $user->year_level)
            ->where('semester', $semester)
            ->where('school_year', $year . '-' . ($year + 1))
            ->select('name', 'amount', 'category')
            ->get();

        if ($fees->isEmpty()) {
            $fees = collect([
                ['name' => 'Registration Fee', 'amount' => 200.0,  'category' => 'Miscellaneous'],
                ['name' => 'Tuition Fee',       'amount' => 5000.0, 'category' => 'Tuition'],
                ['name' => 'Lab Fee',            'amount' => 2000.0, 'category' => 'Laboratory'],
                ['name' => 'Library Fee',        'amount' => 500.0,  'category' => 'Library'],
                ['name' => 'Misc. Fee',          'amount' => 1200.0, 'category' => 'Miscellaneous'],
            ]);
        }

        $latestAssessment = StudentAssessment::where('user_id', $user->id)
            ->with('paymentTerms')
            ->latest('created_at')
            ->first();

        $paymentTerms = [];
        if ($latestAssessment) {
            $paymentTerms = $latestAssessment->paymentTerms()
                ->orderBy('term_order')
                ->get()
                ->map(fn ($t) => [
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
                ])
                ->toArray();
        }

        $notifications = Notification::active()
            ->forUser($user->id)
            ->withinDateRange()
            ->forDueDateTrigger($user)
            ->orderByDesc('created_at')
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
                'user_id'         => $n->user_id,
                'is_active'       => $n->is_active,
                'is_complete'     => $n->is_complete,
                'dismissed_at'    => $n->dismissed_at,
                'created_at'      => $n->created_at,
            ]);

        return Inertia::render('Student/AccountOverview', [
            'account'                 => $user->account,
            'transactions'            => $user->transactions ?? [],
            'fees'                    => $fees->values(),
            'latestAssessment'        => $latestAssessment,
            'paymentTerms'            => $paymentTerms,
            'notifications'           => $notifications,
            'pendingApprovalPayments' => $user->transactions
                ->filter(fn ($t) => $t->kind === 'payment' && $t->status === 'awaiting_approval')
                ->map(fn ($t) => [
                    'id'               => $t->id,
                    'reference'        => $t->reference,
                    'amount'           => (float) $t->amount,
                    'selected_term_id' => isset($t->meta['selected_term_id'])
                        ? (int) $t->meta['selected_term_id']
                        : null,
                    'term_name'        => $t->meta['term_name'] ?? 'General',
                    'created_at'       => $t->created_at,
                ])
                ->values(),
        ]);
    }
}