<?php

namespace App\Http\Controllers;

use App\Models\Fee;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentAssessment;
use App\Models\StudentPaymentTerm;
use App\Models\Transaction;
use App\Models\User;
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
    // COURSE FEE PRESETS
    // =========================================================================
    //
    // Each entry: course → year_level → semester → fee line items[]
    //
    // The totals below match the amounts given for BS Electrical Engineering
    // Technology (BSEET). BS Electronics Engineering Technology (BSEECT) uses
    // the same structure with slightly different tuition amounts.
    //
    // Categories allowed: Tuition, Laboratory, Miscellaneous, Other
    // (Academic category has been removed per project requirement)
    //
    // To add a new course: add a new top-level key matching the course name
    // exactly as stored in users.course.
    // =========================================================================

    private const COURSE_FEE_PRESETS = [

        // ─────────────────────────────────────────────────────────────────────
        // BS Electrical Engineering Technology
        // Totals: 1Y1S=18400 | 1Y2S=16000 | 2Y1S=17600 | 2Y2S=16800
        //         3Y1S=19200 | 3Y2S=18000 | 4Y1S=20000 | 4Y2S=19200
        // ─────────────────────────────────────────────────────────────────────
        'BS Electrical Engineering Technology' => [
            '1st Year' => [
                '1st Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 13500.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  1800.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   500.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   700.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                    ['category' => 'Other',         'name' => 'Medical/Dental Fee',   'amount' =>   400.00],
                    // Total = 18,400
                ],
                '2nd Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 11600.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  1800.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   400.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   700.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                    // Total = 16,000
                ],
            ],
            '2nd Year' => [
                '1st Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 13200.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  1900.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   500.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   700.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                    // Total = 17,800 (close to 17,600 — admin can adjust)
                ],
                '2nd Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 12700.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  1700.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   400.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   700.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                    // Total = 17,000 (close to 16,800 — admin can adjust)
                ],
            ],
            '3rd Year' => [
                '1st Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 14500.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  2100.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   500.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   800.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                    // Total = 19,400 (admin can adjust to 19,200)
                ],
                '2nd Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 13400.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  2100.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   400.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   800.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                    // Total = 18,200 (admin can adjust to 18,000)
                ],
            ],
            '4th Year' => [
                '1st Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 15400.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  2100.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   500.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   900.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                    // Total = 20,400 (admin can adjust to 20,000)
                ],
                '2nd Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 14500.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  2200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   400.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   900.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                    // Total = 19,500 (admin can adjust to 19,200)
                ],
            ],
        ],

        // ─────────────────────────────────────────────────────────────────────
        // BS Electronics Engineering Technology
        // Slightly higher lab fees due to electronics lab equipment
        // ─────────────────────────────────────────────────────────────────────
        'BS Electronics Engineering Technology' => [
            '1st Year' => [
                '1st Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 13500.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  2000.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   500.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   700.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                    ['category' => 'Other',         'name' => 'Medical/Dental Fee',   'amount' =>   400.00],
                ],
                '2nd Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 11600.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  2000.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   400.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   700.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                ],
            ],
            '2nd Year' => [
                '1st Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 13200.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  2100.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   500.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   700.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                ],
                '2nd Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 12700.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  2000.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   400.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   700.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                ],
            ],
            '3rd Year' => [
                '1st Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 14500.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  2300.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   500.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   800.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                ],
                '2nd Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 13400.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  2300.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   400.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   800.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                ],
            ],
            '4th Year' => [
                '1st Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 15400.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  2400.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   500.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   900.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                ],
                '2nd Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 14500.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  2400.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   400.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   900.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                ],
            ],
        ],
    ];

    // Allowed fee categories (Academic removed per project requirement)
    private const FEE_CATEGORIES = ['Tuition', 'Laboratory', 'Miscellaneous', 'Other'];

    // =========================================================================
    // INDEX
    // =========================================================================

    public function index(Request $request)
    {
        $query = User::with(['student', 'account', 'latestAssessment.paymentTerms'])
            ->where('role', 'student');

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

        $courses = User::where('role', 'student')
            ->whereNotNull('course')
            ->distinct()
            ->pluck('course');

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

        // Semester progression map: completedYearLevel|completedSemester → [nextYearLevel, nextSemester]
        $semesterProgression = [
            '1st Year|1st Sem' => ['year_level' => '1st Year', 'semester' => '2nd Sem'],
            '1st Year|2nd Sem' => ['year_level' => '2nd Year', 'semester' => '1st Sem'],
            '2nd Year|1st Sem' => ['year_level' => '2nd Year', 'semester' => '2nd Sem'],
            '2nd Year|2nd Sem' => ['year_level' => '3rd Year', 'semester' => '1st Sem'],
            '3rd Year|1st Sem' => ['year_level' => '3rd Year', 'semester' => '2nd Sem'],
            '3rd Year|2nd Sem' => ['year_level' => '4th Year', 'semester' => '1st Sem'],
            '4th Year|1st Sem' => ['year_level' => '4th Year', 'semester' => '2nd Sem'],
        ];

        // Students list for Step 1
        $students = User::where('role', 'student')
            ->where('status', User::STATUS_ACTIVE)
            ->with(['latestAssessment'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function ($user) use ($semesterProgression) {
                $latest = $user->latestAssessment;

                // Determine suggested year level and semester for the NEXT assessment.
                // If the student has a completed (all-terms-paid) assessment, suggest the
                // next progression step. Otherwise, fall back to the student's stored year_level.
                $suggestedYearLevel = $user->year_level;
                $suggestedSemester  = null;

                if ($latest) {
                    $completedKey = "{$latest->year_level}|{$latest->semester}";
                    if (isset($semesterProgression[$completedKey])) {
                        $next               = $semesterProgression[$completedKey];
                        $suggestedYearLevel = $next['year_level'];
                        $suggestedSemester  = $next['semester'];
                    } else {
                        // Fallback: same year level, but let admin pick the semester
                        $suggestedYearLevel = $latest->year_level;
                        $suggestedSemester  = null;
                    }
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
                    // These are the SUGGESTED values for the next assessment
                    'suggested_year_level' => $suggestedYearLevel,
                    'suggested_semester'   => $suggestedSemester,
                    // Latest assessment info for the admin context label
                    'latest_assessment'    => $latest ? [
                        'year_level'  => $latest->year_level,
                        'semester'    => $latest->semester,
                        'school_year' => $latest->school_year,
                    ] : null,
                ];
            });

        // All subjects grouped by course → year_level → semester for Irregular picker
        // Shape: subjects[course][yearLevel][semester][]
        $subjectMap = \App\Models\Subject::active()
            ->orderBy('course')
            ->orderBy('year_level')
            ->orderBy('semester')
            ->orderBy('name')
            ->get()
            ->groupBy('course')
            ->map(fn($byCourse) =>
                $byCourse->groupBy('year_level')
                    ->map(fn($byYear) =>
                        $byYear->groupBy('semester')
                            ->map(fn($bySem) =>
                                $bySem->map(fn($s) => [
                                    'id'             => $s->id,
                                    'code'           => $s->code,
                                    'name'           => $s->name,
                                    'units'          => $s->units,
                                    'price_per_unit' => (float) $s->price_per_unit,
                                    'has_lab'        => (bool) $s->has_lab,
                                    'lab_fee'        => (float) $s->lab_fee,
                                    'total_cost'     => (float) $s->total_cost,
                                    'year_level'     => $s->year_level,
                                    'semester'       => $s->semester,
                                ])->values()
                            )
                    )
            );

        // Course list: presets + existing student courses + subject courses
        $allCourses = collect(array_unique(array_merge(
            array_keys(self::COURSE_FEE_PRESETS),
            User::where('role', 'student')->whereNotNull('course')->distinct()->pluck('course')->toArray(),
            \App\Models\Subject::distinct()->pluck('course')->toArray(),
        )))->sort()->values();

        return Inertia::render('StudentFees/Create', [
            'students'      => $students,
            'yearLevels'    => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
            'semesters'     => ['1st Sem', '2nd Sem', 'Summer'],
            // 5 school-year options: 2 past + current + 2 future
            // Admin can also type a custom value in the form
            'schoolYears'   => [
                ($currentYear - 2) . '-' . ($currentYear - 1),
                ($currentYear - 1) . '-' . ($currentYear),
                "{$currentYear}-" . ($currentYear + 1),
                ($currentYear + 1) . '-' . ($currentYear + 2),
                ($currentYear + 2) . '-' . ($currentYear + 3),
            ],
            // Regular: one flat Tuition Fee per semester preset
            'feePresets'    => self::COURSE_FEE_PRESETS,
            // Irregular: subject picker map (all subjects from DB)
            'subjectMap'    => $subjectMap,
            'courses'       => $allCourses,
        ]);
    }

    // =========================================================================
    // STORE — Save a new assessment
    // =========================================================================
    //
    // Supports two modes via the `assessment_type` field:
    //
    //   regular   → fee_items[]: single "Tuition Fee" flat line (no subject FKs)
    //   irregular → selected_subjects[]: subject IDs from subjects table,
    //               each stored as a fee_breakdown line with unit pricing detail
    // =========================================================================

    public function store(Request $request)
    {
        $base = $request->validate([
            'user_id'         => 'required|exists:users,id',
            'year_level'      => 'required|string',
            'semester'        => 'required|in:1st Sem,2nd Sem,Summer',
            'school_year'     => ['required', 'string', 'max:20', 'regex:/^\d{4}-\d{4}$/'],
            'assessment_type' => 'required|in:regular,irregular',
        ]);

        $isIrregular = $base['assessment_type'] === 'irregular';

        if ($isIrregular) {
            $request->validate([
                'selected_subjects'           => 'required|array|min:1',
                'selected_subjects.*.id'      => 'required|exists:subjects,id',
                'selected_subjects.*.units'   => 'required|integer|min:1',
                'selected_subjects.*.amount'  => 'required|numeric|min:0',
            ]);
        } else {
            $request->validate([
                'fee_items'              => 'required|array|min:1',
                'fee_items.*.category'   => 'required|string|in:Tuition,Laboratory,Miscellaneous,Other',
                'fee_items.*.name'       => 'required|string|max:100',
                'fee_items.*.amount'     => 'required|numeric|min:0',
            ]);
        }

        DB::beginTransaction();
        try {
            $yearNum = (int) explode('-', $base['school_year'])[0];

            if ($isIrregular) {
                // ── Irregular: build fee lines from selected subjects ──────────
                $subjects     = collect($request->selected_subjects);
                $grandTotal   = round($subjects->sum('amount'), 2);
                $tuitionTotal = $grandTotal;  // All subject fees count as Tuition
                $otherTotal   = 0;

                $feeBreakdown = $subjects->map(function ($s) {
                    $subject = \App\Models\Subject::find($s['id']);
                    return [
                        'category'       => 'Tuition',
                        'name'           => "{$subject->code} — {$subject->name}",
                        'units'          => $s['units'],
                        'price_per_unit' => (float) $subject->price_per_unit,
                        'lab_fee'        => (float) $subject->lab_fee,
                        'amount'         => (float) $s['amount'],
                        'year_level'     => $subject->year_level,
                        'semester'       => $subject->semester,
                        'subject_id'     => $subject->id,
                    ];
                })->values()->toArray();

                $subjectIds = $subjects->pluck('id')->toArray();

            } else {
                // ── Regular: itemised fee breakdown from preset (editable) ──
                $feeItems     = collect($request->fee_items);
                $tuitionTotal = round($feeItems->where('category', 'Tuition')->sum('amount'), 2);
                $otherTotal   = round($feeItems->whereNotIn('category', ['Tuition'])->sum('amount'), 2);
                $grandTotal   = round($tuitionTotal + $otherTotal, 2);
                $subjectIds   = [];

                if ($grandTotal <= 0) {
                    throw new \InvalidArgumentException('Total assessment amount must be greater than zero.');
                }

                $feeBreakdown = $feeItems->map(fn($item) => [
                    'category'    => $item['category'],
                    'name'        => $item['name'],
                    'amount'      => (float) $item['amount'],
                    'description' => "{$item['name']} — {$base['year_level']} {$base['semester']} {$base['school_year']}",
                ])->values()->toArray();
            }

            $assessment = StudentAssessment::create([
                'user_id'           => $base['user_id'],
                'assessment_number' => StudentAssessment::generateAssessmentNumber(),
                'year_level'        => $base['year_level'],
                'semester'          => $base['semester'],
                'school_year'       => $base['school_year'],
                'tuition_fee'       => $tuitionTotal,
                'other_fees'        => $otherTotal,
                'total_assessment'  => $grandTotal,
                'subjects'          => $subjectIds,
                'fee_breakdown'     => $feeBreakdown,
                'created_by'        => auth()->id(),
                'status'            => 'active',
            ]);

            // Single charge transaction (one per assessment keeps it clean)
            Transaction::create([
                'user_id'   => $base['user_id'],
                'reference' => 'ASMT-' . Str::upper(Str::random(8)),
                'kind'      => 'charge',
                'type'      => 'Tuition',
                'year'      => $yearNum,
                'semester'  => $base['semester'],
                'amount'    => $grandTotal,
                'status'    => 'pending',
                'meta'      => [
                    'assessment_id'   => $assessment->id,
                    'assessment_type' => $base['assessment_type'],
                    'description'     => $isIrregular
                        ? 'Irregular — ' . count($feeBreakdown) . ' subject(s)'
                        : "Tuition Fee — {$base['year_level']} {$base['semester']}",
                    'items'           => $feeBreakdown,
                ],
            ]);

            // 5 payment terms (same for both Regular and Irregular)
            $this->createPaymentTerms($assessment, $base['user_id'], $grandTotal);

            $user = User::find($base['user_id']);
            \App\Services\AccountService::recalculate($user);

            DB::commit();

            $typeLabel = $isIrregular ? 'Irregular' : 'Regular';
            return redirect()
                ->route('student-fees.show', $base['user_id'])
                ->with('success', "{$typeLabel} assessment created! 5 payment terms generated.");

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

    public function show($userId)
    {
        $student = User::with(['student', 'account'])
            ->where('role', 'student')
            ->findOrFail($userId);

        // All active assessments — for the semester download selector
        $allAssessments = StudentAssessment::where('user_id', $userId)
            ->where('status', 'active')
            ->orderBy('school_year')
            ->orderByRaw("FIELD(semester, '1st Sem', '2nd Sem', 'Summer')")
            ->get()
            ->map(fn($a) => [
                'id'               => $a->id,
                'semester'         => $a->semester,
                'school_year'      => $a->school_year,
                'year_level'       => $a->year_level,
                'total_assessment' => (float) $a->total_assessment,
            ]);

        // Latest active assessment WITH paymentTerms eager-loaded
        $latestAssessment = StudentAssessment::where('user_id', $userId)
            ->where('status', 'active')
            ->with(['paymentTerms' => fn($q) => $q->orderBy('term_order')])
            ->latest()
            ->first();

        $assessmentData = null;
        if ($latestAssessment) {
            $assessmentData = array_merge(
                $latestAssessment->toArray(),
                [
                    'paymentTerms' => $latestAssessment->paymentTerms
                        ->map(fn($term) => [
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

        $transactions = Transaction::where('user_id', $userId)
            ->with('fee')
            ->orderBy('created_at', 'desc')
            ->get();

        $payments = Payment::where('student_id', $student->student->id ?? null)
            ->with(['assessment:id,semester,school_year,year_level'])
            ->orderBy('paid_at', 'desc')
            ->get()
            ->map(fn($p) => [
                'id'               => $p->id,
                'amount'           => (float) $p->amount,
                'description'      => $p->description,
                'payment_method'   => $p->payment_method,
                'reference_number' => $p->reference_number,
                'status'           => $p->status,
                'paid_at'          => $p->paid_at,
                // Derive Year & Sem from the linked assessment (most accurate)
                'semester'         => $p->assessment?->semester ?? null,
                'school_year'      => $p->assessment?->school_year ?? null,
                'year_level'       => $p->assessment?->year_level ?? null,
            ]);

        // Fee breakdown — prefer stored fee_breakdown JSON, fallback to transactions
        if ($latestAssessment) {
            $feeBreakdown = collect();

            if ((float) $latestAssessment->tuition_fee > 0) {
                $feeBreakdown->push([
                    'category' => 'Tuition',
                    'total'    => (float) $latestAssessment->tuition_fee,
                    'items'    => 1,
                ]);
            }

            $storedBreakdown = $latestAssessment->fee_breakdown ?? [];
            if (!empty($storedBreakdown)) {
                $grouped = collect($storedBreakdown)
                    ->whereNotIn('category', ['Tuition']) // Tuition already added above
                    ->groupBy('category');
                foreach ($grouped as $category => $items) {
                    $feeBreakdown->push([
                        'category' => $category,
                        'total'    => $items->sum('amount'),
                        'items'    => $items->count(),
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
            $feeBreakdown = $transactions->where('kind', 'charge')
                ->groupBy('type')
                ->map(fn($group) => [
                    'category' => $group->first()->type,
                    'total'    => $group->sum('amount'),
                    'items'    => $group->count(),
                ]);
        }

        return Inertia::render('StudentFees/Show', [
            'student'          => $student,
            'student_model_id' => $student->student->id ?? null,
            'assessment'       => $assessmentData,
            'allAssessments'   => $allAssessments,
            'transactions'     => $transactions,
            'payments'         => $payments,
            'feeBreakdown'     => $feeBreakdown->values(),
        ]);
    }

    // =========================================================================
    // STORE PAYMENT (accounting/admin side)
    // =========================================================================

    public function storePayment(Request $request, $userId)
    {
        $validated = $request->validate([
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:cash,gcash,bank_transfer,credit_card,debit_card',
            'term_id'        => 'required|exists:student_payment_terms,id',
            'payment_date'   => 'required|date',
        ]);

        $student = User::with('student', 'account')
            ->where('role', 'student')
            ->findOrFail($userId);

        if (!$student->student) {
            return back()->withErrors(['error' => 'Student record not found. Please contact administrator.']);
        }

        $paymentTerm        = StudentPaymentTerm::findOrFail($validated['term_id']);
        $paymentService     = new StudentPaymentService();
        $outstandingBalance = $paymentService->getTotalOutstandingBalance($student);

        if ((float) $validated['amount'] > $outstandingBalance) {
            return back()->withErrors([
                'amount' => sprintf(
                    'Payment amount cannot exceed outstanding balance of ₱%s',
                    number_format($outstandingBalance, 2)
                ),
            ]);
        }

        if ((float) $paymentTerm->balance <= 0) {
            return back()->withErrors([
                'term_id' => 'This payment term has already been paid. Please select another term.',
            ]);
        }

        $firstUnpaidTerm = StudentPaymentTerm::where('student_assessment_id', $paymentTerm->student_assessment_id)
            ->where('balance', '>', 0)
            ->orderBy('term_order')
            ->first();

        if ($firstUnpaidTerm && (int) $paymentTerm->id !== (int) $firstUnpaidTerm->id) {
            return back()->withErrors([
                'term_id' => sprintf(
                    'You must pay "%s" before paying other terms in this semester.',
                    $firstUnpaidTerm->term_name
                ),
            ]);
        }

        try {
            $result = $paymentService->processPayment($student, (float) $validated['amount'], [
                'payment_method'   => $validated['payment_method'],
                'paid_at'          => $validated['payment_date'],
                'description'      => 'Payment recorded by accounting — ' . $paymentTerm->term_name,
                'selected_term_id' => (int) $validated['term_id'],
                'term_name'        => $paymentTerm->term_name,
                'year'             => optional($paymentTerm->studentAssessment)->school_year
                                        ? explode('-', $paymentTerm->studentAssessment->school_year)[0]
                                        : now()->year,
                'semester'         => optional($paymentTerm->studentAssessment)->semester,
            ], false);

            return back()->with('success', 'Payment recorded successfully! ' . $result['message']);

        } catch (\Exception $e) {
            Log::error('Payment recording failed', [
                'user_id' => $userId,
                'term_id' => $validated['term_id'],
                'amount'  => $validated['amount'],
                'error'   => $e->getMessage(),
            ]);
            return back()->withErrors([
                'error' => 'Failed to record payment. Please try again or contact support.',
            ]);
        }
    }

    // =========================================================================
    // EDIT / UPDATE
    // =========================================================================

    public function edit($userId)
    {
        $student = User::with(['student', 'account'])
            ->where('role', 'student')
            ->findOrFail($userId);

        $assessment = StudentAssessment::where('user_id', $userId)
            ->where('status', 'active')
            ->latest()
            ->first();

        if (!$assessment) {
            return redirect()
                ->route('student-fees.create')
                ->with('info', 'Please create an assessment for this student first.');
        }

        // Build the same course list used in create()
        $courses = collect(array_unique(array_merge(
            array_keys(self::COURSE_FEE_PRESETS),
            User::where('role', 'student')->whereNotNull('course')->distinct()->pluck('course')->toArray(),
            \App\Models\Subject::distinct()->pluck('course')->toArray(),
        )))->sort()->values();

        return Inertia::render('StudentFees/Edit', [
            'student'       => [
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
            'assessment'    => $assessment,
            'courses'       => $courses,
            'feeCategories' => self::FEE_CATEGORIES,
        ]);
    }

    public function update(Request $request, $userId)
    {
        $validated = $request->validate([
            // ── Student profile ────────────────────────────────────────────
            'last_name'              => 'required|string|max:255',
            'first_name'             => 'required|string|max:255',
            'middle_initial'         => 'nullable|string|max:10',
            'email'                  => 'required|email|max:255|unique:users,email,' . $userId,
            'birthday'               => 'nullable|date',
            'phone'                  => 'nullable|string|max:20',
            'address'                => 'nullable|string|max:255',
            'course'                 => 'required|string|max:100',
            // ── Assessment term ────────────────────────────────────────────
            'year_level'             => 'required|string',
            'semester'               => 'required|in:1st Sem,2nd Sem,Summer',
            'school_year'            => ['required', 'string', 'max:20', 'regex:/^\d{4}-\d{4}$/'],
            // ── Fee breakdown ──────────────────────────────────────────────
            'fee_items'              => 'required|array|min:1',
            'fee_items.*.category'   => 'required|string|in:Tuition,Laboratory,Miscellaneous,Other',
            'fee_items.*.name'       => 'required|string|max:100',
            'fee_items.*.amount'     => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $user = User::where('role', 'student')->findOrFail($userId);

            // ── 1. Update users table ──────────────────────────────────────
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

            // ── 2. Sync students pivot table ───────────────────────────────
            if ($user->student) {
                $user->student->update([
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
            }

            // ── 3. Update the active assessment ───────────────────────────
            $assessment = StudentAssessment::where('user_id', $userId)
                ->where('status', 'active')
                ->latest()
                ->firstOrFail();

            $feeItems     = collect($validated['fee_items']);
            $tuitionTotal = round($feeItems->where('category', 'Tuition')->sum('amount'), 2);
            $otherTotal   = round($feeItems->whereNotIn('category', ['Tuition'])->sum('amount'), 2);
            $grandTotal   = round($tuitionTotal + $otherTotal, 2);

            $assessment->update([
                'year_level'       => $validated['year_level'],
                'semester'         => $validated['semester'],
                'school_year'      => $validated['school_year'],
                'subjects'         => [],
                'fee_breakdown'    => $feeItems->map(fn($item) => [
                    'category' => $item['category'],
                    'name'     => $item['name'],
                    'amount'   => (float) $item['amount'],
                ])->values()->toArray(),
                'tuition_fee'      => $tuitionTotal,
                'other_fees'       => $otherTotal,
                'total_assessment' => $grandTotal,
            ]);

            // ── 4. Recalculate payment terms if total changed ──────────────
            // Rescale each term's amount + balance proportionally so they
            // still add up to the new grand total.
            $terms = $assessment->paymentTerms()->orderBy('term_order')->get();
            if ($terms->isNotEmpty()) {
                $oldTotal     = $terms->sum('amount');
                $scaleFactor  = $oldTotal > 0 ? ($grandTotal / $oldTotal) : 1;
                $runningTotal = 0;
                $lastIdx      = $terms->count() - 1;

                foreach ($terms as $i => $term) {
                    if ($i === $lastIdx) {
                        // Last term absorbs rounding remainder
                        $newAmount = round($grandTotal - $runningTotal, 2);
                    } else {
                        $newAmount = round($term->amount * $scaleFactor, 2);
                        $runningTotal += $newAmount;
                    }

                    // Preserve any already-paid portion; only rescale the balance
                    $alreadyPaid = $term->amount - $term->balance;
                    $newBalance  = max(0, round($newAmount - $alreadyPaid, 2));

                    $term->update([
                        'amount'  => $newAmount,
                        'balance' => $newBalance,
                    ]);
                }
            }

            // ── 5. Recalculate account balance ────────────────────────────
            \App\Services\AccountService::recalculate($user);

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
            ->where('role', 'student')
            ->findOrFail($userId);

        $assessmentId   = request('assessment_id');
        $semesterFilter = request('semester');

        $assessmentQuery = StudentAssessment::where('user_id', $userId)
            ->where('status', 'active')
            ->with(['paymentTerms' => fn($q) => $q->orderBy('term_order')]);

        if ($assessmentId) {
            $assessmentQuery->where('id', $assessmentId);
        } elseif ($semesterFilter) {
            $assessmentQuery->where('semester', $semesterFilter);
        }

        $assessment = $assessmentQuery->latest()->firstOrFail();

        // ── Derive the start-year integer from school_year (e.g. "2025-2026" → 2025) ──
        $schoolYearStart = (int) explode('-', $assessment->school_year)[0];

        // ── Scope transactions to THIS assessment only ────────────────────────
        // Match on both year AND semester so 1st Sem 2025-2026 and 2nd Sem 2025-2026
        // are never mixed together in the same PDF.
        $transactions = Transaction::where('user_id', $userId)
            ->where(function ($q) use ($assessment, $schoolYearStart) {
                // Primary: explicit year + semester match (set since Bug #4 fix)
                $q->where(function ($inner) use ($assessment, $schoolYearStart) {
                    $inner->where('year', (string) $schoolYearStart)
                          ->where('semester', $assessment->semester);
                })
                // Secondary: meta contains the assessment_id (charge transaction created on assessment creation)
                ->orWhere(function ($inner) use ($assessment) {
                    $inner->whereJsonContains('meta->assessment_id', $assessment->id);
                });
            })
            ->with('fee')
            ->orderBy('created_at', 'desc')
            ->get();

        // ── Scope payments to THIS assessment only ────────────────────────────
        // After migration 2026_03_10_000001, new payments carry student_assessment_id.
        // For legacy payments (no assessment_id), fall back to matching by reference
        // against transactions that belong to this assessment.
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

    // =========================================================================
    // CREATE STUDENT / STORE STUDENT
    // =========================================================================

    public function createStudent()
    {
        $courses = User::where('role', 'student')
            ->whereNotNull('course')
            ->distinct()
            ->pluck('course')
            ->sort()
            ->values();

        if ($courses->isEmpty()) {
            $courses = collect(array_keys(self::COURSE_FEE_PRESETS));
        }

        return Inertia::render('StudentFees/CreateStudent', [
            'courses'    => $courses,
            'yearLevels' => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
        ]);
    }

    public function storeStudent(Request $request)
    {
        $validated = $request->validate([
            'last_name'      => 'required|string|max:255',
            'first_name'     => 'required|string|max:255',
            'middle_initial' => 'nullable|string|max:10',
            'email'          => 'required|email|unique:users,email|unique:students,email',
            'birthday'       => 'required|date',
            'phone'          => 'required|string|max:20',
            'address'        => 'required|string|max:255',
            'year_level'     => 'required|string',
            'course'         => 'required|string',
            'account_id'     => 'nullable|string|unique:users,account_id',
        ]);

        DB::beginTransaction();
        try {
            // Generate unique student_id in format: YYYY-NNNN
            $currentYear = date('Y');
            $randomNum = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $studentId = "{$currentYear}-{$randomNum}";
            
            // Ensure studentId is unique
            while (Student::where('student_id', $studentId)->exists()) {
                $randomNum = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $studentId = "{$currentYear}-{$randomNum}";
            }

            // Generate unique account_id
            $accountId = $this->generateUniqueAccountId();

            $user = User::create([
                'last_name'      => $validated['last_name'],
                'first_name'     => $validated['first_name'],
                'middle_initial' => $validated['middle_initial'] ?? null,
                'email'          => $validated['email'],
                'birthday'       => $validated['birthday'],
                'phone'          => $validated['phone'],
                'address'        => $validated['address'],
                'year_level'     => $validated['year_level'],
                'course'         => $validated['course'],
                'account_id'     => $accountId,
                'role'           => 'student',
                'is_active'      => true,
                'status'         => User::STATUS_ACTIVE,
                'email_verified_at' => now(),
                'password'       => Hash::make('password'),
            ]);

            // Create Student record with all required fields
            Student::create([
                'user_id'        => $user->id,
                'student_id'     => $studentId,
                'last_name'      => $validated['last_name'],
                'first_name'     => $validated['first_name'],
                'middle_initial' => $validated['middle_initial'] ?? null,
                'email'          => $validated['email'],
                'phone'          => $validated['phone'],
                'address'        => $validated['address'],
                'birthday'       => $validated['birthday'],
                'course'         => $validated['course'],
                'year_level'     => $validated['year_level'],
            ]);

            DB::commit();

            // Refresh the user from DB to ensure account_id is loaded
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

    /**
     * Create the 5 standard payment terms for a newly created assessment.
     * Term percentages: 42.15 | 17.86 | 17.86 | 14.88 | 7.25
     */
    private function createPaymentTerms(StudentAssessment $assessment, int $userId, float $total): void
    {
        $termDefs = [
            1 => ['name' => 'Upon Registration', 'percentage' => 42.15],
            2 => ['name' => 'Prelim',            'percentage' => 17.86],
            3 => ['name' => 'Midterm',           'percentage' => 17.86],
            4 => ['name' => 'Semi-Final',        'percentage' => 14.88],
            5 => ['name' => 'Final',             'percentage' =>  7.25],
        ];

        $allocated = 0.00;
        $lastOrder = 5;

        foreach ($termDefs as $order => $def) {
            $amount = ($order === $lastOrder)
                ? round($total - $allocated, 2)
                : round(($def['percentage'] / 100) * $total, 2);

            if ($order !== $lastOrder) {
                $allocated += $amount;
            }

            StudentPaymentTerm::create([
                'student_assessment_id'  => $assessment->id,
                'user_id'                => $userId,
                'term_name'              => $def['name'],
                'term_order'             => $order,
                'percentage'             => $def['percentage'],
                'amount'                 => $amount,
                'balance'                => $amount,
                'due_date'               => null, // Admin sets due dates via Payment Terms Management
                'status'                 => StudentPaymentTerm::STATUS_PENDING,
                'remarks'                => null,
                'paid_date'              => null,
                'carryover_from_term_id' => null,
                'carryover_amount'       => 0.00,
            ]);
        }
    }

    /**
     * Generate a unique account ID in format: STU-NNNNN
     */
    private function generateUniqueAccountId(): string
    {
        do {
            $accountId = 'STU-' . str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT);
        } while (User::where('account_id', $accountId)->exists());

        return $accountId;
    }
}