<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowApproval;
use App\Services\WorkflowService;
use Illuminate\Http\Request;

class WorkflowApiController extends Controller
{
    public function __construct(protected WorkflowService $workflowService)
    {
    }

    public function index(Request $request)
    {
        $workflows = Workflow::query()
            ->when($request->type, fn($q, $type) => $q->where('type', $type))
            ->active()
            ->get();

        return response()->json($workflows);
    }

    public function show(Workflow $workflow)
    {
        return response()->json($workflow);
    }

    public function instances(Workflow $workflow)
    {
        $instances = $workflow->instances()
            ->with(['workflowable', 'approvals'])
            ->latest()
            ->paginate(20);

        return response()->json($instances);
    }

    public function instanceDetail(WorkflowInstance $instance)
    {
        $instance->load(['workflow', 'workflowable', 'approvals.approver']);

        return response()->json($instance);
    }

    public function advanceInstance(WorkflowInstance $instance)
    {
        if ($instance->status !== 'in_progress') {
            return response()->json([
                'error' => 'Workflow is not in progress'
            ], 400);
        }

        $this->workflowService->advanceWorkflow($instance, auth()->id());

        return response()->json([
            'message' => 'Workflow advanced successfully',
            'instance' => $instance->fresh(),
        ]);
    }

    public function myApprovals(Request $request)
    {
        $approvals = WorkflowApproval::query()
            ->with(['workflowInstance.workflow', 'workflowInstance.workflowable'])
            ->where('approver_id', auth()->id())
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(20);

        return response()->json($approvals);
    }

    public function approve(Request $request, WorkflowApproval $approval)
    {
        $this->authorize('approve', $approval);

        $validated = $request->validate([
            'comments' => 'nullable|string|max:1000',
        ]);

        $this->workflowService->approveStep(
            $approval,
            auth()->id(),
            $validated['comments'] ?? null
        );

        return response()->json([
            'message' => 'Approval granted successfully',
        ]);
    }

    public function reject(Request $request, WorkflowApproval $approval)
    {
        $this->authorize('approve', $approval);

        $validated = $request->validate([
            'comments' => 'required|string|max:1000',
        ]);

        $this->workflowService->rejectStep(
            $approval,
            auth()->id(),
            $validated['comments']
        );

        return response()->json([
            'message' => 'Approval rejected',
        ]);
    }
}