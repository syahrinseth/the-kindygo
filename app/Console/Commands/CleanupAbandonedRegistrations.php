<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\AbandonedRegistrationWarning;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupAbandonedRegistrations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'registrations:cleanup {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup abandoned registrations and send warning emails';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // Send warning emails to users at day 25 (5 days before deletion)
        $this->sendWarningEmails($dryRun);

        // Force delete users at day 30
        $this->deleteAbandonedRegistrations($dryRun);

        return Command::SUCCESS;
    }

    protected function sendWarningEmails(bool $dryRun): void
    {
        $this->info('Checking for registrations that need warning emails...');

        // Find users who registered 25 days ago and haven't completed registration
        $warningDate = now()->subDays(25)->startOfDay();

        $usersToWarn = User::where('registration_step', '<', 4)
            ->whereNotNull('registration_token')
            ->whereDate('created_at', '<=', $warningDate)
            ->whereDoesntHave('notifications', function ($query) {
                $query->where('type', AbandonedRegistrationWarning::class)
                    ->whereDate('created_at', '>=', now()->subDays(6));
            })
            ->get();

        if ($usersToWarn->isEmpty()) {
            $this->info('No users need warning emails.');

            return;
        }

        $this->info("Found {$usersToWarn->count()} users to warn.");

        foreach ($usersToWarn as $user) {
            if ($dryRun) {
                $this->line("[DRY RUN] Would send warning email to: {$user->email}");
            } else {
                try {
                    $user->notify(new AbandonedRegistrationWarning($user));
                    $this->line("Sent warning email to: {$user->email}");
                    Log::info('Sent abandoned registration warning', ['user_id' => $user->id, 'email' => $user->email]);
                } catch (\Exception $e) {
                    $this->error("Failed to send warning to {$user->email}: {$e->getMessage()}");
                    Log::error('Failed to send abandoned registration warning', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    protected function deleteAbandonedRegistrations(bool $dryRun): void
    {
        $this->info('Checking for abandoned registrations to delete...');

        // Find users who registered 30 days ago and haven't completed registration
        $deletionDate = now()->subDays(30)->startOfDay();

        $usersToDelete = User::where('registration_step', '<', 4)
            ->whereNotNull('registration_token')
            ->whereDate('created_at', '<=', $deletionDate)
            ->get();

        if ($usersToDelete->isEmpty()) {
            $this->info('No abandoned registrations to delete.');

            return;
        }

        $this->warn("Found {$usersToDelete->count()} abandoned registrations to delete.");

        foreach ($usersToDelete as $user) {
            if ($dryRun) {
                $this->line("[DRY RUN] Would delete user: {$user->email} (ID: {$user->id}, Created: {$user->created_at})");
            } else {
                try {
                    $email = $user->email;
                    $userId = $user->id;
                    $createdAt = $user->created_at;

                    $user->forceDelete();

                    $this->line("Deleted user: {$email} (ID: {$userId})");
                    Log::info('Deleted abandoned registration', [
                        'user_id' => $userId,
                        'email' => $email,
                        'created_at' => $createdAt,
                    ]);
                } catch (\Exception $e) {
                    $this->error("Failed to delete user {$user->email}: {$e->getMessage()}");
                    Log::error('Failed to delete abandoned registration', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if (! $dryRun) {
            $this->info("Cleanup complete. Deleted {$usersToDelete->count()} abandoned registrations.");
        }
    }
}
