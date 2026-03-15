<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Payment;
use App\Models\Workflow;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function __construct(protected WorkflowService $workflowService)
    {
    }

    // ── INDEX — active/pending/suspended students only ────────────────────────
    public function index(Request $request): Response
    {
        $query = Student::with(['user', 'payments', 'transactions', 'account', 'workflowInstances.workflow']);

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->whereHas('user', function ($q) use ($searchTerm) {
                $q->where('first_name', 'like', "%{$searchTerm}%")
                  ->orWhere('last_name', 'like', "%{$searchTerm}%")
                  ->orWhere('email', 'like', "%{$searchTerm}%")
                  ->orWhere('phone', 'like', "%{$searchTerm}%")
                  ->orWhere('address', 'like', "%{$searchTerm}%");
            })
            ->orWhere('student_id', 'like', "%{$searchTerm}%")
            ->orWhere('student_number', 'like', "%{$searchTerm}%");
        }

        if ($request->filled('course')) {
            $query->whereHas('user', fn($q) => $q->where('course', $request->course));
        }

        if ($request->filled('year_level')) {
            $query->whereHas('user', fn($q) => $q->where('year_level', $request->year_level));
        }

        if ($request->filled('status')) {
            $query->where('enrollment_status', $request->status);
        } else {
            // Default view excludes archived students
            $query->whereIn('enrollment_status', ['active', 'pending', 'suspended']);
        }

        $students = $query->orderBy('created_at', 'desc')->paginate(10);

        return Inertia::render('Students/Index', [
            'students' => $students,
            'filters'  => $request->only(['search', 'status', 'course', 'year_level']),
        ]);
    }

    // ── ARCHIVE — graduated, dropped, and inactive students ──────────────────
    public function archive(Request $request): Response
    {
        $archiveStatuses = ['graduated', 'dropped', 'inactive'];

        $query = Student::with(['user', 'account']);

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->whereHas('user', function ($q) use ($searchTerm) {
                $q->where('first_name', 'like', "%{$searchTerm}%")
                  ->orWhere('last_name', 'like', "%{$searchTerm}%")
                  ->orWhere('email', 'like', "%{$searchTerm}%");
            })
            ->orWhere('student_id', 'like', "%{$searchTerm}%")
            ->orWhere('student_number', 'like', "%{$searchTerm}%");
        }

        if ($request->filled('status') && in_array($request->status, $archiveStatuses)) {
            $query->where('enrollment_status', $request->status);
        } else {
            $query->whereIn('enrollment_status', $archiveStatuses);
        }

        $students = $query->orderBy('updated_at', 'desc')->paginate(15);

        $counts = Student::whereIn('enrollment_status', $archiveStatuses)
            ->selectRaw('enrollment_status, COUNT(*) as count')
            ->groupBy('enrollment_status')
            ->pluck('count', 'enrollment_status');

        return Inertia::render('Students/Archive', [
            'students' => $students,
            'filters'  => $request->only(['search', 'status']),
            'counts'   => [
                'graduated' => (int) ($counts['graduated'] ?? 0),
                'dropped'   => (int) ($counts['dropped']   ?? 0),
                'inactive'  => (int) ($counts['inactive']  ?? 0),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Students/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id'        => 'required|string|unique:students',
            'last_name'         => 'required|string|max:255',
            'first_name'        => 'required|string|max:255',
            'middle_initial'    => 'nullable|string|max:10',
            'email'             => 'required|email|unique:users',
            'course'            => 'required|string',
            'year_level'        => 'required|string',
            'birthday'          => 'nullable|date',
            'phone'             => 'nullable|string',
            'address'           => 'nullable|string',
            'total_balance'     => 'nullable|numeric',
            'enrollment_status' => 'sometimes|in:pending,active,suspended,graduated,dropped,inactive',
            'user_id'           => 'nullable|exists:users,id',
        ]);

        // If no user_id provided, create the User first with personal info
        if (empty($validated['user_id'])) {
            $user = \App\Models\User::create([
                'email'         => $validated['email'],
                'last_name'     => $validated['last_name'],
                'first_name'    => $validated['first_name'],
                'middle_initial' => $validated['middle_initial'],
                'course'        => $validated['course'],
                'year_level'    => $validated['year_level'],
                'birthday'      => $validated['birthday'],
                'phone'         => $validated['phone'],
                'address'       => $validated['address'],
                'role'          => \App\Enums\UserRoleEnum::STUDENT,
                'password'      => bcrypt('temporary' . time()), // temporary password
            ]);
            $validated['user_id'] = $user->id;
        }

        if (! isset($validated['student_number'])) {
            $validated['student_number'] = 'STU-' . strtoupper(uniqid());
        }

        $validated['enrollment_status'] = $validated['enrollment_status'] ?? 'pending';
        $validated['total_balance']     = 0.0;

        // Remove personal data fields — these are now in the User record
        unset(
            $validated['last_name'],
            $validated['first_name'],
            $validated['middle_initial'],
            $validated['email'],
            $validated['course'],
            $validated['year_level'],
            $validated['birthday'],
            $validated['phone'],
            $validated['address']
        );

        $student = Student::create($validated);

        if ($student->enrollment_status === 'pending') {
            $workflow = Workflow::active()
                ->where('type', 'student')
                ->where('name', 'like', '%enrollment%')
                ->first();

            if ($workflow) {
                try {
                    $this->workflowService->startWorkflow($workflow, $student, auth()->id());
                    return redirect()->route('students.show', $student)
                        ->with('success', 'Student created and enrollment workflow started');
                } catch (\Exception $e) {
                    logger()->error('Failed to start workflow: ' . $e->getMessage());
                    return redirect()->route('students.show', $student)
                        ->with('warning', 'Student created but workflow failed to start');
                }
            }
        }

        return redirect()->route('students.show', $student)
            ->with('success', 'Student created successfully');
    }

    public function show($id): Response
    {
        $student = Student::with([
            'payments',
            'user.account',
            'workflowInstances.workflow',
            'workflowInstances.approvals.approver',
            'accountingTransactions',
        ])->findOrFail($id);

        $activeWorkflow = $student->workflowInstances()
            ->whereIn('status', ['pending', 'in_progress'])
            ->with(['workflow', 'approvals'])
            ->first();

        return Inertia::render('Students/StudentProfile', [
            'student'        => $student,
            'activeWorkflow' => $activeWorkflow,
        ]);
    }

    public function storePayment(Request $request, Student $student)
    {
        $request->validate([
            'amount'           => 'required|numeric|min:0.01',
            'description'      => 'required|string|max:255',
            'payment_method'   => 'required|string',
            'reference_number' => 'nullable|string',
            'status'           => 'required|string',
            'paid_at'          => 'required|date',
        ]);

        $user = $request->user();

        if ($user->role === 'student') {
            if (! $user->student || $user->student->id !== $student->id) {
                abort(403, 'Unauthorized payment submission.');
            }
        }

        $student->payments()->create($request->only([
            'amount', 'description', 'payment_method', 'reference_number', 'status', 'paid_at',
        ]));

        return back()->with('success', 'Payment recorded successfully!');
    }

    public function edit(Student $student): Response
    {
        return Inertia::render('Students/Edit', ['student' => $student]);
    }

    public function update(Request $request, Student $student)
    {
        $validated = $request->validate([
            'student_id'        => 'required|string|unique:students,student_id,' . $student->id,
            'first_name'        => 'required|string|max:255',
            'last_name'         => 'required|string|max:255',
            'middle_initial'    => 'nullable|string|max:10',
            'email'             => 'required|email|unique:users,email,' . $student->user_id,
            'course'            => 'required|string|max:255',
            'year_level'        => 'required|string',
            'birthday'          => 'nullable|date',
            'phone'             => 'nullable|string|max:20',
            'address'           => 'nullable|string|max:500',
            'total_balance'     => 'required|numeric|min:0',
            'enrollment_status' => 'sometimes|in:pending,active,suspended,graduated,dropped,inactive',
        ]);

        // Update the related User record with personal data
        if ($student->user) {
            $student->user->update([
                'first_name'     => $validated['first_name'],
                'last_name'      => $validated['last_name'],
                'middle_initial' => $validated['middle_initial'],
                'email'          => $validated['email'],
                'course'         => $validated['course'],
                'year_level'     => $validated['year_level'],
                'birthday'       => $validated['birthday'],
                'phone'          => $validated['phone'],
                'address'        => $validated['address'],
            ]);
        }

        // Update student record with student-specific data only
        $statusChanged = $student->enrollment_status !== ($validated['enrollment_status'] ?? $student->enrollment_status);

        $student->update([
            'student_id'        => $validated['student_id'],
            'total_balance'     => $validated['total_balance'],
            'enrollment_status' => $validated['enrollment_status'] ?? $student->enrollment_status,
        ]);

        if ($statusChanged && $student->enrollment_status === 'active') {
            $activeWorkflow = $student->workflowInstances()->where('status', 'in_progress')->first();
            if ($activeWorkflow) {
                $activeWorkflow->update(['status' => 'completed', 'completed_at' => now()]);
            }
        }

        return redirect()->route('students.show', $student)
            ->with('success', 'Student updated successfully!');
    }

    public function destroy(Student $student)
    {
        $student->delete();
        return redirect()->route('students.index')
            ->with('success', 'Student deleted successfully!');
    }

    public function studentProfile(Request $request): Response
    {
        $user = $request->user();

        if ($user->role === 'student') {
            $student = Student::where('email', $user->email)
                ->orWhere('user_id', $user->id)
                ->firstOrFail();
        } else {
            $student = Student::with('payments')->first();
        }

        $student->load([
            'payments',
            'workflowInstances.workflow',
            'workflowInstances.approvals',
        ]);

        $activeWorkflow = $student->workflowInstances()
            ->whereIn('status', ['pending', 'in_progress'])
            ->with(['workflow', 'approvals'])
            ->first();

        return Inertia::render('Students/StudentProfile', [
            'student'        => $student,
            'activeWorkflow' => $activeWorkflow,
        ]);
    }

    public function advanceWorkflow(Student $student)
    {
        $activeWorkflow = $student->workflowInstances()->where('status', 'in_progress')->first();

        if (! $activeWorkflow) {
            return back()->withErrors(['error' => 'No active workflow found for this student']);
        }

        try {
            $this->workflowService->advanceWorkflow($activeWorkflow, auth()->id());
            return back()->with('success', 'Workflow advanced to next step');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to advance workflow: ' . $e->getMessage()]);
        }
    }

    public function workflowHistory(Student $student): Response
    {
        $workflows = $student->workflowInstances()
            ->with(['workflow', 'approvals.approver'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Students/WorkflowHistory', [
            'student'   => $student,
            'workflows' => $workflows,
        ]);
    }
}