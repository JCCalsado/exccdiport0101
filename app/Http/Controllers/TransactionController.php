<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Transaction;
use App\Models\User;
use App\Models\StudentPaymentTerm;
use App\Models\Workflow;
use App\Enums\UserRoleEnum;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Illuminate\Support\Str;
use App\Services\AccountService;
use App\Services\WorkflowService;
use App\Services\StudentPaymentService;
use App\Events\PaymentRecorded;

class TransactionController extends Controller
{
    public function __construct(protected WorkflowService $workflowService)
    {
    }

    // ─── index ────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $user = $request->user();

        if (in_array($user->role->value, ['super_admin', 'admin', 'accounting'])) {
            $transactions = Transaction::with('user')
                ->orderByDesc('year')
                ->orderByDesc('semester')
                ->get()
                ->groupBy(fn($txn) => $this->getTransactionGroupKey($txn));

            $currentTerm = $this->getCurrentTerm();
        } else {
            $transactions = $user->transactions()
                ->with('user')
                ->orderByDesc('year')
                ->orderByDesc('semester')
                ->get()
                ->groupBy(fn($txn) => $this->getTransactionGroupKey($txn));

            // ── Bug #2 Fix: for students, resolve currentTerm from their ──────
            // latest assessment so the correct term group is auto-expanded.
            // This ensures newly-created assessments expand the right term even
            // when the server-time semester differs from the assessment semester.
            $latestAssessment = \App\Models\StudentAssessment::where('user_id', $user->id)
                ->where('status', 'active')
                ->latest()
                ->first();

            if ($latestAssessment) {
                $yearNum     = explode('-', $latestAssessment->school_year)[0] ?? now()->year;
                $currentTerm = trim("{$yearNum} {$latestAssessment->semester}");
            } else {
                $currentTerm = $this->getCurrentTerm();
            }
        }

        return Inertia::render('Transactions/Index', [
            'auth'               => ['user' => $user],
            'transactionsByTerm' => $transactions,
            'account'            => $user->account,
            'currentTerm'        => $currentTerm,
        ]);
    }

    // ─── create / store ───────────────────────────────────────────────────────

    public function create()
    {
        $users = User::select('id', 'first_name', 'last_name', 'middle_initial', 'email')->get();

        return Inertia::render('Transactions/Create', [
            'users' => $users,
        ]);
    }

    public function store(Request $request)
    {
        if (!in_array($request->user()->role->value, ['super_admin', 'admin', 'accounting'])) {
            abort(403, 'Unauthorized action.');
        }

        $data = $request->validate([
            'user_id'         => 'required|exists:users,id',
            'amount'          => 'required|numeric|min:0.01',
            'type'            => 'required|in:charge,payment',
            'payment_channel' => 'nullable|string',
        ]);

        $transaction = Transaction::create([
            'user_id'         => $data['user_id'],
            'reference'       => 'SYS-' . Str::upper(Str::random(8)),
            'kind'            => $data['type'],
            'type'            => 'Manual Entry',
            'amount'          => $data['amount'],
            'status'          => $data['type'] === 'payment' ? 'paid' : 'pending',
            'payment_channel' => $data['payment_channel'] ?? null,
            'year'            => (string) now()->year,
            'semester'        => $this->getCurrentSemesterLabel(),
            'meta'            => [
                'description' => 'Manual entry by ' . $request->user()->name,
            ],
        ]);

        $this->recalculateAccount($transaction->user);

        return redirect()->route('transactions.index')
            ->with('success', 'Transaction created successfully!');
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function show(Transaction $transaction)
    {
        return Inertia::render('Transactions/Show', [
            'transaction' => $transaction->load('user'),
            'account'     => $transaction->user->account,
        ]);
    }

    // ─── receipt ─────────────────────────────────────────────────────────────

    public function receipt(Request $request, Transaction $transaction)
    {
        $authUser = $request->user();
        $isStaff  = in_array($authUser->role->value, ['super_admin', 'admin', 'accounting']);

        if (!$isStaff && $transaction->user_id !== $authUser->id) {
            abort(403, 'You do not have permission to view this receipt.');
        }

        // ── Block receipts for unconfirmed payments ───────────────────────────
        // Only fully paid transactions may generate a receipt PDF.
        // Awaiting-approval payments have not yet been verified by accounting,
        // so issuing a receipt would be premature and potentially misleading.
        if ($transaction->status === 'awaiting_approval') {
            abort(403, 'Receipt is not available yet. Your payment is still awaiting accounting verification.');
        }

        if ($transaction->kind !== 'payment') {
            abort(400, 'Receipts are only available for payment transactions.');
        }

        $targetUser = $transaction->user->load('account', 'student');

        $currentBalance = (float) ($targetUser->account->balance ?? 0);
        $paymentAmount  = (float) $transaction->amount;

        if ($transaction->status === 'paid') {
            $balanceBefore    = round($currentBalance + $paymentAmount, 2);
            $remainingBalance = round($currentBalance, 2);
        } else {
            $balanceBefore    = round($currentBalance, 2);
            $remainingBalance = round($currentBalance - $paymentAmount, 2);
        }

        $pdf = Pdf::loadView('pdf.receipt', [
            'transaction'      => $transaction,
            'student'          => $targetUser,
            'balanceBefore'    => $balanceBefore,
            'remainingBalance' => $remainingBalance,
        ]);

        $pdf->setPaper('A4', 'portrait');

        $studentId = $targetUser->account_id ?? 'unknown';
        $ref       = str_replace(['/', ' '], '-', $transaction->reference ?? (string) $transaction->id);
        $filename  = "receipt-{$studentId}-{$ref}.pdf";

        return $pdf->download($filename);
    }

    // ─── download ─────────────────────────────────────────────────────────────

    public function download(Request $request)
    {
        $authUser = $request->user();
        $isStaff  = in_array($authUser->role->value, ['super_admin', 'admin', 'accounting']);

        if ($isStaff && $request->filled('user_id')) {
            $targetUser = User::with('account', 'student')->findOrFail((int) $request->user_id);
        } else {
            $targetUser = $authUser->load('account', 'student');
        }

        $query = Transaction::where('user_id', $targetUser->id)
            ->with('fee')
            ->orderBy('year', 'desc')
            ->orderBy('created_at', 'desc');

        $termKey = $request->input('term');
        if ($termKey && $termKey !== 'All Terms') {
            $parts    = explode(' ', $termKey, 2);
            $termYear = $parts[0] ?? null;
            $termSem  = $parts[1] ?? null;

            if ($termYear && $termSem) {
                $query->where('year', $termYear)
                      ->where('semester', $termSem);
            }
        }

        $transactions = $query->get();

        // ── Exclude awaiting_approval from the PDF ────────────────────────────
        // Only confirmed (paid) payments and charges should appear in the Term
        // Summary PDF. Unverified payments are excluded from the document and
        // from the balance calculations to avoid misrepresenting the account.
        $transactions = $transactions->filter(function ($txn) {
            // Always include charges (assessment items)
            if ($txn->kind === 'charge') return true;
            // For payments, only include those that are confirmed paid
            return $txn->kind === 'payment' && $txn->status === 'paid';
        });

        // ── Block download if there are no paid transactions ─────────────────
        // If every payment in this term is still awaiting approval, the PDF
        // would show an incomplete or misleading balance. Prevent the download.
        $paidPaymentsExist = $transactions->where('kind', 'payment')->where('status', 'paid')->isNotEmpty();
        $chargesExist      = $transactions->where('kind', 'charge')->isNotEmpty();

        if (!$paidPaymentsExist && !$chargesExist) {
            abort(403, 'No confirmed transactions available for this term. Awaiting-approval payments cannot be downloaded yet.');
        }

        $totalCharges = $transactions->where('kind', 'charge')->sum('amount');
        $totalPaid    = $transactions->where('kind', 'payment')->where('status', 'paid')->sum('amount');
        $netBalance   = round((float) $totalCharges - (float) $totalPaid, 2);

        $pdf = Pdf::loadView('pdf.transactions', [
            'transactions' => $transactions,
            'student'      => $targetUser,
            'termKey'      => $termKey ?: 'All Terms',
            'totalCharges' => $totalCharges,
            'totalPaid'    => $totalPaid,
            'netBalance'   => $netBalance,
        ]);

        $pdf->setPaper('A4', 'portrait');

        $accountId = $targetUser->account_id ?? 'unknown';
        $termSlug  = $termKey ? str_replace([' ', '/'], '-', $termKey) : 'all-terms';
        $filename  = "transactions-{$accountId}-{$termSlug}.pdf";

        return $pdf->download($filename);
    }

    // ─── payNow ───────────────────────────────────────────────────────────────

    /**
     * Process a payment submission from a student or staff.
     *
     * Students' payments require accounting approval:
     *   1. Transaction is created with status = 'awaiting_approval'
     *   2. A WorkflowInstance + WorkflowApproval record are created
     *   3. Accounting users see the pending approval in /approvals
     *
     * Staff (admin/accounting) bypass approval and are marked 'paid' immediately.
     */
    public function payNow(Request $request)
    {
        $user      = $request->user();
        $isStudent = $user->role === UserRoleEnum::STUDENT;

        $allowedMethods = $isStudent
            ? ['gcash', 'bank_transfer', 'credit_card', 'debit_card']
            : ['cash', 'gcash', 'bank_transfer', 'credit_card', 'debit_card'];

        $data = $request->validate([
            'amount'           => 'required|numeric|min:0.01',
            'payment_method'   => ['required', 'string', Rule::in($allowedMethods)],
            'paid_at'          => 'required|date',
            'description'      => 'nullable|string|max:255',
            'selected_term_id' => 'required|exists:student_payment_terms,id',
        ]);

        try {
            // ─────────────────────────────────────────────────────────────────
            // SERVER-SIDE BALANCE GUARD (Bug Fix #6)
            // The Vue client enforces a max but a user can bypass it via API.
            // We must re-validate the amount against the term's actual balance.
            // ─────────────────────────────────────────────────────────────────
            $term = StudentPaymentTerm::findOrFail((int) $data['selected_term_id']);

            // Ensure the term belongs to this user (security: prevent cross-user payment)
            if ((int) $term->user_id !== (int) $user->id) {
                return back()->withErrors(['payment' => 'Invalid payment term selected.']);
            }

            $termBalance = round((float) $term->balance, 2);
            $paidAmount  = round((float) $data['amount'], 2);

            if ($termBalance <= 0) {
                return back()->withErrors(['payment' => 'This payment term has already been fully paid.']);
            }

            if ($paidAmount > $termBalance) {
                return back()->withErrors([
                    'amount' => sprintf(
                        'Payment amount (₱%s) exceeds the outstanding balance for this term (₱%s).',
                        number_format($paidAmount, 2),
                        number_format($termBalance, 2)
                    ),
                ]);
            }

            // ─────────────────────────────────────────────────────────────────
            // SERVER-SIDE DEDUPLICATION: Prevent duplicate awaiting_approval
            // submissions for the same term.
            // ─────────────────────────────────────────────────────────────────
            if ($isStudent) {
                $alreadyPending = Transaction::where('user_id', $user->id)
                    ->where('status', 'awaiting_approval')
                    ->where('kind', 'payment')
                    ->whereJsonContains('meta->selected_term_id', (int) $data['selected_term_id'])
                    ->exists();

                if ($alreadyPending) {
                    return back()->withErrors(['payment' => 'A payment for this term is already awaiting approval.']);
                }
            }

            $paymentService   = new StudentPaymentService();
            $requiresApproval = $isStudent;

            // ─────────────────────────────────────────────────────────────────
            // TERM KEY FIX (Bug Fix #4)
            // Always derive year and semester from the term's own assessment so
            // the transaction is grouped under the correct term in history.
            // Fallback to server-time only if the assessment is missing.
            // ─────────────────────────────────────────────────────────────────
            $assessment       = $term->assessment;
            $transactionYear  = $assessment
                ? explode('-', $assessment->school_year)[0]
                : (string) now()->year;
            $transactionSem   = $assessment?->semester ?? $this->getCurrentSemesterLabel();

            $result = $paymentService->processPayment($user, $paidAmount, [
                'payment_method'   => $data['payment_method'],
                'paid_at'          => $data['paid_at'],
                'description'      => $data['description'] ?? null,
                'selected_term_id' => (int) $data['selected_term_id'],
                'term_name'        => $term->term_name,
                'year'             => $transactionYear,
                'semester'         => $transactionSem,
            ], $requiresApproval);

            // ── START APPROVAL WORKFLOW FOR STUDENT PAYMENTS ──────────────────
            // This is the critical step that was missing. Without this, the
            // WorkflowApproval record is never created and accounting never
            // receives the submission in their queue.
            if ($requiresApproval) {
                $this->startPaymentApprovalWorkflow($result['transaction_id'], $user->id);
            }

            // ── POST-PROCESSING FOR IMMEDIATELY-APPROVED PAYMENTS ─────────────
            if (!$requiresApproval) {
                event(new PaymentRecorded(
                    $user,
                    $result['transaction_id'] ?? null,
                    (float) $data['amount'],
                    $result['transaction_reference'] ?? 'N/A'
                ));

                if ($user->student) {
                    $this->checkAndPromoteStudent($user->student);
                }
            }

            $message = $requiresApproval
                ? 'Payment submitted successfully. Please wait for accounting approval.'
                : 'Payment recorded successfully!';

            return back()->with([
                'success' => $message,
                'flash'   => [
                    'transaction_id'     => $result['transaction_id'] ?? null,
                    'requires_approval'  => $requiresApproval,
                ],
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('payNow failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['payment' => 'Payment processing failed: ' . $e->getMessage()]);
        }
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Find the active payment_approval workflow and start a workflow instance
     * for the given transaction. This creates WorkflowApproval records so
     * accounting users can see and act on the submission.
     *
     * @throws \Exception if no active payment_approval workflow exists
     */
    private function startPaymentApprovalWorkflow(int $transactionId, int $userId): void
    {
        $workflow = Workflow::active()
            ->where('type', 'payment_approval')
            ->first();

        if (!$workflow) {
            throw new \Exception(
                'No active payment_approval workflow found. ' .
                'Please run: php artisan db:seed --class=PaymentApprovalWorkflowSeeder'
            );
        }

        $transaction = Transaction::findOrFail($transactionId);

        $this->workflowService->startWorkflow($workflow, $transaction, $userId);
    }

    private function getTransactionGroupKey(Transaction $txn): string
    {
        if (!empty($txn->year) && !empty($txn->semester)) {
            return "{$txn->year} {$txn->semester}";
        }

        if (empty($txn->year) && empty($txn->semester)) {
            return $this->getCurrentTerm();
        }

        $label = trim("{$txn->year} {$txn->semester}");
        return $label ?: $this->getCurrentTerm();
    }

    private function getCurrentTerm(): string
    {
        return now()->year . ' ' . $this->getCurrentSemesterLabel();
    }

    private function getCurrentSemesterLabel(): string
    {
        $month = now()->month;
        return ($month >= 6 && $month <= 10) ? '1st Sem' : '2nd Sem';
    }

    protected function recalculateAccount(User $user): void
    {
        $charges  = $user->transactions()->where('kind', 'charge')->sum('amount');
        $payments = $user->transactions()->where('kind', 'payment')->where('status', 'paid')->sum('amount');
        $balance  = round((float) $charges - (float) $payments, 2);

        $account = $user->account ?? $user->account()->create(['balance' => 0]);
        $account->update(['balance' => $balance]);
    }

    protected function checkAndPromoteStudent($student): void
    {
        if (!$student) return;
        $user = $student->user;
        if (!$user) return;
        if ($user->account && $user->account->balance <= 0) {
            $this->promoteYearLevel($student);
            $this->assignNextPayables($student);
        }
    }

    protected function promoteYearLevel($student): void
    {
        $levels       = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        $currentIndex = array_search($student->year_level, $levels);
        if ($currentIndex !== false && $currentIndex < count($levels) - 1) {
            $student->year_level = $levels[$currentIndex + 1];
            $student->save();
        }
    }

    protected function assignNextPayables($student): void
    {
        // Fee management has been disabled.
        // Payables are now created through StudentAssessment and StudentPaymentTerms.
        // This method is retained for backward compatibility but does nothing.
    }
}