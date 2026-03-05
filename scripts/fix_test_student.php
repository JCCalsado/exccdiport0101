<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

// Update student ID 3 to 1st year so assessment seeder will include them
$student = \App\Models\User::find(3);
if ($student) {
  $student->year_level = '1st Year';
  $student->save();
  echo "✓ Updated student ID 3 (student1@ccdi.edu.ph) to 1st Year\n";
  
  // Delete any failed assessments
  \App\Models\StudentAssessment::where('user_id', 3)->delete();
  echo "✓ Cleared any existing assessments\n";
} else {
  echo "✗ Student ID 3 not found\n";
}
?>
