<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\StudentPaymentTerm;

$terms = StudentPaymentTerm::orderBy('id')->limit(5)->get();

if ($terms->isEmpty()) {
    echo "No payment terms found\n";
} else {
    $total = 0;
    $assessment = null;
    
    foreach ($terms as $term) {
        if (!$assessment) {
            $assessment = $term->assessment;
        }
        echo "Term {$term->term_order} ({$term->term_name}): ₱" . number_format($term->amount, 2) . "\n";
        $total += $term->amount;
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "Calculated Total: ₱" . number_format($total, 2) . "\n";
    echo "Assessment Total: ₱" . number_format($assessment->total_assessment, 2) . "\n";
    echo "Difference: ₱" . number_format($total - $assessment->total_assessment, 2) . "\n";
    echo "Status: " . ($total == $assessment->total_assessment ? "✓ CORRECT" : "✗ INCORRECT") . "\n";
}
