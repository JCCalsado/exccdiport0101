<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\Workflow;
use App\Services\WorkflowService;
use Illuminate\Console\Command;

class BackfillPaymentApprovals extends Command
{
    protected $signature = 'backfill:payment-approvals {--dry-run}';
    protected $description = 'Backfill orphaned awaiting_approval transactions with workflow approvals';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info('');
        $this->info('=== Backfill Payment Approval Workflow ===');
        $this->info('');

        // Find transactions without workflow instances
        $orphaned = Transaction::where('status', 'awaiting_approval')
            ->whereDoesntHave('workflowInstances')
            ->get();

        if ($orphaned->isEmpty()) {
            $this->info('✅ No orphaned transactions found. All awaiting_approval payments have workflows.');
            $this->info('');
            return 0;
        }

        $this->warn("Found {$orphaned->count()} orphaned transaction(s)");
        $this->info('');

        // Get the payment_approval workflow
        $workflow = Workflow::where('type', 'payment_approval')->first();
        if (!$workflow) {
            $this->error('❌ Payment approval workflow not found!');
            $this->line('   Run: php artisan db:seed --class=PaymentApprovalWorkflowSeeder');
            return 1;
        }

        $this->info("Using workflow: {$workflow->name} (ID: {$workflow->id})");
        $this->info('');

        $success = 0;
        $failed = 0;

        $workflowService = app(WorkflowService::class);

        foreach ($orphaned as $transaction) {
            try {
                $this->line("Processing Transaction #{$transaction->id} | Amount: \${$transaction->amount}");

                if (!$dryRun) {
                    // Start the workflow for this transaction
                    $workflowService->startWorkflow($workflow, $transaction, $transaction->user_id);
                    $this->info("  ✅ Workflow created and approvals sent");
                } else {
                    $this->info("  [DRY RUN] Would create workflow");
                }

                $success++;
            } catch (\Exception $e) {
                $this->error("  ❌ Failed: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info('');
        $this->table(
            ['Result', 'Count'],
            [
                ['Processed', $success],
                ['Failed', $failed],
                ['Total', $success + $failed],
            ]
        );

        if ($dryRun) {
            $this->warn('');
            $this->warn('[DRY RUN MODE] — No changes were made');
            $this->line('Run without --dry-run to apply changes');
        }

        $this->info('');

        return $failed > 0 ? 1 : 0;
    }
}
