<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$user = \App\Models\User::where('email', 'student1@ccdi.edu.ph')->first();
if ($user) {
  echo 'ID: ' . $user->id . ', Role: ' . $user->role->value . ', Year: ' . $user->year_level . "\n";
} else {
  echo 'Not found';
}
?>
