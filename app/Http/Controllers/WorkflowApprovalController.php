<?php

namespace App\Http\Controllers;

use App\Models\WorkflowApproval;
use App\Models\WorkflowInstance;
use App\Models\StudentPaymentTerm;
use App\Models\StudentAssessment;
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

        if ($userRole === 'accounting') {
            // Accounting can see ALL approvals on payment_approval workflows,
            // regardless of which specific user ID was assigned as approver.
            $query->whereHas('workflowInstance.workflow', function ($wq) {
                $wq->where('type', 'payment_approval');
            });
        } else {
            // Other roles can only see approvals explicitly assigned to them.
            $query->where('approver_id', $user->id);
        }

        $approvals = $query
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Approvals/Index', [
            'approvals' => $approvals,
            'filters'   => $request->only(['status', 'year', 'semester']),
        ]);
    }

    public function show(WorkflowApproval $approval)
    {
        $this->authorize('view', $approval);

        $approval->load([
            'workflowInstance.workflow',
            'workflowInstance.workflowable.user',
            'workflowInstance.approvals',
        ]);

        $transaction = $approval->workflowInstance->workflowable;
        $student     = null;
        $unpaidTerms = collect();
        $assessment  = null;

        if ($transaction instanceof \App\Models\Transaction && $transaction->user && $transaction->user->student) {
            $student = $transaction->user->student->load('user');

            // ✅ FIX: Load assessment from transaction meta so the Show page
            // can display full assessment details alongside the payment.
            $assessmentId = $transaction->meta['assessment_id'] ?? null;
            if ($assessmentId) {
                $assessment = StudentAssessment::find($assessmentId);
            }

            $unpaidTerms = StudentPaymentTerm::whereHas('assessment', function ($q) use ($transaction) {
                    $q->where('user_id', $transaction->user_id);
                })
                ->whereIn('status', ['pending', 'partial'])
                ->orderBy('due_date', 'asc')
                ->get();
        }

        return Inertia::render('Approvals/Show', [
            'approval'    => $approval,
            'student'     => $student,
            'unpaidTerms' => $unpaidTerms,
            'assessment'  => $assessment, // ✅ FIX: was missing, now passed to frontend
        ]);
    }

    public function approve(Request $request, WorkflowApproval $approval)
    {
        $this->authorize('approve', $approval);

        if ($approval->status !== 'pending') {
            return back()->with('flash.error', 'This approval has already been processed.');
        }

        $validated = $request->validate([
            'comments' => 'nullable|string|max:1000',
        ]);

        try {
            $this->workflowService->approveStep(
                $approval,
                auth()->id(),
                $validated['comments'] ?? null
            );
        } catch (\Exception $e) {
            // approveStep rolls back the DB transaction on failure, so the
            // approval record stays 'pending' — accounting can retry safely.
            return back()->with('flash.error', $e->getMessage());
        }

        return redirect()->route('approvals.index')
            ->with('flash.success', 'Payment approved successfully.');
    }

    public function reject(Request $request, WorkflowApproval $approval)
    {
        $this->authorize('approve', $approval);

        if ($approval->status !== 'pending') {
            return back()->with('flash.error', 'This approval has already been processed.');
        }

        $validated = $request->validate([
            'comments' => 'required|string|max:1000',
        ]);

        try {
            $this->workflowService->rejectStep(
                $approval,
                auth()->id(),
                $validated['comments']
            );
        } catch (\Exception $e) {
            return back()->with('flash.error', $e->getMessage());
        }

        return redirect()->route('approvals.index')
            ->with('flash.success', 'Payment declined.');
    }
}