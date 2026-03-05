<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

echo "=== TOTAL SUBJECTS IN DB ===\n";
$totalSubjects = \App\Models\Subject::count();
echo "Total: $totalSubjects\n\n";

echo "=== SUBJECT COUNT BY COURSE/YEAR ===\n";
$subjectCounts = \App\Models\Subject::select('course', 'year_level')
  ->groupBy('course', 'year_level')
  ->selectRaw('COUNT(*) as count')
  ->get()
  ->sortByDesc('count');

foreach ($subjectCounts as $combo) {
  echo "{$combo->course} / {$combo->year_level}: {$combo->count} subjects\n";
}

echo "\n=== CHECK WHAT COURSES/YEARS THE TEST STUDENT HAS ===\n";
$student3 = \App\Models\User::find(3);
echo "Student ID 3 enrolled in: {$student3->course} / {$student3->year_level}\n";

// Check what other students have this same course/year combo
$othersWithSameCourseYear = \App\Models\User::where('role', 'student')
  ->where('course', $student3->course)
  ->where('year_level', $student3->year_level)
  ->count();
echo "Other students with same course/year: $othersWithSameCourseYear\n";

// Let's check one that HAS assessment
echo "\n=== SAMPLE STUDENT WITH ASSESSMENT ===\n";
$userWithAssessment = \App\Models\StudentAssessment::first()->user;
echo "User ID: {$userWithAssessment->id}, Email: {$userWithAssessment->email}\n";
echo "Course: {$userWithAssessment->course}, Year: {$userWithAssessment->year_level}\n";

$subjectsForThat = \App\Models\Subject::where('course', $userWithAssessment->course)
  ->where('year_level', $userWithAssessment->year_level)
  ->count();
echo "Subjects available: $subjectsForThat\n";
?>
