<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Enums\UserRoleEnum;

echo "=== SYSTEM STATUS ===\n\n";

// Count users by role
$students = \App\Models\User::where('role', UserRoleEnum::STUDENT)->count();
$accounting = \App\Models\User::where('role', UserRoleEnum::ACCOUNTING)->count();
$admin = \App\Models\User::where('role', UserRoleEnum::ADMIN)->count();

echo "Users:\n";
echo "  - Students: $students\n";
echo "  - Accounting: $accounting\n";
echo "  - Admin: $admin\n\n";

// Count assessments
$assessments = \App\Models\StudentAssessment::count();
echo "Student Assessments: $assessments\n\n";

// Count payment terms
$terms = \App\Models\StudentPaymentTerm::count();
echo "Payment Terms: $terms\n\n";

// Count transactions by status
$transactions = DB::table('transactions')
    ->selectRaw('kind, status, COUNT(*) as count')
    ->groupBy('kind', 'status')
    ->get();

echo "Transactions:\n";
foreach ($transactions as $tx) {
    echo "  - {$tx->kind} ({$tx->status}): {$tx->count}\n";
}

echo "\n";

// Check workflow status
$workflows = \App\Models\Workflow::all();
echo "Workflows:\n";
foreach ($workflows as $w) {
    echo "  - " . $w->type . ": " . ($w->is_active ? 'active' : 'inactive') . "\n";
}

echo "\n";

// Check approvals
$approvals = DB::table('workflow_approvals')
    ->selectRaw('approver_id, status, COUNT(*) as count')
    ->groupBy('approver_id', 'status')
    ->get();

echo "Workflow Approvals:\n";
foreach ($approvals as $a) {
    $user = \App\Models\User::find($a->approver_id);
   $role = $user ? $user->role->value : 'unknown';
    echo "  - User {$a->approver_id} ({$user?->email}, $role): {$a->status} ({$a->count})\n";
}

echo "\n✅ System snapshot captured\n";
