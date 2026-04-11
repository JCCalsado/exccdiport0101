<?php
namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\StudentAssessment;
use App\Models\StudentEnrollment;
use App\Models\StudentPaymentTerm;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class StudentAccountController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $account = Account::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0]
        );

        $assessment = StudentAssessment::where('user_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        $allAssessments = StudentAssessment::where('user_id', $user->id)
            ->where('status', 'active')
            ->with(['paymentTerms' => fn($q) => $q->orderBy('term_order')])
            ->orderBy('school_year')
            ->get()
            ->map(fn($a) => [
                'id'               => $a->id,
                'assessment_number'=> $a->assessment_number,
                'year_level'       => $a->year_level,
                'semester'         => $a->semester,
                'school_year'      => $a->school_year,
                'course'           => $a->course,
                'total_assessment' => (float) $a->total_assessment,
                'tuition_fee'      => (float) $a->tuition_fee,
                'other_fees'       => (float) $a->other_fees,
                'fee_breakdown'    => $a->fee_breakdown ?? [],
                'status'           => $a->status,
                'created_at'       => $a->created_at,
            ]);

        $paymentTerms = $assessment
            ? StudentPaymentTerm::where('student_assessment_id', $assessment->id)
                ->orderBy('term_order')
                ->get()
            : collect();

        $transactions = Transaction::where('user_id', $user->id)
            ->where('kind', 'payment')
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate totalPaid for the current (latest) assessment only
        // This is the sum of all successful payment transactions (status='paid') 
        // for the current assessment, excluding charge transactions.
        $totalPaid = 0;
        if ($assessment) {
            $totalPaid = (float) $transactions
                ->where('status', 'paid')
                ->filter(function ($txn) use ($assessment) {
                    $assessmentId = data_get($txn->meta, 'assessment_id');
                    return $assessmentId === $assessment->id;
                })
                ->sum('amount');
        }

        $notifications = Notification::where(function($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('target_role', 'student');
            })
            ->where('is_active', true)
            ->whereNull('dismissed_at')
            ->get();

        // enrolledSubjectsByAssessment
        $assessmentTermIndex = $allAssessments->keyBy(
            fn($a) => $a['school_year'] . '||' . $a['semester']
        );

        $enrollmentRows = StudentEnrollment::where('user_id', $user->id)
            ->where('status', 'enrolled')
            ->get(['subject_id', 'school_year', 'semester']);

        $enrolledSubjectsByAssessment = [];
        foreach ($enrollmentRows as $row) {
            $termKey = $row->school_year . '||' . $row->semester;
            if (!isset($assessmentTermIndex[$termKey])) continue;
            $assessmentId = $assessmentTermIndex[$termKey]['id'];
            if (!isset($enrolledSubjectsByAssessment[$assessmentId])) {
                $enrolledSubjectsByAssessment[$assessmentId] = [];
            }
            $enrolledSubjectsByAssessment[$assessmentId][] = (int) $row->subject_id;
        }

        return Inertia::render('Student/AccountOverview', [
            'account'                      => $account,
            'transactions'                 => $transactions,
            'totalPaid'                    => $totalPaid,
            'fees'                         => [],
            'latestAssessment'             => $assessment,
            'allAssessments'               => $allAssessments,
            'paymentTerms'                 => $paymentTerms,
            'notifications'                => $notifications,
            'pendingApprovalPayments'      => [],
            'enrolledSubjectsByAssessment' => $enrolledSubjectsByAssessment,
        ]);
    }
}
