<?php

namespace App\Http\Controllers;

use App\Models\AccountingTransaction;
use App\Models\Workflow;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountingTransactionController extends Controller
{
    public function __construct(protected WorkflowService $workflowService)
    {
    }

    public function index(Request $request)
    {
        $transactions = AccountingTransaction::query()
            ->with('transactionable')
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->type, fn($q, $type) => $q->where('type', $type))
            ->when($request->search, fn($q, $search) => 
                $q->where('transaction_number', 'like', "%{$search}%")
            )
            ->latest()
            ->paginate(15);

        return Inertia::render('Accounting/Index', [
            'transactions' => $transactions,
            'filters' => $request->only(['status', 'type', 'search']),
        ]);
    }

    public function show(AccountingTransaction $transaction)
    {
        $transaction->load(['transactionable', 'workflowInstances.workflow']);

        return Inertia::render('Accounting/Show', [
            'transaction' => $transaction,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:invoice,payment,refund,adjustment',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'transactionable_type' => 'required|string',
            'transactionable_id' => 'required|integer',
            'description' => 'nullable|string',
            'transaction_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:transaction_date',
        ]);

        $validated['status'] = 'draft';

        $transaction = AccountingTransaction::create($validated);

        return redirect()->route('accounting.show', $transaction)
            ->with('success', 'Transaction created successfully');
    }

    public function submitForApproval(AccountingTransaction $transaction)
    {
        if ($transaction->status !== 'draft') {
            return back()->withErrors(['error' => 'Only draft transactions can be submitted']);
        }

        $workflow = Workflow::active()
            ->where('type', 'accounting')
            ->where('name', 'like', '%approval%')
            ->first();

        if (!$workflow) {
            return back()->withErrors(['error' => 'No accounting approval workflow found']);
        }

        $this->workflowService->startWorkflow(
            $workflow,
            $transaction,
            auth()->id()
        );

        $transaction->update(['status' => 'pending_approval']);

        return redirect()->route('accounting.show', $transaction)
            ->with('success', 'Transaction submitted for approval');
    }

    public function update(Request $request, AccountingTransaction $transaction)
    {
        if ($transaction->status !== 'draft') {
            return back()->withErrors(['error' => 'Only draft transactions can be updated']);
        }

        $validated = $request->validate([
            'type' => 'sometimes|in:invoice,payment,refund,adjustment',
            'amount' => 'sometimes|numeric|min:0',
            'description' => 'nullable|string',
            'transaction_date' => 'sometimes|date',
            'due_date' => 'nullable|date|after_or_equal:transaction_date',
        ]);

        $transaction->update($validated);

        return redirect()->route('accounting.show', $transaction)
            ->with('success', 'Transaction updated successfully');
    }

    public function destroy(AccountingTransaction $transaction)
    {
        if (!in_array($transaction->status, ['draft', 'cancelled'])) {
            return back()->withErrors(['error' => 'Only draft or cancelled transactions can be deleted']);
        }

        $transaction->delete();

        return redirect()->route('accounting.index')
            ->with('success', 'Transaction deleted successfully');
    }
}