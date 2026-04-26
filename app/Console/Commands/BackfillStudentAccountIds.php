<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillStudentAccountIds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'students:backfill-account-ids';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Generate and assign Account IDs to students who are missing them';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting backfill of student Account IDs...');

        // Find all student users without account_id
        $studentsWithoutAccountId = User::where('role', 'student')
            ->whereNull('account_id')
            ->orderBy('created_at')
            ->get();

        $count = $studentsWithoutAccountId->count();

        if ($count === 0) {
            $this->info('✓ All students already have Account IDs.');
            return self::SUCCESS;
        }

        $this->info("Found {$count} student(s) without Account ID. Generating...");

        $progressBar = $this->output->createProgressBar($count);
        $progressBar->start();

        $generated = 0;
        $skipped = 0;

        foreach ($studentsWithoutAccountId as $user) {
            try {
                // Generate unique account_id
                $accountId = $this->generateUniqueAccountId();

                $user->update(['account_id' => $accountId]);

                $generated++;
                $this->line("\n✓ {$user->first_name} {$user->last_name} → {$accountId}");
            } catch (\Exception $e) {
                $skipped++;
                $this->line("\n✗ Failed to update user ID {$user->id}: {$e->getMessage()}");
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("✓ Backfill complete: {$generated} assigned, {$skipped} failed.");

        return self::SUCCESS;
    }

    /**
     * Generate a unique account ID in format: STU-NNNNN
     */
    private function generateUniqueAccountId(): string
    {
        $maxAttempts = 100;
        $attempts = 0;

        do {
            $accountId = 'STU-' . str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT);
            $attempts++;

            if ($attempts > $maxAttempts) {
                throw new \Exception('Could not generate unique Account ID after ' . $maxAttempts . ' attempts');
            }
        } while (User::where('account_id', $accountId)->exists());

        return $accountId;
    }
}
