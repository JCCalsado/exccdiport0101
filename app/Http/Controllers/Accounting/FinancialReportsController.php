<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\StudentAssessment;
use App\Models\StudentPaymentTerm;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class FinancialReportsController extends Controller
{
    /**
     * Display financial reports dashboard.
     *
     * FIXES APPLIED:
     *  1. totalPaid — now scoped to semester via assessment relationship
     *  2. byMonthSummary — now scoped to semester via assessment relationship
     *  3. paymentMethods — now scoped to semester via assessment relationship
     *  4. outstandingStudents — now grouped by assessment (not PaymentTerm),
     *     eliminating duplicate rows per student
     */
    public function index(Request $request)
    {
        $schoolYear = $request->get('school_year', now()->year . '-' . (now()->year + 1));
        $semester   = $request->get('semester', '1st Sem');

        // ------------------------------------------------------------------
        // Summary stats
        // ------------------------------------------------------------------

        $totalAssessments = StudentAssessment::where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->count();

        $totalAssessmentAmount = StudentAssessment::where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->sum('total_assessment');

        // FIX 1: totalPaid was using whereYear() only — ignored semester.
        // Now scoped through assessment to match school_year + semester.
        $totalPaid = Payment::where('status', 'completed')
            ->whereHas('assessment', function ($q) use ($schoolYear, $semester) {
                $q->where('school_year', $schoolYear)
                  ->where('semester', $semester);
            })
            ->sum('amount');

        $totalOutstanding = StudentPaymentTerm::whereHas('assessment', function ($q) use ($schoolYear, $semester) {
            $q->where('school_year', $schoolYear)->where('semester', $semester);
        })
            ->where('status', 'pending')
            ->sum('balance');

        // ------------------------------------------------------------------
        // Charts data
        // ------------------------------------------------------------------

        $byCourseSummary = StudentAssessment::where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->selectRaw('course, COUNT(*) as student_count, SUM(total_assessment) as total')
            ->groupBy('course')
            ->orderBy('total', 'desc')
            ->get();

        // FIX 2: byMonthSummary was using whereYear() only — ignored semester.
        // Now scoped through assessment.
        $byMonthSummary = Payment::where('status', 'completed')
            ->whereHas('assessment', function ($q) use ($schoolYear, $semester) {
                $q->where('school_year', $schoolYear)
                  ->where('semester', $semester);
            })
            ->selectRaw('MONTH(created_at) as month, SUM(amount) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn($item) => [
                'month' => Carbon::createFromFormat('m', $item->month)->format('M'),
                'total' => $item->total,
            ]);

        // ------------------------------------------------------------------
        // Payment method breakdown
        // ------------------------------------------------------------------

        // FIX 3: paymentMethods had NO filters at all — showed all-time totals
        // regardless of school year or semester. Now properly scoped.
        $paymentMethods = Payment::where('status', 'completed')
            ->whereHas('assessment', function ($q) use ($schoolYear, $semester) {
                $q->where('school_year', $schoolYear)
                  ->where('semester', $semester);
            })
            ->selectRaw("COALESCE(payment_method, 'Unspecified') as method, COUNT(*) as count, SUM(amount) as total")
            ->groupBy('payment_method')
            ->orderByDesc('total')
            ->get();

        // ------------------------------------------------------------------
        // Outstanding balances table
        // ------------------------------------------------------------------

        // FIX 4: Was querying StudentPaymentTerm directly, which returns one row
        // PER PAYMENT TERM — causing duplicate student rows (e.g. Domasian x3).
        // Now grouped at the StudentAssessment level so each student = one row.
        $outstandingStudents = StudentAssessment::where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->with(['student.user', 'paymentTerms'])
            ->get()
            ->map(function ($assessment) {
                $pendingBalance = $assessment->paymentTerms
                    ->where('status', 'pending')
                    ->sum('balance');

                return [
                    'accountId'   => $assessment->student?->user?->account_id ?? 'N/A',
                    'studentName' => $assessment->student?->user?->name ?? 'Unknown Student',
                    'course'      => $assessment->course,
                    'total'       => $assessment->total_assessment,
                    'balance'     => $pendingBalance,
                    'status'      => $pendingBalance > 0 ? 'Pending' : 'Paid',
                ];
            })
            ->filter(fn($s) => $s['balance'] > 0)  // only students with debt
            ->sortByDesc('balance')                 // highest balance first
            ->take(20)
            ->values();

        $schoolYears = $this->getSchoolYears();
        $semesters   = ['1st Sem', '2nd Sem', 'Summer'];

        return Inertia::render('Accounting/FinancialReports', [
            'summary' => [
                'totalAssessments'     => $totalAssessments,
                'totalAssessmentAmount'=> $totalAssessmentAmount,
                'totalPaid'            => $totalPaid,
                'totalOutstanding'     => $totalOutstanding,
            ],
            'charts' => [
                'byCourse' => $byCourseSummary,
                'byMonth'  => $byMonthSummary,
            ],
            'paymentMethods'    => $paymentMethods,
            'outstandingStudents' => $outstandingStudents,
            'filters' => [
                'schoolYear' => $schoolYear,
                'semester'   => $semester,
            ],
            'schoolYears' => $schoolYears,
            'semesters'   => $semesters,
        ]);
    }

    /**
     * Export financial report as PDF.
     *
     * FIXES APPLIED:
     *  1. totalPaid — now scoped to semester (same fix as index)
     *  2. $students — now filtered to balance > 0, sorted by balance desc,
     *     limited to top 20 — previously passed ALL students including fully paid
     */
    public function export(Request $request)
    {
        $schoolYear = $request->get('school_year', now()->year . '-' . (now()->year + 1));
        $semester   = $request->get('semester', '1st Sem');

        // ------------------------------------------------------------------
        // Summary
        // ------------------------------------------------------------------

        // FIX 1 (export): totalPaid scoped to semester via assessment relationship.
        $totalPaid = Payment::where('status', 'completed')
            ->whereHas('assessment', function ($q) use ($schoolYear, $semester) {
                $q->where('school_year', $schoolYear)
                  ->where('semester', $semester);
            })
            ->sum('amount');

        $totalAssessmentAmount = StudentAssessment::where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->sum('total_assessment');

        $totalOutstanding = StudentPaymentTerm::whereHas('assessment', function ($q) use ($schoolYear, $semester) {
            $q->where('school_year', $schoolYear)->where('semester', $semester);
        })
            ->where('status', 'pending')
            ->sum('balance');

        $summary = [
            'totalAssessments'      => StudentAssessment::where('school_year', $schoolYear)
                ->where('semester', $semester)
                ->count(),
            'totalAssessmentAmount' => $totalAssessmentAmount,
            'totalPaid'             => $totalPaid,
            'totalOutstanding'      => $totalOutstanding,
        ];

        // ------------------------------------------------------------------
        // Student list for PDF
        // ------------------------------------------------------------------

        // FIX 2 (export): Was fetching ALL students in the semester — including
        // fully-paid — with no balance filter, no sort, no limit.
        // Now: only outstanding students, sorted by balance desc, top 20.
        $students = StudentAssessment::where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->with(['student.user', 'paymentTerms'])
            ->get()
            ->map(function ($assessment) {
                $balance = $assessment->paymentTerms->sum('balance');
                $paid    = $assessment->total_assessment - $balance;

                return [
                    'accountId'   => $assessment->student?->user?->account_id ?? 'N/A',
                    'studentName' => $assessment->student?->user?->name ?? 'Unknown Student',
                    'course'      => $assessment->course,
                    'total'       => $assessment->total_assessment,
                    'paid'        => $paid,
                    'balance'     => $balance,
                    'status'      => $balance > 0 ? 'Pending' : 'Paid',
                ];
            })
            ->filter(fn($s) => $s['balance'] > 0)  // only students with debt
            ->sortByDesc('balance')                 // highest outstanding first
            ->take(20)
            ->values();

        $pdf = Pdf::loadView('pdf.financial-report', [
            'schoolYear'  => $schoolYear,
            'semester'    => $semester,
            'summary'     => $summary,
            'students'    => $students,
            'generatedAt' => now(),
        ]);

        $filename = 'financial-report-' . $schoolYear . '-' . str_replace(' ', '-', $semester) . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Get list of available school years.
     */
    private function getSchoolYears(): array
    {
        $years       = [];
        $currentYear = now()->year;

        for ($i = $currentYear - 3; $i <= $currentYear + 2; $i++) {
            $years[] = "{$i}-" . ($i + 1);
        }

        return $years;
    }
}