<?php

$totalAssessment = 15540;
$percentages = [42.15, 17.86, 17.86, 14.88, 7.25];
$amounts = [];
$total = 0;
$count = count($percentages);

echo "Testing rounding fix with total assessment: ₱" . number_format($totalAssessment, 2) . "\n\n";

foreach ($percentages as $key => $percentage) {
    if ($key === $count - 1) {
        // Last term: use remainder
        $amount = round($totalAssessment - $total, 2);
        echo "Term " . ($key + 1) . " (Final - remainder): ₱" . number_format($amount, 2) . "\n";
    } else {
        $amount = round($totalAssessment * ($percentage / 100), 2);
        echo "Term " . ($key + 1) . " (" . $percentage . "%): ₱" . number_format($amount, 2) . "\n";
        $total += $amount;
    }
    $amounts[] = $amount;
}

$sum = array_sum($amounts);
echo "\n" . str_repeat("=", 50) . "\n";
echo "Calculated Total: ₱" . number_format($sum, 2) . "\n";
echo "Assessment Total: ₱" . number_format($totalAssessment, 2) . "\n";
echo "Difference: ₱" . number_format($sum - $totalAssessment, 2) . "\n";
echo "Match: " . ($sum == $totalAssessment ? "✓ YES" : "✗ NO") . "\n";
