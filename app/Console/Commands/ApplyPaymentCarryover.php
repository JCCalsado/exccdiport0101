<?php

namespace App\Console\Commands;

use App\Models\StudentAssessment;
use App\Services\PaymentCarryoverService;
use Illuminate\Console\Command;

class ApplyPaymentCarryover extends Command
{
    protected $signature = 'payment-terms:apply-carryover {--dry-run : Show what would be changed without making changes}';
    protected $description = 'Apply payment carryover logic - unpaid balances carry forward to next terms';

    protected PaymentCarryoverService $carryoverService;

    public function __construct()
    {
        parent::__construct();
        $this->carryoverService = new PaymentCarryoverService();
    }

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info('📋 Applying payment carryover logic...');
        $this->info('Unpaid balances will carry forward to the next term automatically.');
        $this->newLine();

        if ($dryRun) {
            $this->warn('⚠️  DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Get all assessments with payment terms
        $assessments = StudentAssessment::with('paymentTerms')->get();

        if ($assessments->isEmpty()) {
            $this->error('No assessments found!');
            return;
        }

        $this->info('Found ' . $assessments->count() . ' assessments to process');
        $this->newLine();

        $bar = $this->output->createProgressBar($assessments->count());
        $totalTermsProcessed = 0;
        $totalCarriedOver = 0.00;

        foreach ($assessments as $assessment) {
            $bar->advance();

            $terms = $assessment->paymentTerms()
                ->orderBy('term_order')
                ->get();

            if ($terms->isEmpty()) {
                continue;
            }

            if (!$dryRun) {
                $this->carryoverService->applyCarryoverToAssessment($assessment);
            }

            // Calculate carryover amounts for reporting
            foreach ($terms as $term) {
                $totalTermsProcessed++;
                if ($term->hasCarryover()) {
                    $totalCarriedOver += (float) $term->balance;
                }
            }
        }

        $bar->finish();
        $this->newLine();
        $this->newLine();

        if ($dryRun) {
            $this->info('✅ DRY RUN COMPLETE');
            $this->info('📊 Would process ' . $totalTermsProcessed . ' terms');
            $this->info('💰 Would carry over: ₱' . number_format($totalCarriedOver, 2));
            $this->newLine();
            $this->line('Run without --dry-run flag to apply changes:');
            $this->line('php artisan payment-terms:apply-carryover');
        } else {
            $this->info('✅ Successfully applied carryover logic to ' . $assessments->count() . ' assessments');
            $this->info('📊 Terms processed: ' . $totalTermsProcessed);
            $this->info('💰 Total balance carried over: ₱' . number_format($totalCarriedOver, 2));
        }
    }
}
