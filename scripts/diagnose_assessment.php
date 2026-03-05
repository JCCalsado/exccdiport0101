<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

// Check which students have assessments
echo "=== STUDENTS WITH ASSESSMENTS ===\n";
$assessmentsGrouped = \App\Models\StudentAssessment::with('user')
  ->latest('created_at')
  ->take(10)
  ->get()
  ->groupBy('user_id');

foreach ($assessmentsGrouped as $userId => $userAssessments) {
  $user = \App\Models\User::find($userId);
  echo "User ID $userId ({$user->email}): " . count($userAssessments) . " assessment(s)\n";
}

echo "\n=== CHECK STUDENT ID 3 ===\n";
$student3 = \App\Models\User::find(3);
echo "Email: {$student3->email}\n";
echo "Course: {$student3->course}\n";
echo "Year Level: {$student3->year_level}\n";

$assessment = \App\Models\StudentAssessment::where('user_id', 3)->first();
if ($assessment) {
  echo "Has Assessment: YES\n";
} else {
  echo "Has Assessment: NO\n";
}

// Check if there are any subjects for this student
$subjects = \App\Models\Subject::where('course', $student3->course)
  ->where('year_level', $student3->year_level)
  ->count();
echo "Subjects matching {$student3->course} / Year {$student3->year_level}: $subjects\n";
?>
