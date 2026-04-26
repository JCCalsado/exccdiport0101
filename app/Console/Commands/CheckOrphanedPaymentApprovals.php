<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckOrphanedPaymentApprovals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'integrity:check-orphaned-payments {--alert : Send alert email to admin if orphaned payments found}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Find awaiting_approval payments without workflow instances and either start workflows or alert admin';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Checking for orphaned awaiting_approval transactions...');

        // Find all awaiting_approval payment transactions
        $orphanedPayments = Transaction::where('kind', 'payment')
            ->where('status', 'awaiting_approval')
            ->whereDoesntHave('workflowInstances') // No workflow instance for this transaction
            ->with('user')
            ->get();

        if ($orphanedPayments->isEmpty()) {
            $this->info('✅ No orphaned payments found. All awaiting_approval transactions have workflows.');
            return Command::SUCCESS;
        }

        $count = $orphanedPayments->count();
        $this->warn("⚠️  Found {$count} orphaned awaiting_approval transaction(s).");

        // Attempt to recover by starting workflows
        $recovered = 0;
        $failed = [];

        foreach ($orphanedPayments as $transaction) {
            try {
                $this->recoverPaymentWorkflow($transaction);
                $recovered++;
                $this->line("  ✅ Recovered: {$transaction->reference} for user #{$transaction->user_id}");
            } catch (\Exception $e) {
                $failed[] = [
                    'transaction_id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'user_id' => $transaction->user_id,
                    'error' => $e->getMessage(),
                ];
                $this->error("  ❌ Failed: {$transaction->reference} — {$e->getMessage()}");
            }
        }

        $this->line('');
        $this->info("Recovery Summary: {$recovered} recovered, " . count($failed) . ' failed.');

        // Alert admin if failures exist
        if (!empty($failed)) {
            if ($this->option('alert')) {
                $this->alertAdmin($failed, $recovered);
                $this->info('📧 Alert email sent to admin.');
            } else {
                $this->warn('Run with --alert flag to send alert email to admin.');
            }

            Log::warning('Orphaned payments integrity check failed', [
                'recovered' => $recovered,
                'failed_count' => count($failed),
                'failed_details' => $failed,
            ]);

            return Command::FAILURE;
        }

        Log::info('Orphaned payments integrity check completed', ['recovered' => $recovered]);
        return Command::SUCCESS;
    }

    /**
     * Start a payment approval workflow for an orphaned transaction.
     *
     * @throws \Exception if workflow cannot be started
     */
    private function recoverPaymentWorkflow(Transaction $transaction): void
    {
        $workflow = Workflow::active()
            ->where('type', 'payment_approval')
            ->first();

        if (!$workflow) {
            throw new \Exception(
                'No active payment_approval workflow found. ' .
                'Run: php artisan db:seed --class=PaymentApprovalWorkflowSeeder'
            );
        }

        // Create workflow instance for the orphaned transaction
        $instance = WorkflowInstance::create([
            'workflow_id' => $workflow->id,
            'workflowable_type' => Transaction::class,
            'workflowable_id' => $transaction->id,
            'status' => 'pending',
            'current_step' => $workflow->steps->first()?->order ?? 1,
        ]);

        // Create approval records based on workflow steps
        foreach ($workflow->steps as $step) {
            \App\Models\WorkflowApproval::create([
                'workflow_instance_id' => $instance->id,
                'workflow_step_id' => $step->id,
                'approver_id' => null, // Will be assigned by approval system
                'status' => 'pending',
                'approver_role' => $step->approver_role,
            ]);
        }
    }

    /**
     * Send alert email to admin about orphaned payments.
     */
    private function alertAdmin(array $failed, int $recovered): void
    {
        $adminEmail = config('mail.from.address');
        if (!$adminEmail) {
            $this->warn('No admin email configured in config/mail.php');
            return;
        }

        $subject = "⚠️ Payment Approval Integrity Alert — {$recovered} Recovered, " . count($failed) . ' Failed';
        $message = "Orphaned awaiting_approval transactions were found:\n\n";
        $message .= "Successfully Recovered: {$recovered}\n";
        $message .= "Failed to Recover: " . count($failed) . "\n\n";
        $message .= "Failed Transactions:\n";

        foreach ($failed as $item) {
            $message .= "  - Ref: {$item['reference']}, User: #{$item['user_id']}, Error: {$item['error']}\n";
        }

        $message .= "\nPlease review and take manual action for failed transactions.\n";
        $message .= "Check logs for full details.";

        // Simple email dispatch (adjust as needed for your mail driver)
        try {
            \Illuminate\Support\Facades\Mail::raw($message, function ($mail) use ($adminEmail, $subject) {
                $mail->to($adminEmail)->subject($subject);
            });
        } catch (\Exception $e) {
            Log::error('Failed to send orphaned payments alert email', ['error' => $e->getMessage()]);
        }
    }
}
