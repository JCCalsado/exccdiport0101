<?php

namespace App\Console\Commands;

use App\Models\StudentAssessment;
use App\Models\StudentPaymentTerm;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ConvertPaymentTermsToFive extends Command
{
    protected $signature = 'payment-terms:convert-to-five {--dry-run : Show what would be changed without making changes}';
    protected $description = 'Convert all student assessments to 5-term payment structure with specific percentages';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info('📋 Converting payment terms to 5-term structure...');
        $this->info('Percentages: Upon Registration (42.15%), Prelim (17.86%), Midterm (17.86%), Semi-Final (14.88%), Final (7.26%)');
        $this->newLine();

        if ($dryRun) {
            $this->warn('⚠️  DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Get all assessments
        $assessments = StudentAssessment::with('user')->get();

        if ($assessments->isEmpty()) {
            $this->error('No assessments found!');
            return;
        }

        $this->info('Found ' . $assessments->count() . ' assessments to process');
        $this->newLine();

        $bar = $this->output->createProgressBar($assessments->count());
        $termsCreated = 0;

        foreach ($assessments as $assessment) {
            $bar->advance();

            // Skip if no user
            if (!$assessment->user) {
                continue;
            }

            // Delete old payment terms (only if not dry-run)
            if (!$dryRun) {
                StudentPaymentTerm::where('student_assessment_id', $assessment->id)->delete();
            }

            // Define the 5 terms with percentages
            $terms = [
                [
                    'order' => 1,
                    'name' => 'Upon Registration',
                    'percentage' => 42.15,
                    'weeks' => 0, // At enrollment
                ],
                [
                    'order' => 2,
                    'name' => 'Prelim',
                    'percentage' => 17.86,
                    'weeks' => 6,
                ],
                [
                    'order' => 3,
                    'name' => 'Midterm',
                    'percentage' => 17.86,
                    'weeks' => 12,
                ],
                [
                    'order' => 4,
                    'name' => 'Semi-Final',
                    'percentage' => 14.88,
                    'weeks' => 15,
                ],
                [
                    'order' => 5,
                    'name' => 'Final',
                    'percentage' => 7.26,
                    'weeks' => 18,
                ],
            ];

            // Calculate due dates based on school year
            $dueDate = $this->calculateDueDate($assessment->school_year, 0);

            $totalAmount = (float) $assessment->total_assessment;
            $createdCount = 0;

            foreach ($terms as $index => $term) {
                // Calculate amount (with rounding on last term)
                if ($index === count($terms) - 1) {
                    // Last term gets the remainder to ensure accuracy
                    $amount = $totalAmount - array_sum(
                        array_slice(
                            array_map(
                                fn($t) => round($totalAmount * ($t['percentage'] / 100), 2),
                                array_slice($terms, 0, -1)
                            ),
                            0
                        )
                    );
                } else {
                    $amount = round($totalAmount * ($term['percentage'] / 100), 2);
                }

                $termDueDate = $this->calculateDueDate($assessment->school_year, $term['weeks']);

                $data = [
                    'student_assessment_id' => $assessment->id,
                    'user_id' => $assessment->user_id,
                    'term_name' => $term['name'],
                    'term_order' => $term['order'],
                    'percentage' => $term['percentage'],
                    'amount' => $amount,
                    'balance' => $amount,
                    'due_date' => $termDueDate,
                    'status' => 'pending',
                    'remarks' => null,
                ];

                if (!$dryRun) {
                    StudentPaymentTerm::create($data);
                }

                $createdCount++;
                $termsCreated++;
            }
        }

        $bar->finish();
        $this->newLine();
        $this->newLine();

        if ($dryRun) {
            $this->info('✅ DRY RUN COMPLETE - Would convert ' . $assessments->count() . ' assessments');
            $this->info('📊 Would create ' . $termsCreated . ' payment terms');
            $this->newLine();
            $this->line('Run without --dry-run flag to apply changes:');
            $this->line('php artisan payment-terms:convert-to-five');
        } else {
            $this->info('✅ Successfully converted ' . $assessments->count() . ' assessments to 5-term payment structure');
            $this->info('📊 Total payment terms created: ' . $termsCreated);
        }
    }

    /**
     * Calculate due date based on school year and weeks offset
     */
    protected function calculateDueDate(string $schoolYear, int $weeks): Carbon
    {
        // School year format: "2025-2026"
        $startYear = (int) explode('-', $schoolYear)[0];
        
        // Assume school starts around June 1st
        $enrollmentDate = Carbon::createFromDate($startYear, 6, 1);
        
        return $enrollmentDate->addWeeks($weeks);
    }
}
