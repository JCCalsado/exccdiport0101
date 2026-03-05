<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Notification;
use App\Models\StudentAssessment;

// Boot the application
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Find a student user (assuming role = 'student')
$student = \App\Models\User::where('role', 'student')->first();

if (!$student) {
    echo "No student user found\n";
    exit(1);
}

echo "=== Checking notifications for student: " . $student->email . " ===\n\n";

// Step 1: Check database queries
echo "Step 1: Query for active notifications\n";
$query1 = Notification::where('is_active', true)
    ->where(function ($query) use ($student) {
        $query->where('target_role', $student->role)
            ->orWhere('target_role', 'all')
            ->orWhere('user_id', $student->id);
    })
    ->where(function ($query) {
        $query->whereNull('start_date')
            ->orWhere('start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            });
    });

echo "Query SQL: " . $query1->toSql() . "\n";
echo "Bindings: " . json_encode($query1->getBindings()) . "\n";

$notificationsCollection = $query1->orderBy('created_at', 'desc')->get();
echo "Found " . count($notificationsCollection) . " notifications\n\n";

echo "Step 2: Check each notification\n";
foreach ($notificationsCollection as $notif) {
    echo "\n[" . $notif->id . "] " . $notif->title . "\n";
    echo "  is_active: " . ($notif->is_active ? 'YES' : 'NO') . "\n";
    echo "  start_date: " . ($notif->start_date ? $notif->start_date->format('Y-m-d') : 'NULL') . "\n";
    echo "  end_date: " . ($notif->end_date ? $notif->end_date->format('Y-m-d') : 'NULL') . "\n";
    echo "  target_role: " . $notif->target_role . "\n";
    echo "  term_ids: " . json_encode($notif->term_ids) . " (type: " . gettype($notif->term_ids) . ")\n";
    echo "  target_term_name: " . json_encode($notif->target_term_name) . "\n";
    echo "  trigger_days_before_due: " . json_encode($notif->trigger_days_before_due) . "\n";
    
    // Check if this is "Early Payment" notification
    if (strpos(strtolower($notif->title), 'early') !== false) {
        echo "  >>> THIS IS THE EARLY PAYMENT NOTIFICATION <<<\n";
        
        echo "\n  Checking filter logic:\n";
        $hasTermFilter = !empty($notif->term_ids) && !empty($notif->target_term_name);
        echo "    has_term_filter: " . ($hasTermFilter ? 'YES' : 'NO') . "\n";
        
        if ($hasTermFilter) {
            // Get payment terms
            $latestAssessment = StudentAssessment::where('user_id', $student->id)
                ->with('paymentTerms')
                ->latest('created_at')
                ->first();
            
            $paymentTerms = [];
            if ($latestAssessment) {
                $paymentTerms = $latestAssessment->paymentTerms()
                    ->orderBy('term_order')
                    ->get()
                    ->toArray();
            }
            
            echo "    student_has_payment_terms: " . (count($paymentTerms) > 0 ? 'YES' : 'NO') . "\n";
            if (count($paymentTerms) > 0) {
                echo "    payment_terms_count: " . count($paymentTerms) . "\n";
            }
        }
    }
}

echo "\n\nNow searching for 'Early' notification...\n";
$earlyNotif = Notification::where('title', 'like', '%Early%')->first();
if ($earlyNotif) {
    echo "Found: " . $earlyNotif->title . "\n";
    echo json_encode($earlyNotif->toArray(), JSON_PRETTY_PRINT) . "\n";
} else {
    echo "NOT FOUND\n";
}
