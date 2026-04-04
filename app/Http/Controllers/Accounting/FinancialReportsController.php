<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\StudentAssessment;
use App\Models\StudentPaymentTerm;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class FinancialReportsController extends Controller
{
    /**
     * Display financial reports dashboard.
     */
    public function index(Request $request)
    {
        $schoolYear = $request->get('school_year', now()->year . '-' . (now()->year + 1));
        $semester = $request->get('semester', '1st Sem');

        // Summary stats
        $totalAssessments = StudentAssessment::where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->count();

        $totalAssessmentAmount = StudentAssessment::where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->sum('total_assessment');

        $totalPaid = Transaction::where('kind', 'payment')
            ->where('status', 'paid')
            ->whereYear('created_at', intval(explode('-', $schoolYear)[0]))
            ->sum('amount');

        $totalOutstanding = StudentPaymentTerm::whereHas('assessment', function ($q) use ($schoolYear, $semester) {
            $q->where('school_year', $schoolYear)->where('semester', $semester);
        })
            ->where('status', 'pending')
            ->sum('balance');

        // Charts data
        $byCourseSummary = StudentAssessment::where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->selectRaw('course, COUNT(*) as student_count, SUM(total_assessment) as total')
            ->groupBy('course')
            ->orderBy('total', 'desc')
            ->get();

        $byMonthSummary = Transaction::where('kind', 'payment')
            ->where('status', 'paid')
            ->whereYear('created_at', intval(explode('-', $schoolYear)[0]))
            ->selectRaw('MONTH(created_at) as month, SUM(amount) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn($item) => [
                'month' => Carbon::createFromFormat('m', $item->month)->format('M'),
                'total' => $item->total,
            ]);

        // Payment method breakdown
        $paymentMethods = Transaction::where('kind', 'payment')
            ->where('status', 'paid')
            ->selectRaw("COALESCE(payment_channel, 'Unspecified') as method, COUNT(*) as count, SUM(amount) as total")
            ->groupBy('payment_channel')
            ->orderByDesc('total')
            ->get();

        // Outstanding balances table
        $outstandingStudents = StudentPaymentTerm::whereHas('assessment', function ($q) use ($schoolYear, $semester) {
            $q->where('school_year', $schoolYear)->where('semester', $semester);
        })
            ->where('status', 'pending')
            ->with(['assessment.student.user'])
            ->orderByDesc('balance')
            ->limit(20)
            ->get();

        $schoolYears = $this->getSchoolYears();
        $semesters = ['1st Sem', '2nd Sem', 'Summer'];

        return Inertia::render('Accounting/FinancialReports', [
            'summary' => [
                'totalAssessments' => $totalAssessments,
                'totalAssessmentAmount' => $totalAssessmentAmount,
                'totalPaid' => $totalPaid,
                'totalOutstanding' => $totalOutstanding,
            ],
            'charts' => [
                'byCourse' => $byCourseSummary,
                'byMonth' => $byMonthSummary,
            ],
            'paymentMethods' => $paymentMethods,
            'outstandingStudents' => $outstandingStudents,
            'filters' => [
                'schoolYear' => $schoolYear,
                'semester' => $semester,
            ],
            'schoolYears' => $schoolYears,
            'semesters' => $semesters,
        ]);
    }

    /**
     * Export financial report as PDF.
     */
    public function export(Request $request)
    {
        $schoolYear = $request->get('school_year', now()->year . '-' . (now()->year + 1));
        $semester = $request->get('semester', '1st Sem');

        // Get summary data
        $summary = [
            'totalAssessments' => StudentAssessment::where('school_year', $schoolYear)
                ->where('semester', $semester)
                ->count(),
            'totalAssessmentAmount' => StudentAssessment::where('school_year', $schoolYear)
                ->where('semester', $semester)
                ->sum('total_assessment'),
            'totalPaid' => Transaction::where('kind', 'payment')
                ->where('status', 'paid')
                ->whereYear('created_at', intval(explode('-', $schoolYear)[0]))
                ->sum('amount'),
            'totalOutstanding' => StudentPaymentTerm::whereHas('assessment', function ($q) use ($schoolYear, $semester) {
                $q->where('school_year', $schoolYear)->where('semester', $semester);
            })
                ->where('status', 'pending')
                ->sum('balance'),
        ];

        // Get student details
        $students = StudentAssessment::where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->with(['student.user', 'paymentTerms'])
            ->orderBy('course')
            ->get()
            ->map(function ($assessment) {
                $balance = $assessment->paymentTerms->sum('balance');
                $paid = $assessment->total_assessment - $balance;

                return [
                    'accountId' => $assessment->student?->user?->account_id ?? 'N/A',
                    'studentName' => $assessment->student?->user?->name ?? 'Unknown Student',
                    'course' => $assessment->course,
                    'total' => $assessment->total_assessment,
                    'paid' => $paid,
                    'balance' => $balance,
                    'status' => $balance > 0 ? 'Pending' : 'Paid',
                ];
            });

        $pdf = Pdf::loadView('pdf.financial-report', [
            'schoolYear' => $schoolYear,
            'semester' => $semester,
            'summary' => $summary,
            'students' => $students,
            'generatedAt' => now(),
        ]);

        return $pdf->download("financial-report-{$schoolYear}-{$semester}.pdf");
    }

    /**
     * Get list of available school years.
     */
    private function getSchoolYears(): array
    {
        $years = [];
        $currentYear = now()->year;
        for ($i = $currentYear - 3; $i <= $currentYear + 2; $i++) {
            $years[] = "{$i}-" . ($i + 1);
        }

        return $years;
    }
}
