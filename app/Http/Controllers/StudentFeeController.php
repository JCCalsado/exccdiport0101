<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Student;
use App\Models\StudentAssessment;
use App\Models\StudentPaymentTerm;
use App\Models\Subject;
use App\Models\Fee;
use App\Models\Transaction;
use App\Models\Payment;
use App\Services\StudentPaymentService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class StudentFeeController extends Controller
{
    /**
     * Display listing of students for fee management
     */
    public function index(Request $request)
    {
        $query = User::with(['student', 'account', 'latestAssessment.paymentTerms'])
            ->where('role', 'student');

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('student_id', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_initial, '')) like ?", ["%{$search}%"]);
            });
        }

        // Filter by course
        if ($request->filled('course')) {
            $query->where('course', $request->course);
        }

        // Filter by year level
        if ($request->filled('year_level')) {
            $query->where('year_level', $request->year_level);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $students = $query->paginate(15)->withQueryString();

        // Get filter options
        $courses = User::where('role', 'student')
            ->whereNotNull('course')
            ->distinct()
            ->pluck('course');

        $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        $statuses = [
            User::STATUS_ACTIVE => 'Active',
            User::STATUS_GRADUATED => 'Graduated',
            User::STATUS_DROPPED => 'Dropped',
        ];

        return Inertia::render('StudentFees/Index', [
            'students' => $students,
            'filters' => $request->only(['search', 'course', 'year_level', 'status']),
            'courses' => $courses,
            'yearLevels' => $yearLevels,
            'statuses' => $statuses,
        ]);
    }

    /**
     * Show create assessment form
     */
    public function create(Request $request)
    {
        // If this is an AJAX request for getting student data
        if ($request->has('get_data') && $request->has('student_id')) {
            $student = User::where('role', 'student')->findOrFail($request->student_id);
            
            // Get subjects for this student
            $subjects = Subject::active()
                ->where('course', $student->course)
                ->where('year_level', $student->year_level)
                ->get()
                ->map(function ($subject) {
                    return [
                        'id' => $subject->id,
                        'code' => $subject->code,
                        'name' => $subject->name,
                        'units' => $subject->units,
                        'price_per_unit' => $subject->price_per_unit,
                        'has_lab' => $subject->has_lab,
                        'lab_fee' => $subject->lab_fee,
                        'total_cost' => $subject->total_cost,
                    ];
                });

            // Get fees
            $fees = Fee::active()
                ->whereIn('category', ['Laboratory', 'Library', 'Athletic', 'Miscellaneous'])
                ->get()
                ->map(function ($fee) {
                    return [
                        'id' => $fee->id,
                        'name' => $fee->name,
                        'category' => $fee->category,
                        'amount' => $fee->amount,
                    ];
                });

            return response()->json([
                'subjects' => $subjects,
                'fees' => $fees,
            ]);
        }

        // Get all active students for selection
        $students = User::where('role', 'student')
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'student_id' => $user->student_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'course' => $user->course,
                    'year_level' => $user->year_level,
                    'status' => $user->status,
                ];
            });

        $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        $semesters = ['1st Sem', '2nd Sem', 'Summer'];
        $currentYear = now()->year;
        $schoolYears = [
            "{$currentYear}-" . ($currentYear + 1),
            ($currentYear - 1) . "-{$currentYear}",
        ];

        return Inertia::render('StudentFees/Create', [
            'students' => $students,
            'yearLevels' => $yearLevels,
            'semesters' => $semesters,
            'schoolYears' => $schoolYears,
        ]);
    }

    /**
     * Store new assessment
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'year_level' => 'required|string',
            'semester' => 'required|string',
            'school_year' => 'required|string',
            'subjects' => 'required|array|min:1',
            'subjects.*.id' => 'required|exists:subjects,id',
            'subjects.*.units' => 'required|numeric|min:0',
            'subjects.*.amount' => 'required|numeric|min:0',
            'other_fees' => 'nullable|array',
            'other_fees.*.id' => 'required|exists:fees,id',
            'other_fees.*.amount' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Calculate tuition fee
            $tuitionFee = collect($validated['subjects'])->sum('amount');
            
            // Calculate other fees
            $otherFeesTotal = isset($validated['other_fees']) 
                ? collect($validated['other_fees'])->sum('amount') 
                : 0;

            // Create assessment
            $assessment = StudentAssessment::create([
                'user_id' => $validated['user_id'],
                'assessment_number' => StudentAssessment::generateAssessmentNumber(),
                'year_level' => $validated['year_level'],
                'semester' => $validated['semester'],
                'school_year' => $validated['school_year'],
                'tuition_fee' => $tuitionFee,
                'other_fees' => $otherFeesTotal,
                'total_assessment' => $tuitionFee + $otherFeesTotal,
                'subjects' => $validated['subjects'],
                'fee_breakdown' => $validated['other_fees'] ?? [],
                'created_by' => auth()->id(),
                'status' => 'active',
            ]);

            // Create transactions for each subject
            foreach ($validated['subjects'] as $subject) {
                Transaction::create([
                    'user_id' => $validated['user_id'],
                    'reference' => 'SUBJ-' . strtoupper(Str::random(8)),
                    'kind' => 'charge',
                    'type' => 'Tuition',
                    'year' => explode('-', $validated['school_year'])[0],
                    'semester' => $validated['semester'],
                    'amount' => $subject['amount'],
                    'status' => 'pending',
                    'meta' => [
                        'assessment_id' => $assessment->id,
                        'subject_id' => $subject['id'],
                        'description' => 'Tuition Fee - Subject',
                    ],
                ]);
            }

            // Create transactions for other fees
            if (isset($validated['other_fees'])) {
                foreach ($validated['other_fees'] as $fee) {
                    $feeModel = Fee::find($fee['id']);
                    Transaction::create([
                        'user_id' => $validated['user_id'],
                        'fee_id' => $fee['id'],
                        'reference' => 'FEE-' . strtoupper(Str::random(8)),
                        'kind' => 'charge',
                        'type' => $feeModel->category,
                        'year' => explode('-', $validated['school_year'])[0],
                        'semester' => $validated['semester'],
                        'amount' => $fee['amount'],
                        'status' => 'pending',
                        'meta' => [
                            'assessment_id' => $assessment->id,
                            'fee_code' => $feeModel->code,
                            'fee_name' => $feeModel->name,
                        ],
                    ]);
                }
            }

            // Recalculate student balance
            $user = User::find($validated['user_id']);
            \App\Services\AccountService::recalculate($user);

            DB::commit();

            return redirect()
                ->route('student-fees.show', $validated['user_id'])
                ->with('success', 'Student fee assessment created successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to create assessment: ' . $e->getMessage()]);
        }
    }

    /**
     * Show student fee details
     */
    public function show($userId)
    {
        $student = User::with(['student', 'account'])
            ->where('role', 'student')
            ->findOrFail($userId);

        // Get latest assessment with payment terms
        $latestAssessment = StudentAssessment::where('user_id', $userId)
            ->where('status', 'active')
            ->with('paymentTerms')
            ->latest()
            ->first();

        // Get all transactions
        $transactions = Transaction::where('user_id', $userId)
            ->with('fee')
            ->orderBy('created_at', 'desc')
            ->get();

        // Get payments
        $payments = Payment::where('student_id', $student->student->id ?? null)
            ->orderBy('paid_at', 'desc')
            ->get();

        // ── FIX: Build feeBreakdown from the assessment record first. ─────────────
        // Previously this was derived only from transactions, which meant students
        // without charge transactions (like jcdc742713) showed an empty fee breakdown.
        // Now we derive from the assessment's stored amounts, falling back to
        // transactions if no assessment exists.
        if ($latestAssessment) {
            $feeBreakdown = collect();

            if ($latestAssessment->tuition_fee > 0) {
                $feeBreakdown->push([
                    'category' => 'Tuition',
                    'total'    => (float) $latestAssessment->tuition_fee,
                    'items'    => 1,
                ]);
            }

            if ($latestAssessment->other_fees > 0) {
                // Try to further break down other_fees from stored fee_breakdown JSON
                $storedBreakdown = $latestAssessment->fee_breakdown ?? [];
                if (!empty($storedBreakdown)) {
                    $grouped = collect($storedBreakdown)->groupBy('category');
                    foreach ($grouped as $category => $items) {
                        $feeBreakdown->push([
                            'category' => $category,
                            'total'    => $items->sum('amount'),
                            'items'    => $items->count(),
                        ]);
                    }
                } else {
                    // No detailed breakdown stored — show as Miscellaneous
                    $feeBreakdown->push([
                        'category' => 'Miscellaneous',
                        'total'    => (float) $latestAssessment->other_fees,
                        'items'    => 1,
                    ]);
                }
            }
        } else {
            // Fallback: derive from transactions (original behavior)
            $feeBreakdown = $transactions->where('kind', 'charge')
                ->groupBy('type')
                ->map(function ($group) {
                    return [
                        'category' => $group->first()->type,
                        'total'    => $group->sum('amount'),
                        'items'    => $group->count(),
                    ];
                });
        }

        return Inertia::render('StudentFees/Show', [
            'student'          => $student,
            'student_model_id' => $student->student->id ?? null,
            'assessment'       => $latestAssessment,
            'transactions'     => $transactions,
            'payments'         => $payments,
            'feeBreakdown'     => $feeBreakdown->values(),
        ]);
    }

    /**
     * Store payment for student
     * Validates:
     * - Amount doesn't exceed total outstanding balance
     * - Selected term exists and is unpaid
     * - Only first unpaid term is selected
     * - All operations are atomic (DB transactions)
     */
    public function storePayment(Request $request, $userId)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:cash,gcash,bank_transfer,credit_card,debit_card',
            'term_id' => 'required|exists:student_payment_terms,id',
            'payment_date' => 'required|date',
        ]);

        $student = User::with('student', 'account')->where('role', 'student')->findOrFail($userId);

        // Ensure student has a student record
        if (!$student->student) {
            return back()->withErrors(['error' => 'Student record not found. Please contact administrator.']);
        }

        // Get the payment term
        $paymentTerm = StudentPaymentTerm::findOrFail($validated['term_id']);

        // Get the payment service
        $paymentService = new StudentPaymentService();
        $outstandingBalance = $paymentService->getTotalOutstandingBalance($student);

        // Validation: Amount cannot exceed total outstanding balance (overpayment protection)
        if ((float) $validated['amount'] > $outstandingBalance) {
            return back()->withErrors([
                'amount' => sprintf(
                    'Payment amount cannot exceed outstanding balance of ₱%s',
                    number_format($outstandingBalance, 2)
                )
            ]);
        }

        // Validation: Selected term must have outstanding balance
        if ((float) $paymentTerm->balance <= 0) {
            return back()->withErrors([
                'term_id' => 'This payment term has already been paid. Please select another term.'
            ]);
        }

        // Validation: Only first unpaid term can be selected (sequential enforcement)
        $firstUnpaidTerm = StudentAssessment::where('user_id', $userId)
            ->latest('created_at')
            ->first()
            ->paymentTerms()
            ->where('balance', '>', 0)
            ->orderBy('term_order')
            ->first();

        if ($firstUnpaidTerm && (int) $paymentTerm->id !== (int) $firstUnpaidTerm->id) {
            return back()->withErrors([
                'term_id' => sprintf(
                    'You must pay %s before paying other terms. Please select that term.',
                    $firstUnpaidTerm->term_name
                )
            ]);
        }

        try {
            // Process payment using service (atomic transaction)
            $result = $paymentService->processPayment($student, (float) $validated['amount'], [
                'payment_method' => $validated['payment_method'],
                'paid_at' => $validated['payment_date'],
                'description' => 'Payment recorded by accounting',
                'selected_term_id' => (int) $validated['term_id'],
                'term_name' => $paymentTerm->term_name,
            ]);

            return back()->with('success', 'Payment recorded successfully! ' . $result['message']);

        } catch (\Exception $e) {
            Log::error('Payment recording failed', [
                'user_id' => $userId,
                'term_id' => $validated['term_id'],
                'amount' => $validated['amount'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return back()->withErrors([
                'error' => 'Failed to record payment. Please try again or contact support.'
            ]);
        }
    }

    /**
     * Edit assessment
     */
    public function edit($userId)
    {
        $student = User::with(['student', 'account'])
            ->where('role', 'student')
            ->findOrFail($userId);

        $assessment = StudentAssessment::where('user_id', $userId)
            ->where('status', 'active')
            ->latest()
            ->first();

        // If no active assessment exists, redirect to create one
        if (!$assessment) {
            return redirect()
                ->route('student-fees.create')
                ->with('info', 'Please create an assessment for this student first.');
        }

        $subjects = Subject::active()
            ->where('course', $student->course)
            ->where('year_level', $student->year_level)
            ->get();

        $fees = Fee::active()
            ->whereIn('category', ['Laboratory', 'Library', 'Athletic', 'Miscellaneous'])
            ->get();

        return Inertia::render('StudentFees/Edit', [
            'student' => $student,
            'assessment' => $assessment,
            'subjects' => $subjects,
            'fees' => $fees,
        ]);
    }

    /**
     * Update student assessment
     */
    public function update(Request $request, $userId)
    {
        $validated = $request->validate([
            'year_level' => 'required|string',
            'semester' => 'required|string',
            'school_year' => 'required|string',
            'subjects' => 'required|array',
            'other_fees' => 'required|array',
        ]);

        $assessment = StudentAssessment::where('user_id', $userId)
            ->where('status', 'active')
            ->latest()
            ->firstOrFail();

        $tuitionTotal = collect($validated['subjects'])->sum('amount') ?? 0;
        $otherFeesTotal = collect($validated['other_fees'])->sum('amount') ?? 0;

        $assessment->update([
            'year_level' => $validated['year_level'],
            'semester' => $validated['semester'],
            'school_year' => $validated['school_year'],
            'subjects' => $validated['subjects'],
            'fee_breakdown' => $validated['other_fees'],
            'tuition_fee' => $tuitionTotal,
            'other_fees' => $otherFeesTotal,
            'total_assessment' => $tuitionTotal + $otherFeesTotal,
        ]);

        return redirect()
            ->route('student-fees.show', $userId)
            ->with('success', 'Assessment updated successfully!');
    }

    /**
     * Export assessment to PDF
     */
    public function exportPdf($userId)
    {
        $student = User::with(['student', 'account'])
            ->where('role', 'student')
            ->findOrFail($userId);

        $assessment = StudentAssessment::where('user_id', $userId)
            ->where('status', 'active')
            ->latest()
            ->firstOrFail();

        $transactions = Transaction::where('user_id', $userId)
            ->with('fee')
            ->orderBy('created_at', 'desc')
            ->get();

        $payments = Payment::where('student_id', $student->student->id ?? null)
            ->orderBy('paid_at', 'desc')
            ->get();

        $pdf = Pdf::loadView('pdf.student-assessment', [
            'student' => $student,
            'assessment' => $assessment,
            'transactions' => $transactions,
            'payments' => $payments,
        ]);

        return $pdf->download("assessment-{$student->student_id}.pdf");
    }

    /**
     * Show create student form
     */
    public function createStudent()
    {
        // ✅ Get unique courses from existing students
        $courses = User::where('role', 'student')
            ->whereNotNull('course')
            ->distinct()
            ->pluck('course')
            ->sort()
            ->values();
        
        // ✅ If no students exist yet, provide default courses
        if ($courses->isEmpty()) {
            $courses = collect([
                'BS Electrical Engineering Technology',
                'BS Electronics Engineering Technology',
                'BS Computer Science',
                'BS Information Technology',
                'BS Accountancy',
                'BS Business Administration',
            ]);
        }
        
        $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        
        return Inertia::render('StudentFees/CreateStudent', [
            'courses' => $courses,
            'yearLevels' => $yearLevels,
        ]);
    }

    /**
     * Store new student
     */
    public function storeStudent(Request $request)
    {
        $validated = $request->validate([
            'last_name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_initial' => 'nullable|string|max:10',
            'email' => 'required|email|unique:users,email',
            'birthday' => 'required|date',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:255',
            'year_level' => 'required|string',
            'course' => 'required|string',
            'student_id' => 'nullable|string|unique:users,student_id',
        ]);

        DB::beginTransaction();
        try {
            // Auto-generate ID if empty
            $studentId = $validated['student_id'] ?: $this->generateUniqueStudentId();

            // Create user record
            $user = User::create([
                'last_name' => $validated['last_name'],
                'first_name' => $validated['first_name'],
                'middle_initial' => $validated['middle_initial'],
                'email' => $validated['email'],
                'birthday' => $validated['birthday'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'year_level' => $validated['year_level'],
                'course' => $validated['course'],
                'student_id' => $studentId,
                'role' => 'student',
                'status' => User::STATUS_ACTIVE,
                'password' => Hash::make('password'),
            ]);

            // ✅ FIX: Create Student model entry
            Student::create([
                'user_id' => $user->id,
            ]);

            DB::commit();

            return redirect()
                ->route('student-fees.show', $user->id)
                ->with('success', 'Student created successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors([
                'error' => 'Failed to create student: ' . $e->getMessage(),
            ]);
        }
    }

    private function generateUniqueStudentId(): string
    {
        $year = now()->year;
        
        // Use database transaction to prevent race conditions
        return DB::transaction(function () use ($year) {
            // Lock the table to prevent concurrent ID generation
            $lastStudent = User::where('student_id', 'like', "{$year}-%")
                ->lockForUpdate()
                ->orderByRaw('CAST(SUBSTRING(student_id, 6) AS UNSIGNED) DESC')
                ->first();

            if ($lastStudent) {
                // Extract the number part and increment
                $lastNumber = intval(substr($lastStudent->student_id, -4));
                $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            } else {
                $newNumber = '0001';
            }

            $newStudentId = "{$year}-{$newNumber}";
            
            // Double-check uniqueness
            $attempts = 0;
            while (User::where('student_id', $newStudentId)->exists() && $attempts < 10) {
                $lastNumber = intval($newNumber);
                $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
                $newStudentId = "{$year}-{$newNumber}";
                $attempts++;
            }
            
            if ($attempts >= 10) {
                throw new \Exception('Unable to generate unique student ID after multiple attempts.');
            }
            
            return $newStudentId;
        });
    }
}