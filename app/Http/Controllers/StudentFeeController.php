<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\UserRoleEnum;
use App\Models\Account;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentAssessment;
use App\Models\StudentEnrollment;
use App\Models\StudentPaymentTerm;
use App\Models\StudentStatusLog;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountService;
use App\Services\StudentPaymentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class StudentFeeController extends Controller
{
    // =========================================================================
    // FEE CONFIGURATION
    // =========================================================================
    //
    // Billing model (AY 2025-2026, Rate of Conduct of Consultation April 2025):
    //   Total = (Σ selected units × ₱364) + (Σ lab subjects × ₱1,656) + ₱6,956 misc
    //
    // config('fees.tuition_per_unit')    → 364.00
    // config('fees.lab_fee_per_subject') → 1656.00
    // config('fees.miscellaneous')       → itemized fixed block, total 6956.00
    // config('fees.terms')               → 5 payment term definitions
    //
    // After editing config/fees.php run: php artisan config:clear
    // =========================================================================

    // =========================================================================
    // SHARED HELPER — Course list
    // =========================================================================

    private function allCourses(): \Illuminate\Support\Collection
    {
        return collect(array_unique(array_merge(
            Subject::distinct()->pluck('course')->toArray(),
            User::students()
                ->whereNotNull('course')
                ->distinct()
                ->pluck('course')
                ->toArray(),
        )))->sort()->values();
    }

    // =========================================================================
    // SHARED HELPER — Build subjectMap for the Create Assessment form
    // Returns: subjectMap[course][year_level][semester] = SubjectItem[]
    // =========================================================================

    private function buildSubjectMap(): array
    {
        $rate   = (float) config('fees.tuition_per_unit',    364.00);
        $labFee = (float) config('fees.lab_fee_per_subject', 1656.00);

        return Subject::where('is_active', true)
            ->orderBy('course')
            ->orderByRaw("FIELD(year_level,'1st Year','2nd Year','3rd Year','4th Year')")
            ->orderByRaw("FIELD(semester,'1st Sem','2nd Sem','Summer')")
            ->orderBy('code')
            ->get()
            ->groupBy('course')
            ->map(fn ($byCourse) => $byCourse
                ->groupBy('year_level')
                ->map(fn ($byYear) => $byYear
                    ->groupBy('semester')
                    ->map(fn ($bySem) => $bySem->map(fn ($s) => [
                        'id'           => $s->id,
                        'code'         => $s->code,
                        'name'         => $s->name,
                        'course'       => $s->course,
                        'lec_units'    => $s->lec_units ?? 0,
                        'lab_units'    => $s->lab_units ?? 0,
                        'total_units'  => ($s->lec_units ?? 0) + ($s->lab_units ?? 0),
                        'has_lab'      => (bool) $s->has_lab,
                        'tuition_cost' => round(($s->lec_units ?? 0) * $rate, 2),
                        'lab_cost'     => $s->lab_units > 0 ? $labFee : 0,
                        'year_level'   => $s->year_level,
                        'semester'     => $s->semester,
                    ])->values()->toArray())
                    ->toArray())
                ->toArray())
            ->toArray();
    }

    // =========================================================================
    // INDEX
    // =========================================================================

    public function index(Request $request)
    {
        $query = User::with(['student', 'account', 'latestAssessment.paymentTerms'])
            ->students();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('account_id', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_initial, '')) like ?", ["%{$search}%"]);
            });
        }

        if ($request->filled('course')) {
            $query->where('course', $request->course);
        }

        if ($request->filled('year_level')) {
            $query->where('year_level', $request->year_level);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $students = $query->paginate(15)->withQueryString();

        $courses = $this->allCourses();

        $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        $statuses   = [
            User::STATUS_ACTIVE    => 'Active',
            User::STATUS_GRADUATED => 'Graduated',
            User::STATUS_DROPPED   => 'Dropped',
        ];

        return Inertia::render('StudentFees/Index', [
            'students'   => $students,
            'filters'    => $request->only(['search', 'course', 'year_level', 'status']),
            'courses'    => $courses,
            'yearLevels' => $yearLevels,
            'statuses'   => $statuses,
        ]);
    }

    // =========================================================================
    // CREATE — Show the create-assessment form
    // =========================================================================

    public function create(Request $request)
    {
        $currentYear = now()->year;

        $semesterProgression = [
            '1st Year|1st Sem' => ['year_level' => '1st Year', 'semester' => '2nd Sem'],
            '1st Year|2nd Sem' => ['year_level' => '2nd Year', 'semester' => '1st Sem'],
            '2nd Year|1st Sem' => ['year_level' => '2nd Year', 'semester' => '2nd Sem'],
            '2nd Year|2nd Sem' => ['year_level' => '3rd Year', 'semester' => '1st Sem'],
            '3rd Year|1st Sem' => ['year_level' => '3rd Year', 'semester' => '2nd Sem'],
            '3rd Year|2nd Sem' => ['year_level' => '4th Year', 'semester' => '1st Sem'],
            '4th Year|1st Sem' => ['year_level' => '4th Year', 'semester' => '2nd Sem'],
        ];

        $students = User::students()
            ->where('status', User::STATUS_ACTIVE)
            ->with(['latestAssessment'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        // CONCERN FIX #4: Bulk-load active assessments to prevent N+1 query
        // Get all active assessments with unpaid balances for all students at once
        $userIds = $students->pluck('id')->toArray();
        $activeAssessments = StudentAssessment::where('status', 'active')
            ->whereIn('user_id', $userIds)
            ->whereHas('paymentTerms', fn ($q) => $q->where('balance', '>', 0))
            ->with(['paymentTerms' => fn ($q) => $q->where('balance', '>', 0)])
            ->get()
            ->groupBy('user_id')
            ->map(function ($assessments) {
                // Get the latest active assessment for this user
                return $assessments->sortByDesc('created_at')->first();
            });

        // Map student data with pre-loaded assessment info
        $students = $students->map(function ($user) use ($semesterProgression, $activeAssessments) {
            $latest = $user->latestAssessment;

            $suggestedYearLevel = $user->year_level;
            $suggestedSemester  = null;

            if ($latest) {
                $completedKey = "{$latest->year_level}|{$latest->semester}";
                if (isset($semesterProgression[$completedKey])) {
                    $next               = $semesterProgression[$completedKey];
                    $suggestedYearLevel = $next['year_level'];
                    $suggestedSemester  = $next['semester'];
                } else {
                    $suggestedYearLevel = $latest->year_level;
                    $suggestedSemester  = null;
                }
            }

            // Use pre-loaded active assessment instead of querying per student
            $activeAssessment = $activeAssessments[$user->id] ?? null;
            $activeAssessmentInfo = null;
            if ($activeAssessment) {
                $totalBalance = $activeAssessment->paymentTerms->sum('balance');
                $activeAssessmentInfo = [
                    'id'                 => $activeAssessment->id,
                    'assessment_number'  => $activeAssessment->assessment_number,
                    'year_level'         => $activeAssessment->year_level,
                    'semester'           => $activeAssessment->semester,
                    'school_year'        => $activeAssessment->school_year,
                    'total_assessment'   => (float) $activeAssessment->total_assessment,
                    'remaining_balance'  => round($totalBalance, 2),
                    'unpaid_term_count'  => $activeAssessment->paymentTerms->where('status', '!=', 'paid')->count(),
                ];
            }

            return [
                'id'                   => $user->id,
                'account_id'           => $user->account_id,
                'name'                 => $user->name,
                'email'                => $user->email,
                'course'               => $user->course,
                'year_level'           => $user->year_level,
                'status'               => $user->status,
                'is_irregular'         => (bool) $user->is_irregular,
                'suggested_year_level' => $suggestedYearLevel,
                'suggested_semester'   => $suggestedSemester,
                'latest_assessment'    => $latest ? [
                    'year_level'  => $latest->year_level,
                    'semester'    => $latest->semester,
                    'school_year' => $latest->school_year,
                ] : null,
                'activeAssessmentInfo' => $activeAssessmentInfo,
            ];
        });

        // Build miscellaneous fee lines for display
        $miscItems = config('fees.miscellaneous', []);
        $miscTotal = collect($miscItems)->sum('amount');

        // Define year levels, semesters, and school years for the form
        $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        $semesters = ['1st Sem', '2nd Sem', 'Summer'];
        
        // Generate school years: previous, current, and next
        $currentYear = now()->year;
        $schoolYears = [
            ($currentYear - 1) . '-' . $currentYear,
            $currentYear . '-' . ($currentYear + 1),
            ($currentYear + 1) . '-' . ($currentYear + 2),
        ];

        // ── Enrolled subjects per student ─────────────────────────────────────
        // Structure: enrollmentsMap[userId][schoolYear] = int[]
        //
        // IMPORTANT: keyed by school year ONLY — NOT by semester.
        //
        // The previous structure ([userId][schoolYear][semester]) was correct
        // for Regular assessments (one fixed term) but broke for Irregular
        // assessments: when a staff member browses subjects from a different
        // semester than the assessment's own term, the semester-scoped lookup
        // missed those enrollments and let already-enrolled subjects appear
        // selectable.
        //
        // By collapsing to school-year scope, isAlreadyEnrolled() in Vue
        // blocks any subject the student is actively enrolled in across ALL
        // semesters of the selected school year — correct for both Regular and
        // Irregular assessment types.
        //
        // Only 'enrolled' status is included. 'dropped' and 'completed'
        // records are excluded so those subjects remain available.
        $studentIds     = $students->pluck('id'); 
        
         $enrollmentsMap = StudentEnrollment::where('status', 'enrolled')
            ->whereIn('user_id', $studentIds)
            ->get(['user_id', 'subject_id', 'school_year'])
            ->groupBy('user_id')
            ->map(fn($rows) => $rows
                ->groupBy('school_year')
                ->map(fn($byYear) => $byYear->pluck('subject_id')
                    ->map(fn($id) => (int) $id)->toArray())
                ->toArray())
            ->toArray();

        return Inertia::render('StudentFees/Create', [
            'students'         => $students,
            'yearLevels'       => $yearLevels,
            'semesters'        => $semesters,
            'schoolYears'      => $schoolYears,
            'subjectMap'       => $this->buildSubjectMap(),
            'courses'          => $this->allCourses(),
            'miscItems'        => $miscItems,
            'miscTotal'        => $miscTotal,
            'tuitionPerUnit'   => (float) config('fees.tuition_per_unit', 364.00),
            'labFeePerSubject' => (float) config('fees.lab_fee_per_subject', 1656.00),
            'enrollmentsMap'   => $enrollmentsMap,
        ]);
    }   
       
    public function store(Request $request)
    {
        $base = $request->validate([
            'user_id'              => 'required|exists:users,id',
            'course'               => 'required|string|max:255',
            'year_level'           => 'required|string',
            'semester'             => 'required|in:1st Sem,2nd Sem,Summer',
            'school_year'          => ['required', 'string', 'max:20', 'regex:/^\d{4}-\d{4}$/'],
            'assessment_type'      => 'required|in:regular,irregular',
            'selected_subjects'    => 'required|array|min:1',
            'selected_subjects.*'  => 'required|integer|exists:subjects,id',
        ]);

        $isIrregular = $base['assessment_type'] === 'irregular';

        // ── Single Active Assessment Guard ───────────────────────────────────
        // Prevent duplicate active assessments: a student must not have more than
        // one active assessment at a time. An assessment is considered "active" if:
        //   1. status = 'active', AND
        //   2. it has at least one payment term with outstanding balance > 0
        //
        // This enforces the completion requirement: a new assessment can only be
        // created if the previous assessment is fully paid and its status is
        // marked as completed/closed.
        $existingActiveWithBalance = StudentAssessment::where('user_id', $base['user_id'])
            ->where('status', 'active')
            ->whereHas('paymentTerms', fn ($q) => $q->where('balance', '>', 0))
            ->exists();

        if ($existingActiveWithBalance) {
            return back()->withErrors([
                'assessment' => 'Student already has an active assessment with remaining balance. Please complete the current assessment before creating a new one.',
            ]);
        }

        DB::beginTransaction();
        try {
            $rate   = (float) config('fees.tuition_per_unit',    364.00);
            $labFee = (float) config('fees.lab_fee_per_subject', 1656.00);

            // Load selected subjects from DB — trust the database, not the client
            $subjects = Subject::whereIn('id', $base['selected_subjects'])
                ->where('is_active', true)
                ->get();

            if ($subjects->isEmpty()) {
                throw new \InvalidArgumentException('No valid active subjects found for the selected IDs.');
            }

            // ── Server-side guard: reject already-enrolled subjects ───────────
            // Enforces the same rule the Vue form enforces visually.
            // Protects against tampered requests and concurrent-tab race conditions.
            //
            // For REGULAR assessments a semester-scoped check is sufficient, but
            // for IRREGULAR assessments subjects are drawn from multiple semesters.
            // Using the assessment-level semester would miss subjects the student
            // is enrolled in from a different semester of the same school year.
            //
            // Solution: always use the school-year-wide lookup so both Regular
            // and Irregular assessments are protected with the same single query.
            $alreadyEnrolled = StudentEnrollment::enrolledSubjectIdsForYear(
                (int) $base['user_id'],
                $base['school_year']
            );

            $blockedIds = array_intersect(
                array_map('intval', $base['selected_subjects']),
                $alreadyEnrolled
            );

            if (! empty($blockedIds)) {
                $blockedCodes = $subjects
                    ->whereIn('id', $blockedIds)
                    ->pluck('code')
                    ->implode(', ');

                throw new \InvalidArgumentException(
                    "The following subject(s) are already enrolled for this school year: {$blockedCodes}. " .
                    'Please remove them from the selection.'
                );
            }

            $yearNum = (int) explode('-', $base['school_year'])[0];

            // ── Fee calculation using LEC and LAB units separately ──────────
            // Tuition = sum of (lecture_units × rate per unit)
            // Lab = count of subjects with lab_units > 0 × lab_fee per subject
            $tuitionTotal = round($subjects->sum(fn ($s) => ($s->lec_units ?? 0) * $rate), 2);
            $labTotal     = round($subjects->filter(fn ($s) => ($s->lab_units ?? 0) > 0)->count() * $labFee, 2);

            // Fixed miscellaneous block — same every semester
            $miscItems = collect(config('fees.miscellaneous', []));
            $miscTotal = round($miscItems->sum('amount'), 2);

            $grandTotal = round($tuitionTotal + $labTotal + $miscTotal, 2);

            if ($grandTotal <= 0) {
                throw new \InvalidArgumentException('Total assessment amount must be greater than zero.');
            }

            // ── Build fee breakdown for storage ──────────────────────────────
            $feeBreakdown = [];

            // ✅ TUITION: Lecture units × rate per unit
            // Store LEC units separately so frontend can display "LEC Units" column
            foreach ($subjects as $subject) {
                if (($subject->lec_units ?? 0) > 0) {
                    $feeBreakdown[] = [
                        'category'   => 'Tuition',
                        'name'       => $subject->name,
                        'code'       => $subject->code,
                        'units'      => $subject->lec_units ?? 0,
                        'amount'     => round(($subject->lec_units ?? 0) * $rate, 2),
                        'subject_id' => $subject->id,
                    ];
                }
            }

            // ✅ LABORATORY: Lab units (stored separately)
            // One row per subject with lab unit(s)
            foreach ($subjects as $subject) {
                if (($subject->lab_units ?? 0) > 0) {
                    $feeBreakdown[] = [
                        'category'   => 'Laboratory',
                        'name'       => 'Laboratory — ' . $subject->name,
                        'code'       => $subject->code . '-LAB',
                        'units'      => $subject->lab_units ?? 0,
                        'amount'     => $labFee,
                        'subject_id' => $subject->id,
                    ];
                }
            }

            // Fixed miscellaneous lines
            foreach ($miscItems as $item) {
                $feeBreakdown[] = [
                    'category' => $item['category'],
                    'name'     => $item['name'],
                    'units'    => 0,
                    'amount'   => (float) $item['amount'],
                ];
            }

            // Archive any existing active assessments for the same year/semester.
            StudentAssessment::where('user_id', $base['user_id'])
                ->where('status', 'active')
                ->where('year_level', $base['year_level'])
                ->where('semester', $base['semester'])
                ->where('school_year', $base['school_year'])
                ->update(['status' => 'archived']);

            $assessment = StudentAssessment::create([
                'user_id'           => $base['user_id'],
                'assessment_number' => StudentAssessment::generateAssessmentNumber(),
                'course'            => $base['course'],
                'year_level'        => $base['year_level'],
                'semester'          => $base['semester'],
                'school_year'       => $base['school_year'],
                'tuition_fee'       => $tuitionTotal + $labTotal,
                'other_fees'        => $miscTotal,
                'total_assessment'  => $grandTotal,
                'subjects'          => $base['selected_subjects'],
                'fee_breakdown'     => $feeBreakdown,
                'created_by'        => optional(auth()->user())->getKey(),
                'status'            => 'active',
            ]);

            // NOTE: No charge Transaction is created here.
            // The assessment's financial data lives in StudentAssessment.total_assessment
            // and StudentPaymentTerm records. Creating a kind='charge' Transaction row
            // was redundant and caused it to bleed into student-facing transaction views.
            // AccountService::recalculate() now derives balance from StudentPaymentTerm sums.
            $this->createPaymentTerms($assessment, $grandTotal);

            // ── Write enrollment records ─────────────────────────────────────
            // Persist one student_enrollments row per enrolled subject so that
            // future Create Assessment calls can correctly exclude these subjects
            // from the available selection list for this school year + semester.
            //
            // insertOrIgnore is safe here: the unique constraint
            // 'student_enroll_unique' (user_id, subject_id, school_year, semester)
            // already exists — duplicate calls silently no-op instead of throwing.
            $now            = now();
            $enrollmentRows = $subjects->map(fn ($subject) => [
                'user_id'     => (int) $base['user_id'],
                'subject_id'  => $subject->id,
                'school_year' => $base['school_year'],
                'semester'    => $base['semester'],
                'status'      => 'enrolled',
                'created_at'  => $now,
                'updated_at'  => $now,
            ])->toArray();

            StudentEnrollment::insertOrIgnore($enrollmentRows);

            $user = User::find($base['user_id']);

            // ── Enforce is_irregular consistency ─────────────────────────────
            // The student's is_irregular flag must always mirror the assessment
            // type chosen here. Without this, the Create Assessment form will
            // pre-select the wrong type on the next visit (it seeds assessmentType
            // from student.is_irregular), and the student list badge will be wrong.
            //
            // Bidirectional:
            //   assessment_type = 'irregular' → is_irregular = true
            //   assessment_type = 'regular'   → is_irregular = false
            //
            // $isIrregular is already computed at the top of this transaction
            // as ($base['assessment_type'] === 'irregular') — reuse it directly.
            $userUpdates = ['is_irregular' => $isIrregular];

            // Preserve existing course-backfill behaviour: only overwrite course
            // when the student has no course assigned yet (blank or 'N/A').
            if ($base['course'] && (! $user->course || $user->course === 'N/A')) {
                $userUpdates['course'] = $base['course'];
            }

            $user->update($userUpdates);

            AccountService::recalculate($user);

            DB::commit();

            $typeLabel = $isIrregular ? 'Irregular' : 'Regular';

            return redirect()
                ->route('student-fees.show', $base['user_id'])
                ->with('success', "{$typeLabel} assessment created — {$subjects->count()} subjects, {$subjects->sum('lec_units')} LEC units, total ₱" . number_format($grandTotal, 2) . ". 5 payment terms generated.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Assessment creation failed', [
                'user_id'         => $request->user_id,
                'assessment_type' => $request->assessment_type,
                'error'           => $e->getMessage(),
                'trace'           => $e->getTraceAsString(),
            ]);
            return back()->withErrors(['error' => 'Failed to create assessment: ' . $e->getMessage()]);
        }
    }

    // =========================================================================
    // SHOW
    // =========================================================================

    public function show(Request $request, $userId)
    {
        $student = User::with(['student', 'account'])
            ->students()
            ->findOrFail($userId);

        $allAssessments = StudentAssessment::where('user_id', $userId)
            ->where('status', 'active')
            ->with(['paymentTerms' => fn ($q) => $q->orderBy('term_order')])
            ->orderBy('school_year')
            ->orderByRaw("FIELD(semester, '1st Sem', '2nd Sem', 'Summer')")
            ->get()
            ->map(fn ($a) => [
                'id'               => $a->id,
                'course'           => $a->course,
                'semester'         => $a->semester,
                'school_year'      => $a->school_year,
                'year_level'       => $a->year_level,
                'total_assessment' => (float) $a->total_assessment,
                'tuition_fee'      => (float) $a->tuition_fee,
                'other_fees'       => (float) $a->other_fees,
                'fee_breakdown'    => $a->fee_breakdown ?? [],
                // paymentTerms loaded here so the assessment selector in Show.vue
                // can compute remainingBalance for any selected assessment, not
                // just the latest one.
                'paymentTerms'     => $a->paymentTerms->map(fn ($term) => [
                    'id'         => $term->id,
                    'term_name'  => $term->term_name,
                    'term_order' => $term->term_order,
                    'percentage' => $term->percentage,
                    'amount'     => (float) $term->amount,
                    'balance'    => (float) $term->balance,
                    'due_date'   => $term->due_date,
                    'status'     => $term->status,
                    'remarks'    => $term->remarks,
                    'paid_date'  => $term->paid_date,
                ])->toArray(),
            ]);

        $latestAssessment = StudentAssessment::where('user_id', $userId)
            ->where('status', 'active')
            ->with(['paymentTerms' => fn ($q) => $q->orderBy('term_order')])
            ->latest()
            ->first();

        $assessmentData = null;
        if ($latestAssessment) {
            $assessmentData = array_merge(
                $latestAssessment->toArray(),
                [
                    'paymentTerms' => $latestAssessment->paymentTerms
                        ->map(fn ($term) => [
                            'id'         => $term->id,
                            'term_name'  => $term->term_name,
                            'term_order' => $term->term_order,
                            'percentage' => $term->percentage,
                            'amount'     => (float) $term->amount,
                            'balance'    => (float) $term->balance,
                            'due_date'   => $term->due_date,
                            'status'     => $term->status,
                            'remarks'    => $term->remarks,
                            'paid_date'  => $term->paid_date,
                        ])
                        ->toArray(),
                ]
            );
        }

        // IMPORTANT: Only pass payment transactions to the Vue page.
        // Transactions with kind='charge' (reference prefix ASMT-) are assessment
        // debit entries created automatically when an assessment is saved. They are
        // NOT cashier payments and must never appear in the Payment History table.
        // They are visible in Students/WorkflowHistory.vue (Assessment History section).
        // Filter at the query level — not in Vue — so this rule is self-enforcing.
        $transactions = Transaction::where('user_id', $userId)
            ->where('kind', 'payment')
            ->with('fee')
            ->orderBy('created_at', 'desc')
            ->get();

        $payments = Payment::where('student_id', $student->student->id ?? null)
            ->with(['assessment:id,semester,school_year,year_level'])
            ->orderBy('paid_at', 'desc')
            ->get()
            ->map(fn ($p) => [
                'id'               => $p->id,
                'amount'           => (float) $p->amount,
                'description'      => $p->description,
                'payment_method'   => $p->payment_method,
                'reference_number' => $p->reference_number,
                'status'           => $p->status,
                'paid_at'          => $p->paid_at,
                'assessment_id'    => $p->student_assessment_id,
                'semester'         => $p->assessment?->semester ?? null,
                'school_year'      => $p->assessment?->school_year ?? null,
                'year_level'       => $p->assessment?->year_level ?? null,
            ]);

        if ($latestAssessment) {
            $feeBreakdown = collect();

            if ((float) $latestAssessment->tuition_fee > 0) {
                $feeBreakdown->push([
                    'category' => 'Tuition + Lab',
                    'total'    => (float) $latestAssessment->tuition_fee,
                    'items'    => count(collect($latestAssessment->fee_breakdown ?? [])->whereIn('category', ['Tuition', 'Laboratory'])->all()),
                ]);
            }

            $storedBreakdown = $latestAssessment->fee_breakdown ?? [];
            if (! empty($storedBreakdown)) {
                $grouped = collect($storedBreakdown)
                ->whereNotIn('category', ['Tuition', 'Laboratory'])
                ->groupBy('category');
            foreach ($grouped as $category => $items) {
                $feeBreakdown->push([
                    'category' => $category,
                    'total'    => collect($items)->sum('amount'),
                    'items'    => collect($items)->count(),
                ]);
            }
            } elseif ((float) $latestAssessment->other_fees > 0) {
                $feeBreakdown->push([
                    'category' => 'Miscellaneous',
                    'total'    => (float) $latestAssessment->other_fees,
                    'items'    => 1,
                ]);
            }
        } else {
            // $transactions is payment-only — fetch charge rows separately for the
            // fee breakdown summary card (this is NOT shown in Payment History).
           $feeBreakdown = Transaction::where('user_id', $userId)
                ->where('kind', 'charge')
                ->get()
                ->groupBy('type')
                ->map(fn ($group) => [
                'category' => collect($group)->first()->type ?? '',
                'total'    => collect($group)->sum('amount'),
                'items'    => collect($group)->count(),
            ]);
        }

        $backUrl = $request->query('from') === 'archive'
            ? route('students.archive')
            : route('student-fees.index');

        // ── enrolledSubjectsByAssessment — powers the Enrolled Subjects accordion ──
        //
        // Structure: enrolledSubjectsByAssessment[assessmentId] = int[]
        //
        // Maps student_enrollments rows back to their matching assessment ID via a
        // (school_year || semester) term index built from $allAssessments (already
        // loaded above). A single query retrieves all enrolled rows for this
        // student — no N+1.
        //
        // Vue uses this to render a ✓ Enrolled badge (green) vs ○ Assessment-only
        // badge (grey) per subject row in the accordion.
        $assessmentTermIndex = collect($allAssessments)->keyBy(
            fn ($a) => $a['school_year'] . '||' . $a['semester']
        );

        $enrollmentRows = StudentEnrollment::where('user_id', $userId)
            ->where('status', 'enrolled')
            ->get(['subject_id', 'school_year', 'semester']);

        $enrolledSubjectsByAssessment = [];

        foreach ($enrollmentRows as $row) {
            $termKey = $row->school_year . '||' . $row->semester;
            if (! isset($assessmentTermIndex[$termKey])) {
                // Enrollment exists for a term with no active assessment — skip.
                continue;
            }
            $assessmentId = data_get($assessmentTermIndex[$termKey], 'id');
            if (! isset($enrolledSubjectsByAssessment[$assessmentId])) {
                $enrolledSubjectsByAssessment[$assessmentId] = [];
            }
            $enrolledSubjectsByAssessment[$assessmentId][] = (int) $row->subject_id;
        }

        return Inertia::render('StudentFees/Show', [
            'student'                      => $student,
            'student_model_id'             => $student->student->id ?? null,
            'assessment'                   => $assessmentData,
            'allAssessments'               => $allAssessments,
            'transactions'                 => $transactions,
            'payments'                     => $payments,
            'feeBreakdown'                 => $feeBreakdown->values(),
            'backUrl'                      => $backUrl,
            // Powers the Enrolled Subjects accordion on Show.vue.
            // enrolledSubjectsByAssessment[assessmentId] = int[] of subject IDs
            // confirmed in student_enrollments with status = 'enrolled'.
            'enrolledSubjectsByAssessment' => $enrolledSubjectsByAssessment,
            // Fee rates passed to Show.vue so the formula label in the Fee Breakdown
            // card header stays in sync with config/fees.php automatically.
            // Previously Show.vue hardcoded 364.00 and 1656.00 — if rates change
            // in config/fees.php the label would silently show the wrong values.
            'tuitionPerUnit'              => (float) config('fees.tuition_per_unit',    364.00),
            'labFeePerSubject'            => (float) config('fees.lab_fee_per_subject', 1656.00),
        ]);
    }

    // =========================================================================
    // STORE PAYMENT (accounting/admin side)
    // =========================================================================
    //
    // Auto-allocates the entered amount sequentially across all unpaid terms
    // for the student's currently selected assessment (oldest term first by
    // term_order ASC). No manual term selection is required from accounting.
    //
    // No hard upper-limit — accounting may record any valid numeric amount.
    // If the amount exceeds all outstanding balances the excess is noted in
    // the success message and the transaction meta; it is NOT silently dropped.
    //
    // Allocation per call:
    //   1. Load all unpaid terms for the assessment ordered by term_order ASC
    //   2. Walk terms: apply min(remaining_amount, term.balance) to each term
    //   3. Mark each touched term PAID or PARTIAL accordingly
    //   4. Write ONE Transaction for the full payment amount (audit trail)
    //   5. Write ONE Payment record per term that received funds
    //   6. Store per-term allocation breakdown in transaction.meta
    // =========================================================================

    public function storePayment(Request $request, $userId)
    {
        $validated = $request->validate([
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:cash,gcash,bank_transfer,credit_card,debit_card',
            'assessment_id'  => 'required|exists:student_assessments,id',
            'payment_date'   => 'required|date',
        ]);

        $student = User::with('student', 'account')
            ->students()
            ->findOrFail($userId);

        if (! $student->student) {
            return back()->withErrors(['error' => 'Student record not found. Please contact administrator.']);
        }

        // Confirm the assessment belongs to this student and is active
        $assessment = StudentAssessment::where('id', $validated['assessment_id'])
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if (! $assessment) {
            return back()->withErrors(['error' => 'Assessment not found or does not belong to this student.']);
        }

        // Load all unpaid terms for this assessment, oldest (lowest term_order) first
        $unpaidTerms = StudentPaymentTerm::where('student_assessment_id', $assessment->id)
            ->whereIn('status', PaymentStatus::unpaidValues())
            ->where('balance', '>', 0)
            ->orderBy('term_order')
            ->get();

        if ($unpaidTerms->isEmpty()) {
            return back()->withErrors(['error' => 'This assessment has no outstanding balances to pay.']);
        }

        $paymentAmount = round((float) $validated['amount'], 2);
        $remaining     = $paymentAmount;

        DB::beginTransaction();
        try {
            $reference  = 'PAY-' . Str::upper(Str::random(8));
            $paidAt     = $validated['payment_date'];
            $method     = $validated['payment_method'];
            $yearStart  = (int) explode('-', $assessment->school_year)[0];
            $allocation = []; // per-term breakdown stored in meta for full audit trail

            // ── Sequential allocation across unpaid terms ─────────────────────
            foreach ($unpaidTerms as $term) {
                if ($remaining <= 0) {
                    break;
                }

                $termBalance = round((float) $term->balance, 2);
                $applied     = round(min($remaining, $termBalance), 2);
                $newBalance  = round($termBalance - $applied, 2);
                $newStatus   = $newBalance <= 0
                    ? PaymentStatus::PAID->value
                    : PaymentStatus::PARTIAL->value;

                // Update this term's balance and status
                $term->update([
                    'balance'   => $newBalance,
                    'status'    => $newStatus,
                    'paid_date' => $newStatus === PaymentStatus::PAID->value ? now() : $term->paid_date,
                ]);

                // One Payment record per term — gives per-term payment history
                if ($student->student) {
                    Payment::create([
                        'student_id'            => $student->student->id,
                        'student_assessment_id' => $assessment->id,
                        'amount'                => $applied,
                        'payment_method'        => $method,
                        'reference_number'      => $reference,
                        'description'           => 'Payment — ' . $term->term_name
                            . ' (from ₱' . number_format($paymentAmount, 2) . ' total)',
                        'status'                => PaymentStatus::COMPLETED->value,
                        'paid_at'               => $paidAt,
                    ]);
                }

                // Record allocation detail for the transaction meta audit trail
                $allocation[] = [
                    'term_id'        => $term->id,
                    'term_name'      => $term->term_name,
                    'term_order'     => $term->term_order,
                    'applied'        => $applied,
                    'balance_before' => $termBalance,
                    'balance_after'  => $newBalance,
                    'status_after'   => $newStatus,
                ];

                $remaining = round($remaining - $applied, 2);
            }

            // ── Build a human-readable transaction type/description ───────────
            $termsLabel   = collect($allocation)->pluck('term_name')->implode(', ');
            $totalApplied = round($paymentAmount - $remaining, 2);

            $transactionType = count($allocation) > 1
                ? 'Multi-Term Payment'
                : ($allocation[0]['term_name'] ?? 'Payment');

            $description = count($allocation) > 1
                ? '₱' . number_format($totalApplied, 2) . ' allocated across: ' . $termsLabel
                : 'Payment — ' . ($allocation[0]['term_name'] ?? 'Term');

            // ── ONE transaction record for the full payment amount ────────────
            Transaction::create([
                'user_id'         => $student->id,
                'reference'       => $reference,
                'kind'            => 'payment',
                'type'            => $transactionType,
                'amount'          => $paymentAmount,
                'status'          => PaymentStatus::PAID->value,
                'payment_channel' => $method,
                'paid_at'         => $paidAt,
                'year'            => $yearStart,
                'semester'        => $assessment->semester,
                'meta'            => [
                    'payment_method'    => $method,
                    'description'       => $description,
                    'assessment_id'     => $assessment->id,
                    'allocation'        => $allocation,
                    'terms_covered'     => count($allocation),
                    'total_applied'     => $totalApplied,
                    'unallocated'       => $remaining,
                    'recorded_by' => auth()->check() ? auth()->user()->id : null,
                    'requires_approval' => false,
                ],
            ]);

            // Recalculate account running balance after all term updates
            AccountService::recalculate($student);

            // Notify admin if all terms for this assessment are now fully paid
            $paymentService = new StudentPaymentService();
            $paymentService->notifyProgressionIfComplete($student, $assessment->id);

            DB::commit();

            // ── Success message ───────────────────────────────────────────────
            $successMsg = '₱' . number_format($totalApplied, 2) . ' recorded successfully';

            if (count($allocation) > 1) {
                $successMsg .= ' across ' . $termsLabel;
            } else {
                $successMsg .= ' for ' . ($allocation[0]['term_name'] ?? 'term');
            }

            if ($remaining > 0) {
                $successMsg .= '. Note: ₱' . number_format($remaining, 2)
                    . ' exceeded all outstanding balances and was not applied.';
            }

            return back()->with('success', $successMsg . '.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Accounting payment recording failed', [
                'user_id'       => $userId,
                'assessment_id' => $validated['assessment_id'],
                'amount'        => $validated['amount'],
                'error'         => $e->getMessage(),
                'trace'         => $e->getTraceAsString(),
            ]);
            return back()->withErrors([
                'error' => 'Failed to record payment: ' . $e->getMessage(),
            ]);
        }
    }

    // =========================================================================
    // EDIT / UPDATE
    // =========================================================================

    public function edit($userId)
    {
        $student = User::with(['student', 'account'])
            ->students()
            ->findOrFail($userId);

        $assessment = StudentAssessment::where('user_id', $userId)
            ->where('status', 'active')
            ->latest()
            ->first();

        if (! $assessment) {
            return redirect()
                ->route('student-fees.create')
                ->with('info', 'Please create an assessment for this student first.');
        }

        return Inertia::render('StudentFees/Edit', [
            'student'          => [
                'id'             => $student->id,
                'account_id'     => $student->account_id,
                'name'           => $student->name,
                'last_name'      => $student->last_name,
                'first_name'     => $student->first_name,
                'middle_initial' => $student->middle_initial,
                'email'          => $student->email,
                'birthday'       => $student->birthday
                    ? \Carbon\Carbon::parse($student->birthday)->format('Y-m-d')
                    : null,
                'phone'          => $student->phone,
                'address'        => $student->address,
                'course'         => $student->course,
                'year_level'     => $student->year_level,
                'status'         => $student->status,
            ],
            'assessment'       => $assessment,
            'courses'          => $this->allCourses(),
            'feeCategories'    => config('fees.categories', []),
            // For edit, still support manual fee items in case subjects change
            'subjectMap'       => $this->buildSubjectMap(),
            'tuitionPerUnit'   => (float) config('fees.tuition_per_unit',    364.00),
            'labFeePerSubject' => (float) config('fees.lab_fee_per_subject', 1656.00),
            'miscItems'        => config('fees.miscellaneous', []),
        ]);
    }

    public function update(Request $request, $userId)
    {
        $validated = $request->validate([
            'last_name'            => 'required|string|max:255',
            'first_name'           => 'required|string|max:255',
            'middle_initial'       => 'nullable|string|max:10',
            'email'                => 'required|email|max:255|unique:users,email,' . $userId,
            'birthday'             => 'nullable|date',
            'phone'                => 'nullable|string|max:20',
            'address'              => 'nullable|string|max:255',
            'course'               => 'required|string|max:100',
            'year_level'           => 'required|string',
            'semester'             => 'required|in:1st Sem,2nd Sem,Summer',
            'school_year'          => ['required', 'string', 'max:20', 'regex:/^\d{4}-\d{4}$/'],
            'fee_items'            => 'required|array|min:1',
            'fee_items.*.category'   => 'required|string|in:Tuition,Laboratory,Miscellaneous,Other',
            'fee_items.*.name'       => 'required|string|max:100',
            'fee_items.*.amount'     => 'required|numeric|min:0',
            // Subject metadata fields — optional, preserved from original fee_breakdown
            'fee_items.*.subject_id' => 'nullable|integer',
            'fee_items.*.code'       => 'nullable|string|max:50',
            'fee_items.*.lec_units'  => 'nullable|integer|min:0',
            'fee_items.*.lab_units'  => 'nullable|integer|min:0',
            'fee_items.*.units'      => 'nullable|integer|min:0',
        ]);

        DB::beginTransaction();
        try {
            $user = User::students()->findOrFail($userId);

            $user->update([
                'last_name'      => $validated['last_name'],
                'first_name'     => $validated['first_name'],
                'middle_initial' => $validated['middle_initial'] ?? null,
                'email'          => $validated['email'],
                'birthday'       => $validated['birthday'] ?? null,
                'phone'          => $validated['phone']   ?? null,
                'address'        => $validated['address'] ?? null,
                'course'         => $validated['course'],
                'year_level'     => $validated['year_level'],
            ]);

            $assessment = StudentAssessment::where('user_id', $userId)
                ->where('status', 'active')
                ->latest()
                ->firstOrFail();

            $feeItems     = collect($validated['fee_items']);
            $tuitionTotal = round($feeItems->whereIn('category', ['Tuition', 'Laboratory'])->sum('amount'), 2);
            $otherTotal   = round($feeItems->whereNotIn('category', ['Tuition', 'Laboratory'])->sum('amount'), 2);
            $grandTotal   = round($tuitionTotal + $otherTotal, 2);

            // ── Preserve subject metadata from the existing fee_breakdown ─────────
            // Edit.vue sends fee_items as { category, name, amount } only.
            // The original fee_breakdown (written by store()) also contains:
            //   subject_id, code, units — needed by the Enrolled Subjects accordion.
            // We match existing rows by (category + name) to re-attach the metadata.
            // Rows without a match (e.g. manually added misc lines) are saved as-is.
            $existingBreakdown = collect($assessment->fee_breakdown ?? []);
            $existingLookup    = $existingBreakdown->keyBy(
                fn ($row) => ($row['category'] ?? '') . '||' . ($row['name'] ?? '')
            );

            $oldSchoolYear = $assessment->school_year;
            $oldSemester   = $assessment->semester;

            $rebuiltBreakdown = $feeItems->map(function ($item) use ($existingLookup) {
                $lookupKey = ($item['category'] ?? '') . '||' . ($item['name'] ?? '');
                $existing  = $existingLookup->get($lookupKey);

                // lec_units / lab_units priority:
                //   1. Value sent explicitly by Edit.vue (carried through from original fee_breakdown)
                //   2. Value from the existingLookup match (same-category+name row in stored breakdown)
                //   3. Legacy 'units' field from the stored row
                //   4. Zero (safe default for manually-added misc lines)
                $lecUnits = (int) ($item['lec_units']
                    ?? $existing['lec_units']
                    ?? ($existing['units'] ?? 0));

                $labUnits = (int) ($item['lab_units']
                    ?? $existing['lab_units']
                    ?? 0);

                return [
                    'category'   => $item['category'],
                    'name'       => $item['name'],
                    'amount'     => (float) $item['amount'],
                    // Subject metadata — preserved from the original row
                    'subject_id' => $item['subject_id'] ?? $existing['subject_id'] ?? null,
                    'code'       => $item['code']       ?? $existing['code']       ?? null,
                    // Unit data — critical for Show.vue buildSubjectPanel()
                    // to display correct LEC/LAB columns after an edit.
                    'lec_units'  => $lecUnits,
                    'lab_units'  => $labUnits,
                    'units'      => $existing['units'] ?? 0,
                ];
            })->values()->toArray();

            $assessment->update([
                'course'           => $validated['course'],
                'year_level'       => $validated['year_level'],
                'semester'         => $validated['semester'],
                'school_year'      => $validated['school_year'],
                'fee_breakdown'    => $rebuiltBreakdown,
                'tuition_fee'      => $tuitionTotal,
                'other_fees'       => $otherTotal,
                'total_assessment' => $grandTotal,
            ]);

            // ── Sync StudentEnrollment rows when term identifier changes ──────
            // store() writes enrollment rows keyed by (school_year, semester).
            // If update() changes either value, show()'s assessmentTermIndex
            // lookup would fail to match those rows to this assessment, causing
            // the Enrolled Subjects accordion to show all subjects as grey ○.
            //
            // Fix: update the enrollment rows for this student's subjects to
            // reflect the new school_year and semester.
            $termChanged = $oldSchoolYear !== $validated['school_year']
                || $oldSemester !== $validated['semester'];

            if ($termChanged) {
                $subjectIds = collect($rebuiltBreakdown)
                    ->filter(fn ($row) => ! empty($row['subject_id']))
                    ->pluck('subject_id')
                    ->unique()
                    ->values()
                    ->toArray();

                if (! empty($subjectIds)) {
                    StudentEnrollment::where('user_id', $userId)
                        ->where('school_year', $oldSchoolYear)
                        ->where('semester', $oldSemester)
                        ->whereIn('subject_id', $subjectIds)
                        ->where('status', 'enrolled')
                        ->update([
                            'school_year' => $validated['school_year'],
                            'semester'    => $validated['semester'],
                        ]);

                    Log::info('Synced StudentEnrollment rows after assessment term change', [
                        'user_id'         => $userId,
                        'assessment_id'   => $assessment->id,
                        'old_school_year' => $oldSchoolYear,
                        'old_semester'    => $oldSemester,
                        'new_school_year' => $validated['school_year'],
                        'new_semester'    => $validated['semester'],
                        'subject_count'   => count($subjectIds),
                    ]);
                }
            }

            $terms = $assessment->paymentTerms()->orderBy('term_order')->get();
            $oldTotal = 0;
            if ($terms->isNotEmpty()) {
                $oldTotal     = $terms->sum('amount');
                $scaleFactor  = $oldTotal > 0 ? ($grandTotal / $oldTotal) : 1;
                $runningTotal = 0;
                $lastIdx      = $terms->count() - 1;

                foreach ($terms as $i => $term) {
                    if ($i === $lastIdx) {
                        $newAmount = round($grandTotal - $runningTotal, 2);
                    } else {
                        $newAmount = round($term->amount * $scaleFactor, 2);
                        $runningTotal += $newAmount;
                    }

                    $alreadyPaid = $term->amount - $term->balance;
                    $newBalance  = max(0, round($newAmount - $alreadyPaid, 2));

                    $term->update([
                        'amount'  => $newAmount,
                        'balance' => $newBalance,
                    ]);
                }
            }

            // NOTE: No charge Transaction is updated here.
            // Charge transactions are no longer created on assessment store/update.
            // Balance is derived from StudentPaymentTerm sums via AccountService.

            StudentAssessment::where('user_id', $userId)
                ->where('status', 'active')
                ->where('id', '!=', $assessment->id)
                ->where('year_level', $validated['year_level'])
                ->where('semester', $validated['semester'])
                ->where('school_year', $validated['school_year'])
                ->update(['status' => 'archived']);

            AccountService::recalculate($user);

            DB::commit();

            return redirect()
                ->route('student-fees.show', $userId)
                ->with('success', 'Student information and assessment updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Assessment update failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'Update failed: ' . $e->getMessage()]);
        }
    }

    // =========================================================================
    // EXPORT PDF
    // =========================================================================

    public function exportPdf($userId)
    {
        $student = User::with(['student', 'account'])
            ->students()
            ->findOrFail($userId);

        $assessmentId   = request('assessment_id');
        $semesterFilter = request('semester');

        $assessmentQuery = StudentAssessment::where('user_id', $userId)
            ->where('status', 'active')
            ->with(['paymentTerms' => fn ($q) => $q->orderBy('term_order')]);

        if ($assessmentId) {
            $assessmentQuery->where('id', $assessmentId);
        } elseif ($semesterFilter) {
            $assessmentQuery->where('semester', $semesterFilter);
        }

        $assessment = $assessmentQuery->latest()->firstOrFail();

        $schoolYearStart = (int) explode('-', $assessment->school_year)[0];

        $transactions = Transaction::where('user_id', $userId)
            ->where(function ($q) use ($assessment, $schoolYearStart) {
                $q->where(function ($inner) use ($assessment, $schoolYearStart) {
                    $inner->where('year', (string) $schoolYearStart)
                          ->where('semester', $assessment->semester);
                })
                ->orWhere(function ($inner) use ($assessment) {
                    $inner->whereJsonContains('meta->assessment_id', $assessment->id);
                });
            })
            ->with('fee')
            ->orderBy('created_at', 'desc')
            ->get();

        $assessmentTransactionRefs = $transactions
            ->where('kind', 'payment')
            ->pluck('reference')
            ->filter()
            ->values();

        $payments = Payment::where('student_id', $student->student->id ?? null)
            ->where(function ($q) use ($assessment, $assessmentTransactionRefs) {
                $q->where('student_assessment_id', $assessment->id)
                  ->orWhereIn('reference_number', $assessmentTransactionRefs);
            })
            ->orderBy('paid_at', 'desc')
            ->get();

        $paymentTerms = $assessment->paymentTerms()->orderBy('term_order')->get();

        $pdf = Pdf::loadView('pdf.student-assessment', [
            'student'      => $student,
            'assessment'   => $assessment,
            'transactions' => $transactions,
            'payments'     => $payments,
            'paymentTerms' => $paymentTerms,
        ]);

        $pdf->setPaper('A4', 'portrait');
        $filename = 'receipt-' . $student->account_id
            . '-' . str_replace(' ', '-', $assessment->year_level)
            . '-' . str_replace(' ', '-', $assessment->semester)
            . '-' . $assessment->school_year . '.pdf';
        $filename = str_replace(['/', ' '], '-', $filename);

        return $pdf->download($filename);
    }

    // ── DROP STUDENT ─────────────────────────────────────────────────────────

    public function drop(Request $request, User $user): \Illuminate\Http\RedirectResponse
    {
        $student = Student::where('user_id', $user->id)->firstOrFail();

        $droppable = ['active', 'pending', 'suspended'];

        if (! in_array($student->enrollment_status, $droppable)) {
            return back()->with(
                'error',
                "Only active, pending, or suspended students can be dropped. Current status: {$student->enrollment_status}."
            );
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $fromStatus = $student->enrollment_status;

        $student->update(['enrollment_status' => 'dropped']);
        $user->update(['status' => User::STATUS_DROPPED]);

        StudentStatusLog::create([
            'student_id'  => $student->id,
            'changed_by' => auth()->check() ? auth()->user()->id : null,
            'from_status' => $fromStatus,
            'to_status'   => 'dropped',
            'reason'      => $request->input('reason'),
            'action'      => 'drop',
        ]);

        $name = "{$user->last_name}, {$user->first_name}";

        return back()->with('success', "{$name} has been marked as Dropped.");
    }

    // =========================================================================
    // CREATE STUDENT / STORE STUDENT
    // =========================================================================

    public function createStudent()
    {
        // Ensure only Admin can create students
        if (auth()->user()->role !== UserRoleEnum::ADMIN) {
            abort(403, 'Only administrators can create new students.');
        }

        return Inertia::render('StudentFees/CreateStudent', [
            'courses'    => $this->allCourses(),
            'yearLevels' => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
        ]);
    }

    public function storeStudent(Request $request)
    {
        // Ensure only Admin can create students
        if (auth()->user()->role !== UserRoleEnum::ADMIN) {
            abort(403, 'Only administrators can create new students.');
        }

        $validated = $request->validate([
            'last_name'      => 'required|string|max:255',
            'first_name'     => 'required|string|max:255',
            'middle_initial' => 'nullable|string|max:10',
            'email'          => 'required|email|unique:users,email',
            'birthday'       => 'required|date',
            'phone'          => 'required|string|max:20',
            'address'        => 'required|string|max:255',
            'year_level'     => 'required|string',
            'course'         => 'required|string',
            'account_id'     => 'nullable|string|unique:users,account_id',
        ]);

        DB::beginTransaction();
        try {
            $currentYear = date('Y');
            $randomNum   = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $studentId   = "{$currentYear}-{$randomNum}";

            while (Student::where('student_id', $studentId)->exists()) {
                $randomNum = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $studentId = "{$currentYear}-{$randomNum}";
            }

            $accountId = $this->generateUniqueAccountId();

            $user = User::create([
                'last_name'         => $validated['last_name'],
                'first_name'        => $validated['first_name'],
                'middle_initial'    => $validated['middle_initial'] ?? null,
                'email'             => $validated['email'],
                'birthday'          => $validated['birthday'],
                'phone'             => $validated['phone'],
                'address'           => $validated['address'],
                'year_level'        => $validated['year_level'],
                'course'            => $validated['course'],
                'account_id'        => $accountId,
                'role'              => UserRoleEnum::STUDENT->value,
                'is_active'         => true,
                'status'            => User::STATUS_ACTIVE,
                'email_verified_at' => now(),
                'password'          => Hash::make('password'),
            ]);

            Student::create([
                'user_id'           => $user->id,
                'student_id'        => $studentId,
                'enrollment_status' => 'pending',
            ]);

            // ── BUG FIX #1: Create Account record immediately ──
            // Previously, Account was only created defensively by AccountService::recalculate()
            // on first assessment. Now create it here so student has account_number immediately.
            Account::create([
                'user_id'        => $user->id,
                'account_number' => Account::generateAccountNumber(),
                'balance'        => 0,
            ]);

            DB::commit();

            $user->refresh();

            return redirect()
                ->route('student-fees.show', $user->id)
                ->with('success', "Student {$user->first_name} {$user->last_name} (Account ID: {$user->account_id}) created successfully!");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Student creation failed: ' . $e->getMessage(), [
                'email' => $validated['email'] ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withErrors(['error' => 'Failed to create student: ' . $e->getMessage()]);
        }
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function createPaymentTerms(StudentAssessment $assessment, float $total): void
    {
        /** @var array<int, array{name: string, percentage: float}> $termDefs */
        $termDefs  = config('fees.terms');
        $lastOrder = array_key_last($termDefs);
        $allocated = 0.00;

        foreach ($termDefs as $order => $def) {
            $amount = ($order === $lastOrder)
                ? round($total - $allocated, 2)
                : round(($def['percentage'] / 100) * $total, 2);

            if ($order !== $lastOrder) {
                $allocated += $amount;
            }

            StudentPaymentTerm::create([
                'student_assessment_id'  => $assessment->id,
                'term_name'              => $def['name'],
                'term_order'             => $order,
                'percentage'             => $def['percentage'],
                'amount'                 => $amount,
                'balance'                => $amount,
                'due_date'               => null,
                'status'                 => PaymentStatus::PENDING->value,
                'remarks'                => null,
                'paid_date'              => null,
                'carryover_from_term_id' => null,
                'carryover_amount'       => 0.00,
            ]);
        }
    }

    private function generateUniqueAccountId(): string
    {
        do {
            $accountId = 'STU-' . str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT);
        } while (User::where('account_id', $accountId)->exists());

        return $accountId;
    }

    /**
     * Check if a student has an active assessment with outstanding balance.
     * Used during Create Assessment form rendering to warn staff and block submission.
     *
     * Returns:
     *   null if student has no active assessments with unpaid balance
     *   array with assessment details if one exists (to display in warning)
     */
    private function getActiveAssessmentInfo(int $userId): ?array
    {
        $assessment = StudentAssessment::where('user_id', $userId)
            ->where('status', 'active')
            ->whereHas('paymentTerms', fn ($q) => $q->where('balance', '>', 0))
            ->with(['paymentTerms' => fn ($q) => $q->where('balance', '>', 0)])
            ->latest()
            ->first();

        if (!$assessment) {
            return null;
        }

        $totalBalance = $assessment->paymentTerms->sum('balance');

        return [
            'id'                 => $assessment->id,
            'assessment_number'  => $assessment->assessment_number,
            'year_level'         => $assessment->year_level,
            'semester'           => $assessment->semester,
            'school_year'        => $assessment->school_year,
            'total_assessment'   => (float) $assessment->total_assessment,
            'remaining_balance'  => round($totalBalance, 2),
            'unpaid_term_count'  => $assessment->paymentTerms->count(),
        ];
    }
}