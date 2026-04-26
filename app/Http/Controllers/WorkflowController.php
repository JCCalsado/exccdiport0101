<?php

namespace App\Http\Controllers;

use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkflowController extends Controller
{
    public function __construct(protected WorkflowService $workflowService)
    {
    }

    public function index(Request $request)
    {
        $workflows = Workflow::query()
            ->when($request->type, fn($q, $type) => $q->where('type', $type))
            ->when($request->search, fn($q, $search) => 
                $q->where('name', 'like', "%{$search}%")
            )
            ->latest()
            ->paginate(15);

        return Inertia::render('Workflows/Index', [
            'workflows' => $workflows,
            'filters' => $request->only(['type', 'search']),
        ]);
    }

    public function show(Workflow $workflow)
    {
        $workflow->load(['instances' => fn($q) => $q->latest()->limit(10)]);

        return Inertia::render('Workflows/Show', [
            'workflow' => $workflow,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:student,accounting,general',
            'description' => 'nullable|string',
            'steps' => 'required|array|min:1',
            'steps.*.name' => 'required|string',
            'steps.*.requires_approval' => 'boolean',
            'steps.*.approvers' => 'array',
        ]);

        $workflow = Workflow::create($validated);

        return redirect()->route('workflows.show', $workflow)
            ->with('success', 'Workflow created successfully');
    }

    public function update(Request $request, Workflow $workflow)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:student,accounting,general',
            'description' => 'nullable|string',
            'steps' => 'sometimes|array|min:1',
            'is_active' => 'boolean',
        ]);

        $workflow->update($validated);

        return redirect()->route('workflows.show', $workflow)
            ->with('success', 'Workflow updated successfully');
    }

    public function destroy(Workflow $workflow)
    {
        $workflow->delete();

        return redirect()->route('workflows.index')
            ->with('success', 'Workflow deleted successfully');
    }
}