<?php

namespace App\Http\Controllers;

use App\Models\WorkflowApproval;
use App\Models\WorkflowInstance;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkflowApprovalController extends Controller
{
    public function __construct(protected WorkflowService $workflowService)
    {
    }

    public function index(Request $request)
    {
        $user     = auth()->user();
        $userRole = $user->role->value ?? null;

        $query = WorkflowApproval::query()
            ->with([
                'workflowInstance.workflow',
                'workflowInstance.workflowable.user',
            ]);

        if (in_array($userRole, ['accounting', 'admin'])) {
            // Accounting and admin can see ALL approvals on payment_approval workflows,
            // regardless of which specific user ID was assigned as approver.
            // This ensures no submission is invisible due to timing of user creation.
            $query->whereHas('workflowInstance.workflow', function ($wq) {
                $wq->where('type', 'payment_approval');
            });
        } else {
            // Other roles can only see approvals explicitly assigned to them.
            $query->where('approver_id', $user->id);
        }

        $approvals = $query
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(15);

        return Inertia::render('Approvals/Index', [
            'approvals' => $approvals,
            'filters'   => $request->only(['status']),
        ]);
    }

    public function show(WorkflowApproval $approval)
    {
        $this->authorize('view', $approval);

        $approval->load([
            'workflowInstance.workflow',
            'workflowInstance.workflowable',
            'workflowInstance.approvals',
        ]);

        $transaction = $approval->workflowInstance->workflowable;
        $student     = null;
        $unpaidTerms = null;

        if ($transaction instanceof \App\Models\Transaction && $transaction->user && $transaction->user->student) {
            $student     = $transaction->user->student;
            $unpaidTerms = \App\Models\StudentPaymentTerm::where('user_id', $transaction->user_id)
                ->whereIn('status', ['pending', 'partial'])
                ->orderBy('due_date', 'asc')
                ->get();
        }

        return Inertia::render('Approvals/Show', [
            'approval'    => $approval,
            'unpaidTerms' => $unpaidTerms,
        ]);
    }

    public function approve(Request $request, WorkflowApproval $approval)
    {
        $this->authorize('approve', $approval);

        if ($approval->status !== 'pending') {
            return back()->withErrors(['error' => 'This approval has already been processed']);
        }

        $validated = $request->validate([
            'comments' => 'nullable|string|max:1000',
        ]);

        $this->workflowService->approveStep(
            $approval,
            auth()->id(),
            $validated['comments'] ?? null
        );

        return redirect()->route('approvals.index')
            ->with('success', 'Payment approved successfully.');
    }

    public function reject(Request $request, WorkflowApproval $approval)
    {
        $this->authorize('approve', $approval);

        if ($approval->status !== 'pending') {
            return back()->withErrors(['error' => 'This approval has already been processed']);
        }

        $validated = $request->validate([
            'comments' => 'required|string|max:1000',
        ]);

        $this->workflowService->rejectStep(
            $approval,
            auth()->id(),
            $validated['comments']
        );

        return redirect()->route('approvals.index')
            ->with('success', 'Payment declined.');
    }
}