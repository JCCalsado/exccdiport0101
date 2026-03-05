<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);

use Illuminate\Support\Facades\DB;

echo "\n=== PAYMENT TERMS VERIFICATION ===\n\n";

$assessments = DB::table('student_assessments')->limit(5)->get();

foreach ($assessments as $a) {
    $terms = DB::table('student_payment_terms')
        ->where('student_assessment_id', $a->id)
        ->orderBy('term_order')
        ->get();
    
    $sumAmounts = $terms->sum('amount');
    $sumBalances = $terms->sum('balance');
    
    $match = abs($sumAmounts - $a->total_assessment) < 0.01 ? '✓' : '✗';
    
    echo "Assessment #{$a->id} ({$a->user_id}): ";
    echo "Total={$a->total_assessment}, ";
    echo "Sum={$sumAmounts}, ";
    echo "Balance={$sumBalances} {$match}\n";
    
    if (abs($sumAmounts - $a->total_assessment) >= 0.01) {
        echo "  Terms: ";
        foreach ($terms as $t) {
            echo $t->amount . " ";
        }
        echo "\n";
    }
}

echo "\n";
