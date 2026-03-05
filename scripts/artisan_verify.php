#!/usr/bin/env php
<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n========== PAYMENT TERMS VERIFICATION ==========\n";

// Check total counts
$assessmentCount = DB::table('student_assessments')->count();
$termsCount = DB::table('student_payment_terms')->count();

echo "Total assessments: $assessmentCount\n";
echo "Total payment terms: $termsCount\n\n";

// Get assessments WITH their payment terms
$assessments = DB::table('student_assessments as sa')
    ->join('users as u', 'sa.user_id', '=', 'u.id')
    ->select('sa.id', 'sa.user_id', 'sa.total_assessment', 
             DB::raw("CONCAT(u.last_name, ', ', u.first_name) as full_name"))
    ->limit(10)
    ->get();

foreach ($assessments as $a) {
    $terms = DB::table('student_payment_terms')
        ->where('student_assessment_id', $a->id)
        ->orderBy('term_order')
        ->get();
    
    $sum = round($terms->sum('amount'), 2);
    $balance = round($terms->sum('balance'), 2);
    $diff = abs($sum - $a->total_assessment);
    $status = $diff < 0.01 ? '✓ OK' : '✗ ERROR';
    
    echo "\n📊 {$a->full_name} (Assessment #{$a->id}):\n";
    echo "  Total Assessment: ₱" . number_format($a->total_assessment, 2) . "\n";
    echo "  Payment Terms Sum: ₱" . number_format($sum, 2) . "\n";
    echo "  Balance Sum: ₱" . number_format($balance, 2) . "\n";
    echo "  Discrepancy: ₱" . number_format($diff, 4) . " {$status}\n";
    
    if ($terms->count() > 0) {
        echo "  Terms: ";
        foreach ($terms as $t) {
            echo "₱" . number_format($t->amount, 2) . " ";
        }
        echo "\n";
    }
}

// Check for any remaining discrepancies
$discrepancies = DB::table('student_assessments as sa')
    ->select(
        'sa.id',
        'sa.total_assessment',
        DB::raw('ROUND(SUM(spt.amount), 2) as sum_amount')
    )
    ->leftJoin('student_payment_terms as spt', 'spt.student_assessment_id', '=', 'sa.id')
    ->groupBy('sa.id', 'sa.total_assessment')
    ->havingRaw('ABS(SUM(spt.amount) - sa.total_assessment) >= 0.01')
    ->get();

echo "\n========== DISCREPANCY SUMMARY ==========\n";
echo "Total assessments with rounding errors: " . count($discrepancies) . "\n";

if (count($discrepancies) > 0) {
    echo "⚠️ Found " . count($discrepancies) . " assessments with discrepancies:\n";
    foreach ($discrepancies as $d) {
        echo "  ID {$d->id}: Total={$d->total_assessment}, Sum={$d->sum_amount}, Diff=" 
            . number_format(abs($d->sum_amount - $d->total_assessment), 4) . "\n";
    }
} else {
    echo "✅ All assessments have correct payment term sums!\n";
}

echo "\n";
