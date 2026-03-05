<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$student = \App\Models\User::find(3);
$student->year_level = '1st Year';
$student->save();

\App\Models\StudentAssessment::where('user_id', 3)->delete();

echo "Updated student 3 to 1st Year\n";
echo "Cleared old assessments\n";
