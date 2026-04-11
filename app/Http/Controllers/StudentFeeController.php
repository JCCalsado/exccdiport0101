<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentAssessment;
use App\Models\StudentPaymentTerm;
use App\Models\Transaction;
use App\Models\User;
use App\Enums\UserRoleEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StudentFeeController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    //  HELPERS
    // ─────────────────────────────────────────────────────────────

    /**
     * Compute the total assessment from raw unit inputs.
     *
     * Formula (AY 2025-2026):
     *   Tuition  = lec_units × ₱364
     *   Lab Fee  = lab_subjects × ₱1,656  (per-subject, not per-unit)
     *   Misc Fee = ₱5,300 (fixed)
     *   Total    = tuition + lab_fee + misc_fee
     */
    private function computeTotal(int $lecUnits, int $labSubjects): array
    {
        $tuitionPerUnit  = (float) config('fees.tuition_per_lec_unit', 364.00);
        $labFeePerSubject = (float) config('fees.lab_fee_per_subject', 1656.00);
        $miscFeeFixed    = (float) config('fees.misc_fee_fixed', 5300.00);

        $tuitionFee = $lecUnits * $tuitionPerUnit;
        $labFee     = $labSubjects * $labFeePerSubject;
        $miscFee    = $miscFeeFixed;
        $total      = $tuitionFee + $labFee + $miscFee;

        return compact('tuitionFee', 'labFee', 'miscFee', 'total');
    }

    /**
     * Build payment terms from config percentages and a total amount.
     * Returns an array ready to insert into student_payment_terms.
     */
    private function buildPaymentTerms(float $total): array
    {
        $termConfigs = config('fees.payment_terms', []);
        $terms       = [];

        foreach ($termConfigs as $config) {
            $amount = round($total * ($config['percentage'] / 100), 2);

            $terms[] = [
                'term_name'   => $config['term_name'],
                'term_order'  => $config['term_order'],
                'percentage'  => $config['percentage'],
                'amount'      => $amount,
                'balance'     => $amount,   // balance = unpaid portion (source of truth)
                'status'      => 'unpaid',
                'due_date'    => null,
                'paid_date'   => null,
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        }

        return $terms;
    }

    // ─────────────────────────────────────────────────────────────
    //  INDEX — list all assessed students
    // ─────────────────────────────────────────────────────────────

    public function index(): Response
    {
        $this->authorize('viewAny', StudentAssessment::class);

        $assessments = StudentAssessment::with(['user'])
            ->where('status', 'active')
            ->latest()
            ->paginate(20)
            ->through(fn ($a) => [
                'id'           => $a->id,
                'user_id'      => $a->user_id,
                'student_name' => $a->user->last_name . ', ' . $a->user->first_name,
                'account_id'   => $a->user->account_id,
                'course'       => $a->user->course,
                'year_level'   => $a->user->year_level,
                'semester'     => $a->semester,
                'school_year'  => $a->school_year,
                'lec_units'    => $a->lec_units,
                'lab_units'    => $a->lab_units,
                'lab_subjects' => $a->lab_subjects,
                'total_amount' => $a->paymentTerms->sum('amount'),
                'total_balance'=> $a->paymentTerms->sum('balance'),
            ]);

        return Inertia::render('StudentFees/Index', [
            'assessments' => $assessments,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  CREATE — show the "create assessment" form
    // ─────────────────────────────────────────────────────────────

    public function create(Request $request): Response
    {
        $this->authorize('create', StudentAssessment::class);

        // Pre-load the selected student if ?student_id= is passed
        $preselectedStudent = null;
        if ($request->filled('student_id')) {
            $student = User::where('role', UserRoleEnum::STUDENT)
                ->where('id', $request->student_id)
                ->first();

            if ($student) {
                $preselectedStudent = [
                    'id'         => $student->id,
                    'name'       => $student->last_name . ', ' . $student->first_name,
                    'account_id' => $student->account_id,
                    'course'     => $student->course,
                    'year_level' => $student->year_level,
                ];
            }
        }

        // Pass fee rates to the frontend so it can compute live preview
        $feeRates = [
            'tuition_per_lec_unit'  => config('fees.tuition_per_lec_unit', 364.00),
            'lab_fee_per_subject'   => config('fees.lab_fee_per_subject', 1656.00),
            'misc_fee_fixed'        => config('fees.misc_fee_fixed', 5300.00),
            'payment_terms'         => config('fees.payment_terms', []),
        ];

        return Inertia::render('StudentFees/Create', [
            'preselectedStudent' => $preselectedStudent,
            'feeRates'           => $feeRates,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  STORE — save the new assessment (no subjects, just units)
    // ─────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $this->authorize('create', StudentAssessment::class);

        $validated = $request->validate([
            'user_id'      => ['required', 'exists:users,id'],
            'semester'     => ['required', 'in:1st,2nd,Summer'],
            'school_year'  => ['required', 'string', 'max:20'],  // e.g. "2025-2026"
            'lec_units'    => ['required', 'integer', 'min:0', 'max:30'],
            'lab_units'    => ['required', 'integer', 'min:0', 'max:10'],
            'lab_subjects' => ['required', 'integer', 'min:0', 'max:10'],
        ]);

        // Ensure this student doesn't already have an active assessment for the same semester
        $existing = StudentAssessment::where('user_id', $validated['user_id'])
            ->where('semester', $validated['semester'])
            ->where('school_year', $validated['school_year'])
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return back()->withErrors([
                'user_id' => 'This student already has an active assessment for ' .
                             $validated['semester'] . ' Sem, SY ' . $validated['school_year'] . '.',
            ]);
        }

        DB::transaction(function () use ($validated) {
            // 1. Archive any previous active assessment for this student
            StudentAssessment::where('user_id', $validated['user_id'])
                ->where('status', 'active')
                ->update(['status' => 'archived']);

            // 2. Compute fees
            $fees = $this->computeTotal(
                (int) $validated['lec_units'],
                (int) $validated['lab_subjects']
            );

            // 3. Create the assessment record
            $assessment = StudentAssessment::create([
                'user_id'      => $validated['user_id'],
                'semester'     => $validated['semester'],
                'school_year'  => $validated['school_year'],
                'lec_units'    => $validated['lec_units'],
                'lab_units'    => $validated['lab_units'],
                'lab_subjects' => $validated['lab_subjects'],
                'status'       => 'active',
            ]);

            // 4. Build and insert payment terms
            $terms = $this->buildPaymentTerms($fees['total']);

            foreach ($terms as $term) {
                $assessment->paymentTerms()->create($term);
            }

            // 5. Record a charge transaction for the ledger (ASMT- prefix = assessment debit)
            Transaction::create([
                'user_id'         => $validated['user_id'],
                'kind'            => 'charge',
                'status'          => 'paid',  // "paid" here means "posted to ledger"
                'amount'          => $fees['total'],
                'reference'       => 'ASMT-' . strtoupper(Str::random(8)),
                'payment_channel' => 'assessment',
                'year'            => now()->year,
                'semester'        => $validated['semester'],
                'meta'            => json_encode([
                    'lec_units'    => $validated['lec_units'],
                    'lab_units'    => $validated['lab_units'],
                    'lab_subjects' => $validated['lab_subjects'],
                    'tuition_fee'  => $fees['tuitionFee'],
                    'lab_fee'      => $fees['labFee'],
                    'misc_fee'     => $fees['miscFee'],
                    'school_year'  => $validated['school_year'],
                ]),
            ]);
        });

        return redirect()
            ->route('student-fees.show', $validated['user_id'])
            ->with('success', 'Assessment created successfully.');
    }

    // ─────────────────────────────────────────────────────────────
    //  SHOW — view a student's current assessment & payment terms
    // ─────────────────────────────────────────────────────────────

    public function show(int $userId): Response
    {
        $this->authorize('view', StudentAssessment::class);

        $user = User::with([
            'latestAssessment.paymentTerms',
        ])->findOrFail($userId);

        $assessment = $user->latestAssessment;

        $feeBreakdown = null;
        if ($assessment) {
            $feeBreakdown = $this->computeTotal(
                $assessment->lec_units,
                $assessment->lab_subjects
            );
        }

        return Inertia::render('StudentFees/Show', [
            'student'      => [
                'id'         => $user->id,
                'name'       => $user->last_name . ', ' . $user->first_name,
                'account_id' => $user->account_id,
                'course'     => $user->course,
                'year_level' => $user->year_level,
                'avatar'     => $user->avatar,
            ],
            'assessment'   => $assessment ? [
                'id'           => $assessment->id,
                'semester'     => $assessment->semester,
                'school_year'  => $assessment->school_year,
                'lec_units'    => $assessment->lec_units,
                'lab_units'    => $assessment->lab_units,
                'lab_subjects' => $assessment->lab_subjects,
                'status'       => $assessment->status,
            ] : null,
            'feeBreakdown' => $feeBreakdown,
            'paymentTerms' => $assessment
                ? $assessment->paymentTerms->sortBy('term_order')->values()
                : [],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  EDIT — reopen assessment for correction
    // ─────────────────────────────────────────────────────────────

    public function edit(int $userId): Response
    {
        $this->authorize('update', StudentAssessment::class);

        $user       = User::findOrFail($userId);
        $assessment = StudentAssessment::where('user_id', $userId)
            ->where('status', 'active')
            ->with('paymentTerms')
            ->firstOrFail();

        $feeRates = [
            'tuition_per_lec_unit' => config('fees.tuition_per_lec_unit', 364.00),
            'lab_fee_per_subject'  => config('fees.lab_fee_per_subject', 1656.00),
            'misc_fee_fixed'       => config('fees.misc_fee_fixed', 5300.00),
            'payment_terms'        => config('fees.payment_terms', []),
        ];

        return Inertia::render('StudentFees/Edit', [
            'student' => [
                'id'         => $user->id,
                'name'       => $user->last_name . ', ' . $user->first_name,
                'account_id' => $user->account_id,
                'course'     => $user->course,
                'year_level' => $user->year_level,
            ],
            'assessment' => [
                'id'           => $assessment->id,
                'semester'     => $assessment->semester,
                'school_year'  => $assessment->school_year,
                'lec_units'    => $assessment->lec_units,
                'lab_units'    => $assessment->lab_units,
                'lab_subjects' => $assessment->lab_subjects,
            ],
            'feeRates' => $feeRates,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  UPDATE — recalculate assessment with new unit values
    // ─────────────────────────────────────────────────────────────

    public function update(Request $request, int $userId)
    {
        $this->authorize('update', StudentAssessment::class);

        $validated = $request->validate([
            'semester'     => ['required', 'in:1st,2nd,Summer'],
            'school_year'  => ['required', 'string', 'max:20'],
            'lec_units'    => ['required', 'integer', 'min:0', 'max:30'],
            'lab_units'    => ['required', 'integer', 'min:0', 'max:10'],
            'lab_subjects' => ['required', 'integer', 'min:0', 'max:10'],
        ]);

        $assessment = StudentAssessment::where('user_id', $userId)
            ->where('status', 'active')
            ->firstOrFail();

        // Only allow update if no payments have been made yet
        $paidTerms = $assessment->paymentTerms()
            ->where('status', '!=', 'unpaid')
            ->count();

        if ($paidTerms > 0) {
            return back()->withErrors([
                'lec_units' => 'Cannot edit this assessment — payments have already been recorded. Please contact the admin.',
            ]);
        }

        DB::transaction(function () use ($assessment, $validated, $userId) {
            // Recompute fees
            $fees = $this->computeTotal(
                (int) $validated['lec_units'],
                (int) $validated['lab_subjects']
            );

            // Update the assessment
            $assessment->update([
                'semester'     => $validated['semester'],
                'school_year'  => $validated['school_year'],
                'lec_units'    => $validated['lec_units'],
                'lab_units'    => $validated['lab_units'],
                'lab_subjects' => $validated['lab_subjects'],
            ]);

            // Delete old terms and regenerate
            $assessment->paymentTerms()->delete();
            $terms = $this->buildPaymentTerms($fees['total']);
            foreach ($terms as $term) {
                $assessment->paymentTerms()->create($term);
            }

            // Update the charge transaction
            Transaction::where('user_id', $userId)
                ->where('kind', 'charge')
                ->where('semester', $validated['semester'])
                ->where('payment_channel', 'assessment')
                ->latest()
                ->first()
                ?->update([
                    'amount' => $fees['total'],
                    'meta'   => json_encode([
                        'lec_units'    => $validated['lec_units'],
                        'lab_units'    => $validated['lab_units'],
                        'lab_subjects' => $validated['lab_subjects'],
                        'tuition_fee'  => $fees['tuitionFee'],
                        'lab_fee'      => $fees['labFee'],
                        'misc_fee'     => $fees['miscFee'],
                        'school_year'  => $validated['school_year'],
                        'updated_at'   => now()->toISOString(),
                    ]),
                ]);
        });

        return redirect()
            ->route('student-fees.show', $userId)
            ->with('success', 'Assessment updated successfully.');
    }

    // ─────────────────────────────────────────────────────────────
    //  SEARCH — live search for students (used by Create.vue)
    // ─────────────────────────────────────────────────────────────

    public function search(Request $request): \Illuminate\Http\JsonResponse
    {
        $q = $request->get('q', '');

        $students = User::where('role', UserRoleEnum::STUDENT)
            ->where(function ($query) use ($q) {
                $query->where('last_name', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('account_id', 'like', "%{$q}%");
            })
            ->where('is_active', true)
            ->select('id', 'last_name', 'first_name', 'account_id', 'course', 'year_level')
            ->limit(10)
            ->get()
            ->map(fn ($u) => [
                'id'         => $u->id,
                'name'       => $u->last_name . ', ' . $u->first_name,
                'account_id' => $u->account_id,
                'course'     => $u->course,
                'year_level' => $u->year_level,
            ]);

        return response()->json(['students' => $students]);
    }
}