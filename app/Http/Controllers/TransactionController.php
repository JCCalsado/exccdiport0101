<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Transaction;
use App\Models\Fee;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use App\Services\AccountService;
use App\Services\WorkflowService;
use App\Models\Workflow;

class TransactionController extends Controller
{
    public function __construct(protected WorkflowService $workflowService)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        // Admins & accounting see all, students see their own
        if (in_array($user->role->value, ['super_admin', 'admin', 'accounting'])) {
            $transactions = Transaction::with('user')
                ->orderByDesc('year')
                ->orderByDesc('semester')
                ->get()
                ->groupBy(fn($txn) => $this->getTransactionGroupKey($txn));
        } else {
            $transactions = $user->transactions()
                ->with('user')
                ->orderByDesc('year')
                ->orderByDesc('semester')
                ->get()
                ->groupBy(fn($txn) => $this->getTransactionGroupKey($txn));
        }

        return Inertia::render('Transactions/Index', [
            'auth' => ['user' => $user],
            'transactionsByTerm' => $transactions,
            'account' => $user->account,
            'currentTerm' => $this->getCurrentTerm(),
        ]);
    }

    private function getTransactionGroupKey($txn): string
    {
        // If year and semester are present, use them
        if (!empty($txn->year) && !empty($txn->semester)) {
            return "{$txn->year} {$txn->semester}";
        }
        
        // Fallback: use current term if year/semester are empty
        if (empty($txn->year) && empty($txn->semester)) {
            return $this->getCurrentTerm();
        }
        
        // Partial data: return what we have
        $label = trim("{$txn->year} {$txn->semester}");
        return $label ?: $this->getCurrentTerm();
    }

    private function getCurrentTerm(): string
    {
        $month = now()->month;
        $year = now()->year;

        if ($month >= 6 && $month <= 10) {
            // 1st Semester: June–October → school year started this calendar year
            $schoolYearStart = $year;
            $semester = '1st Sem';
        } elseif ($month >= 11) {
            // 2nd Semester starts November → school year started this calendar year
            $schoolYearStart = $year;
            $semester = '2nd Sem';
        } else {
            // 2nd Semester continuing: Jan–May → school year started last calendar year
            $schoolYearStart = $year - 1;
            $semester = '2nd Sem';
        }

        return "{$schoolYearStart} {$semester}";
    }
    public function create()
    {
        $users = User::select('id', 'first_name', 'last_name', 'middle_initial', 'email')->get();

        return Inertia::render('Transactions/Create', [
            'users' => $users,
        ]);
    }

    public function store(Request $request)
    {
        // Only staff can create transactions
        if (!in_array($request->user()->role->value, ['super_admin', 'admin', 'accounting'])) {
            abort(403, 'Unauthorized action.');
        }

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:charge,payment',
            'payment_channel' => 'nullable|string',
        ]);

        $transaction = Transaction::create([
            'user_id' => $data['user_id'],
            'reference' => 'SYS-' . Str::upper(Str::random(8)),
            'kind' => $data['type'],
            'type' => 'Manual Entry',
            'amount' => $data['amount'],
            'status' => $data['type'] === 'payment' ? 'paid' : 'pending',
            'payment_channel' => $data['payment_channel'] ?? null,
            'year' => now()->year,
            'semester' => $this->getCurrentTerm(),
        ]);

        // Recalculate balance
        $this->recalculateAccount($transaction->user);

        return redirect()->route('transactions.index')
            ->with('success', 'Transaction created successfully!');
    }

    public function show(Transaction $transaction)
    {
        return Inertia::render('Transactions/Show', [
            'transaction' => $transaction->load('user'),
        ]);
    }

    // This shouldn't be allowed
    // public function destroy(Transaction $transaction)
    // {
    //     $this->authorize('delete', $transaction);

    //     $transaction->delete();

    //     // Recalculate after deletion
    //     $this->recalculateAccount($transaction->user);

    //     return redirect()->route('transactions.index')
    //         ->with('success', 'Transaction deleted.');
    // }

    // protected function recalculateAccount($user): void
    // {
    //     $charges = $user->transactions()->where('type', 'charge')->sum('amount');
    //     $payments = $user->transactions()->where('type', 'payment')->where('status', 'paid')->sum('amount');
    //     $balance = $charges - $payments;

    //     $account = $user->account ?? $user->account()->create();
    //     $account->update(['balance' => $balance]);
    // }

    // public function payNow(Request $request)
    // {
    //     $user = $request->user();

    //     $data = $request->validate([
    //         'amount' => 'required|numeric|min:0.01',
    //         'payment_method' => 'required|string',
    //         'reference_number' => 'nullable|string',
    //         'paid_at' => 'required|date',
    //         'description' => 'required|string',
    //     ]);

    //     $tx = Transaction::create([
    //         'user_id' => $user->id,
    //         'reference' => 'PAY-' . Str::upper(Str::random(8)),
    //         'type' => 'payment',
    //         'amount' => $data['amount'],
    //         'status' => 'paid',
    //         'payment_channel' => $data['payment_method'],
    //         'paid_at' => $data['paid_at'],
    //         'meta' => [
    //             'reference_number' => $data['reference_number'] ?? null,
    //             'description' => $data['description'],
    //         ],
    //     ]);

    //     // update account balance
    //     $this->recalculateAccount($user);

    //     // ✅ Only check promotion if user has a student profile
    //     if ($user->role === 'student' && $user->student) {
    //         $this->checkAndPromoteStudent($user->student);
    //     }

    //     return redirect()->route('student.account')
    //         ->with('success', 'Payment recorded successfully.');
    // }

    // protected function checkAndPromoteStudent($student)
    // {
    //     if (!$student) {
    //         return; // no student profile, nothing to do
    //     }

    //     $user = $student->user;
    //     if (!$user) {
    //         return; // student not linked to user
    //     }

    //     AccountService::recalculate($user);

    //     $account = $user->account;

    //     if ($account && $account->balance <= 0) {
    //         $this->promoteYearLevel($student);
    //         $this->assignNextPayables($student);
    //     }
    // }

    // protected function promoteYearLevel($student)
    // {
    //     $levels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
    //     $currentIndex = array_search($student->year_level, $levels);

    //     if ($currentIndex !== false && $currentIndex < count($levels) - 1) {
    //         $student->year_level = $levels[$currentIndex + 1];
    //         $student->save();
    //     }
    // }

    // protected function assignNextPayables($student)
    // {
    //     // find fees for the new year/semester
    //     $fees = Fee::where('year_level', $student->year_level)
    //         ->where('semester', '1st Sem') // or detect dynamically
    //         ->get();

    //     foreach ($fees as $fee) {
    //         $student->transactions()->create([
    //             'reference' => 'FEE-' . strtoupper($fee->name) . '-' . $student->id,
    //             'type' => 'charge',
    //             'amount' => $fee->amount,
    //             'status' => 'pending',
    //             'meta' => ['description' => $fee->name],
    //         ]);
    //     }
    // }

    public function payNow(Request $request)
    {
        $user = $request->user();

        // Determine allowed payment methods based on user role
        // Students cannot use 'cash' - only admin and accounting can record cash payments
        $isStudent = $user->role === \App\Enums\UserRoleEnum::STUDENT;
        
        if ($isStudent) {
            $allowedMethods = ['gcash', 'bank_transfer', 'credit_card', 'debit_card'];
        } else {
            $allowedMethods = ['cash', 'gcash', 'bank_transfer', 'credit_card', 'debit_card'];
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => ['required', 'string', \Illuminate\Validation\Rule::in($allowedMethods)],
            'paid_at' => 'required|date',
            'description' => 'nullable|string|max:255',
            'selected_term_id' => 'required|exists:student_payment_terms,id',
        ]);

        try {
            $paymentService = new \App\Services\StudentPaymentService();

            // Students require approval; staff (admin/accounting) bypass it
            $requiresApproval = $isStudent;

            $result = $paymentService->processPayment($user, (float) $data['amount'], [
                'payment_method'   => $data['payment_method'],
                'paid_at'          => $data['paid_at'],
                'description'      => $data['description'] ?? null,
                'selected_term_id' => (int) $data['selected_term_id'],
                'term_name'        => \App\Models\StudentPaymentTerm::find($data['selected_term_id'])?->term_name,
            ], $requiresApproval);  // ← pass the flag

            // ✅ BUG FIX: Start payment approval workflow for student payments
            // Previously, processPayment() only created a Transaction with status='pending'
            // but never started the workflow — so accounting never saw any approval request.
            if ($requiresApproval) {
                $paymentWorkflow = Workflow::where('type', 'payment_approval')
                    ->where('is_active', true)
                    ->first();

                if ($paymentWorkflow) {
                    $transaction = \App\Models\Transaction::find($result['transaction_id']);
                    try {
                        $this->workflowService->startWorkflow(
                            $paymentWorkflow,
                            $transaction,
                            $user->id
                        );
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('Failed to start payment approval workflow', [
                            'transaction_id' => $result['transaction_id'],
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Trigger payment recorded event for notifications (for verified payments only)
            if (!$requiresApproval) {
                event(new \App\Events\PaymentRecorded(
                    $user,
                    $result['transaction_id'],
                    (float) $data['amount'],
                    $result['transaction_reference']
                ));
            }

            // ✅ Only check promotion if user has a student profile and payment is approved
            if ($isStudent && $user->student && !$requiresApproval) {
                $this->checkAndPromoteStudent($user->student);
            }

            return redirect()->route('student.account', ['tab' => 'history'])
                ->with('success', $result['message']);

        } catch (\Exception $e) {
            // Throw validation exception so it appears as form error
            throw \Illuminate\Validation\ValidationException::withMessages([
                'amount' => $e->getMessage(),
            ]);
        }
    }

    protected function recalculateAccount($user): void
    {
        $charges = $user->transactions()->where('kind', 'charge')->sum('amount');
        $payments = $user->transactions()->where('kind', 'payment')->where('status', 'paid')->sum('amount');
        $balance = $charges - $payments;

        $account = $user->account ?? $user->account()->create();
        $account->update(['balance' => $balance]);
    }

    protected function checkAndPromoteStudent($student)
    {
        if (!$student) {
            return;
        }

        $user = $student->user;
        if (!$user) {
            return;
        }

        $account = $user->account;

        if ($account && $account->balance <= 0) {
            $this->promoteYearLevel($student);
            $this->assignNextPayables($student);
        }
    }

    protected function promoteYearLevel($student)
    {
        $levels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        $currentIndex = array_search($student->year_level, $levels);

        if ($currentIndex !== false && $currentIndex < count($levels) - 1) {
            $student->year_level = $levels[$currentIndex + 1];
            $student->save();
        }
    }

    protected function assignNextPayables($student)
    {
        // find fees for the new year/semester
        $fees = \App\Models\Fee::where('year_level', $student->year_level)
            ->where('semester', '1st Sem')
            ->get();

        foreach ($fees as $fee) {
            $student->user->transactions()->create([
                'reference' => 'FEE-' . strtoupper($fee->name) . '-' . $student->id,
                'kind' => 'charge',
                'type' => $fee->name,
                'amount' => $fee->amount,
                'status' => 'pending',
                'meta' => ['description' => $fee->name],
            ]);
        }
    }
    public function download()
    {
        $transactions = Transaction::with('fee')->orderBy('created_at', 'desc')->get();

        $pdf = Pdf::loadView('pdf.transactions', [
            'transactions' => $transactions
        ]);

        return $pdf->download('transactions.pdf');
    }
}