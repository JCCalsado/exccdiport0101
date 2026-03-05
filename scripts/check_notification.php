<?php
// Quick diagnostic script to check Early Payment notification

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$container = $app->make(Illuminate\Contracts\Container\Container::class);
$container->make(Illuminate\Contracts\Http\Kernel::class)->handle(
    $request = Illuminate\Http\Request::capture()
);

use App\Models\Notification;

$notification = Notification::where('title', 'like', '%Early%')->first();

if ($notification) {
    echo "=== Early Payment Notification Found ===\n";
    echo "ID: " . $notification->id . "\n";
    echo "Title: " . $notification->title . "\n";
    echo "is_active: " . ($notification->is_active ? 'YES' : 'NO') . "\n";
    echo "start_date: " . ($notification->start_date ? $notification->start_date->format('Y-m-d') : 'NULL') . "\n";
    echo "end_date: " . ($notification->end_date ? $notification->end_date->format('Y-m-d') : 'NULL') . "\n";
    echo "target_role: " . $notification->target_role . "\n";
    echo "user_id: " . ($notification->user_id ? $notification->user_id : 'NULL (all students)') . "\n";
    echo "term_ids: " . json_encode($notification->term_ids) . "\n";
    echo "target_term_name: " . ($notification->target_term_name ? $notification->target_term_name : 'NULL') . "\n";
    echo "trigger_days_before_due: " . ($notification->trigger_days_before_due ? $notification->trigger_days_before_due : 'NULL') . "\n";
    echo "\n=== Current Date ===\n";
    echo "Today: " . now()->format('Y-m-d H:i:s') . "\n";
    echo "\n=== Checking Date Range ===\n";
    echo "Today >= start_date: " . (now()->gte($notification->start_date) ? 'YES' : 'NO') . "\n";
    if ($notification->end_date) {
        echo "Today <= end_date: " . (now()->lte($notification->end_date) ? 'YES' : 'NO') . "\n";
    } else {
        echo "No end_date (ongoing)\n";
    }
} else {
    echo "No notification found with 'Early' in title\n";
    echo "\nAll notifications:\n";
    Notification::orderBy('id')->get(['id', 'title', 'is_active'])->each(function($n) {
        echo "  [" . ($n->is_active ? "ACTIVE" : "INACTIVE") . "] ID: " . $n->id . " - " . $n->title . "\n";
    });
}
