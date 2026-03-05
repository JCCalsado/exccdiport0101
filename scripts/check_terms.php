<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$termsCount = \App\Models\StudentPaymentTerm::where('user_id', 3)->count();
echo "Payment Terms for Student 3: $termsCount\n\n";

$terms = \App\Models\StudentPaymentTerm::where('user_id', 3)->orderBy('term_order')->get();
if ($terms->isNotEmpty()) {
  echo "Payment Terms Details:\n";
  foreach ($terms as $term) {
    echo "  Term: {$term->term_name} | Amount: " . number_format($term->amount, 2) . " | Balance: " . number_format($term->balance, 2) . " | Status: {$term->status}\n";
  }
} else {
  echo "No payment terms found!\n";
}
