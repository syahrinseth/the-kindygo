<?php

namespace App\Console\Commands;

use App\Models\Child;
use App\Models\FamilyMember;
use App\Models\Payment;
use App\Models\User;
use App\Services\Migration\MigrationLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MigrateLegacyMedia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:legacy-media
                            {--dry-run : Run without making changes}
                            {--step= : Run only a specific step (1=children, 2=users, 3=family-members, 4=payment-proof)}
                            {--chunk=500 : Number of records to process at once}
                            {--start-id=0 : Start from a specific ID (skips all records below this ID)}
                            {--skip-existing : Skip records that already have media attached}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate legacy media files to Spatie Media Library (children, users, family members, payment proof)';

    /**
     * Base path to legacy media files.
     */
    private string $legacyBasePath;

    /**
     * Counters for summary output.
     *
     * @var array<string, int>
     */
    private array $counters = [
        'processed' => 0,
        'attached' => 0,
        'skipped' => 0,
        'missing' => 0,
        'errors' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->legacyBasePath = storage_path('app/kindygo-legacy/c136c9fde0ee46499ef6da5e15455449');

        if (! File::isDirectory($this->legacyBasePath)) {
            $this->error("Legacy media directory not found: {$this->legacyBasePath}");

            return Command::FAILURE;
        }

        $dryRun = $this->option('dry-run');
        $step = $this->option('step');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $this->info('Starting legacy media migration...');
        $this->newLine();

        $steps = $step ? [(int) $step] : [1, 2, 3, 4];

        foreach ($steps as $currentStep) {
            $this->resetCounters();
            $result = match ($currentStep) {
                1 => $this->migrateChildMedia($dryRun),
                2 => $this->migrateUserMedia($dryRun),
                3 => $this->migrateFamilyMemberMedia($dryRun),
                4 => $this->migratePaymentProofMedia($dryRun),
                default => $this->error("Invalid step: {$currentStep}") ?? Command::FAILURE,
            };

            if ($result !== Command::SUCCESS) {
                return $result;
            }
        }

        $this->newLine();
        $this->info('Legacy media migration completed!');

        return Command::SUCCESS;
    }

    /**
     * Step 1: Migrate child media (photo, birth_certificate, immunization_card).
     *
     * Source: children/{child_id}/profile/{filename}
     * Target: Child model media collections
     */
    private function migrateChildMedia(bool $dryRun): int
    {
        $this->info('--- Step 1: Migrating Child Media ---');

        $logger = new MigrationLogger('phase_4_child_media', 'legacy_files', 'media');
        $chunkSize = (int) $this->option('chunk');
        $startId = (int) $this->option('start-id');
        $skipExisting = $this->option('skip-existing');

        $mediaMapping = [
            'passport_sized_image' => 'photo',
            'child_birth_certificate' => 'birth_certificate',
            'immunization_card' => 'immunization_card',
        ];

        // Get children IDs that have legacy directories
        $childrenDir = $this->legacyBasePath.'/children';
        if (! File::isDirectory($childrenDir)) {
            $this->warn('No children media directory found, skipping...');
            $logger->complete();

            return Command::SUCCESS;
        }

        // Query children from DB and process in chunks
        $query = Child::withoutGlobalScopes()
            ->where('id', '>', $startId)
            ->orderBy('id');

        $totalChildren = (clone $query)->count();
        $logger->setTotalSource($totalChildren);
        $this->info("Processing media for {$totalChildren} children...");
        $bar = $this->output->createProgressBar($totalChildren);
        $bar->start();

        $query->chunk($chunkSize, function ($children) use ($mediaMapping, $childrenDir, $dryRun, $skipExisting, $logger, $bar) {
            foreach ($children as $child) {
                $this->counters['processed']++;
                $childDir = $childrenDir.'/'.$child->id.'/profile';

                if (! File::isDirectory($childDir)) {
                    $this->counters['missing']++;
                    $bar->advance();

                    continue;
                }

                foreach ($mediaMapping as $legacyFilename => $collectionName) {
                    // Skip if already has media in this collection
                    if ($skipExisting && $child->hasMedia($collectionName)) {
                        $this->counters['skipped']++;

                        continue;
                    }

                    $filePath = $this->findFileWithAnyExtension($childDir, $legacyFilename);

                    if ($filePath === null) {
                        $this->counters['missing']++;

                        continue;
                    }

                    if ($dryRun) {
                        $this->counters['attached']++;

                        continue;
                    }

                    try {
                        // Clear existing media in collection (singleFile) before adding
                        $child->clearMediaCollection($collectionName);

                        $child->addMedia($filePath)
                            ->preservingOriginal()
                            ->withCustomProperties([
                                'legacy_source' => 'children/'.$child->id.'/profile/'.basename($filePath),
                            ])
                            ->toMediaCollection($collectionName);

                        $this->counters['attached']++;
                        $logger->incrementMigrated();
                    } catch (\Throwable $e) {
                        $this->counters['errors']++;
                        $logger->logError("Child {$child->id} - {$collectionName}: {$e->getMessage()}", $child->id);
                    }
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $logger->complete();
        $this->printStepSummary('Child Media');

        return Command::SUCCESS;
    }

    /**
     * Step 2: Migrate user media (mykad, photo, immunization_card).
     *
     * Source: users/{user_id}/profile/{filename}
     * Target: User model media collections
     */
    private function migrateUserMedia(bool $dryRun): int
    {
        $this->newLine();
        $this->info('--- Step 2: Migrating User Media ---');

        $logger = new MigrationLogger('phase_4_user_media', 'legacy_files', 'media');
        $chunkSize = (int) $this->option('chunk');
        $startId = (int) $this->option('start-id');
        $skipExisting = $this->option('skip-existing');

        $mediaMapping = [
            'user_mykad_image' => 'mykad',
            'user_passport_size_photo' => 'photo',
            'user_immunization_card' => 'immunization_card',
        ];

        $usersDir = $this->legacyBasePath.'/users';
        if (! File::isDirectory($usersDir)) {
            $this->warn('No users media directory found, skipping...');
            $logger->complete();

            return Command::SUCCESS;
        }

        // Query users from DB and process in chunks
        $query = User::withoutGlobalScopes()
            ->where('id', '>', $startId)
            ->orderBy('id');

        $totalUsers = (clone $query)->count();
        $logger->setTotalSource($totalUsers);
        $this->info("Processing media for {$totalUsers} users...");
        $bar = $this->output->createProgressBar($totalUsers);
        $bar->start();

        $query->chunk($chunkSize, function ($users) use ($mediaMapping, $usersDir, $dryRun, $skipExisting, $logger, $bar) {
            foreach ($users as $user) {
                $this->counters['processed']++;
                $userDir = $usersDir.'/'.$user->id.'/profile';

                if (! File::isDirectory($userDir)) {
                    $this->counters['missing']++;
                    $bar->advance();

                    continue;
                }

                foreach ($mediaMapping as $legacyFilename => $collectionName) {
                    if ($skipExisting && $user->hasMedia($collectionName)) {
                        $this->counters['skipped']++;

                        continue;
                    }

                    $filePath = $this->findFileWithAnyExtension($userDir, $legacyFilename);

                    if ($filePath === null) {
                        $this->counters['missing']++;

                        continue;
                    }

                    if ($dryRun) {
                        $this->counters['attached']++;

                        continue;
                    }

                    try {
                        $user->clearMediaCollection($collectionName);

                        $user->addMedia($filePath)
                            ->preservingOriginal()
                            ->withCustomProperties([
                                'legacy_source' => 'users/'.$user->id.'/profile/'.basename($filePath),
                            ])
                            ->toMediaCollection($collectionName);

                        $this->counters['attached']++;
                        $logger->incrementMigrated();
                    } catch (\Throwable $e) {
                        $this->counters['errors']++;
                        $logger->logError("User {$user->id} - {$collectionName}: {$e->getMessage()}", $user->id);
                    }
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $logger->complete();
        $this->printStepSummary('User Media');

        return Command::SUCCESS;
    }

    /**
     * Step 3: Migrate family member media (spouse mykad, spouse photo).
     *
     * Source: users/{user_id}/profile/spouse_{filename}
     * Target: FamilyMember model media collections
     */
    private function migrateFamilyMemberMedia(bool $dryRun): int
    {
        $this->newLine();
        $this->info('--- Step 3: Migrating Family Member (Spouse) Media ---');

        $logger = new MigrationLogger('phase_4_family_member_media', 'legacy_files', 'media');
        $chunkSize = (int) $this->option('chunk');
        $startId = (int) $this->option('start-id');
        $skipExisting = $this->option('skip-existing');

        $mediaMapping = [
            'spouse_mykad_image' => 'mykad',
            'spouse_passport_size_photo' => 'photo',
        ];

        $usersDir = $this->legacyBasePath.'/users';
        if (! File::isDirectory($usersDir)) {
            $this->warn('No users media directory found, skipping...');
            $logger->complete();

            return Command::SUCCESS;
        }

        // Family members were created during Phase 2a user migration.
        // Each family_member has a user_id linking back to the parent user.
        $query = FamilyMember::query()
            ->where('id', '>', $startId)
            ->orderBy('id');

        $totalFamilyMembers = (clone $query)->count();
        $logger->setTotalSource($totalFamilyMembers);
        $this->info("Processing media for {$totalFamilyMembers} family members...");
        $bar = $this->output->createProgressBar($totalFamilyMembers);
        $bar->start();

        $query->chunk($chunkSize, function ($familyMembers) use ($mediaMapping, $usersDir, $dryRun, $skipExisting, $logger, $bar) {
            foreach ($familyMembers as $familyMember) {
                $this->counters['processed']++;

                // Spouse media lives under the parent user's directory
                $userDir = $usersDir.'/'.$familyMember->user_id.'/profile';

                if (! File::isDirectory($userDir)) {
                    $this->counters['missing']++;
                    $bar->advance();

                    continue;
                }

                foreach ($mediaMapping as $legacyFilename => $collectionName) {
                    if ($skipExisting && $familyMember->hasMedia($collectionName)) {
                        $this->counters['skipped']++;

                        continue;
                    }

                    $filePath = $this->findFileWithAnyExtension($userDir, $legacyFilename);

                    if ($filePath === null) {
                        $this->counters['missing']++;

                        continue;
                    }

                    if ($dryRun) {
                        $this->counters['attached']++;

                        continue;
                    }

                    try {
                        $familyMember->clearMediaCollection($collectionName);

                        $familyMember->addMedia($filePath)
                            ->preservingOriginal()
                            ->withCustomProperties([
                                'legacy_source' => 'users/'.$familyMember->user_id.'/profile/'.basename($filePath),
                            ])
                            ->toMediaCollection($collectionName);

                        $this->counters['attached']++;
                        $logger->incrementMigrated();
                    } catch (\Throwable $e) {
                        $this->counters['errors']++;
                        $logger->logError("FamilyMember {$familyMember->id} (user {$familyMember->user_id}) - {$collectionName}: {$e->getMessage()}", $familyMember->id);
                    }
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $logger->complete();
        $this->printStepSummary('Family Member Media');

        return Command::SUCCESS;
    }

    /**
     * Step 4: Migrate payment proof media.
     *
     * Source: transactions/bills/payment_slips/{transaction_id}.{ext}
     * The payment_slip path is stored in payments.meta JSON field.
     * Target: Payment model media collection 'payment_proof'
     */
    private function migratePaymentProofMedia(bool $dryRun): int
    {
        $this->newLine();
        $this->info('--- Step 4: Migrating Payment Proof Media ---');

        $logger = new MigrationLogger('phase_4_payment_proof', 'legacy_files', 'media');
        $chunkSize = (int) $this->option('chunk');
        $startId = (int) $this->option('start-id');
        $skipExisting = $this->option('skip-existing');

        $paymentSlipsDir = $this->legacyBasePath.'/transactions/bills/payment_slips';
        if (! File::isDirectory($paymentSlipsDir)) {
            $this->warn('No payment slips directory found, skipping...');
            $logger->complete();

            return Command::SUCCESS;
        }

        // Query payments that have a payment_slip path in their meta JSON
        $query = Payment::withoutGlobalScopes()
            ->whereNotNull('meta')
            ->whereRaw("JSON_EXTRACT(meta, '$.payment_slip') IS NOT NULL")
            ->where('id', '>', $startId)
            ->orderBy('id');

        $totalPayments = (clone $query)->count();
        $logger->setTotalSource($totalPayments);
        $this->info("Processing payment proof for {$totalPayments} payments...");
        $bar = $this->output->createProgressBar($totalPayments);
        $bar->start();

        $query->chunk($chunkSize, function ($payments) use ($paymentSlipsDir, $dryRun, $skipExisting, $logger, $bar) {
            foreach ($payments as $payment) {
                $this->counters['processed']++;

                if ($skipExisting && $payment->hasMedia('payment_proof')) {
                    $this->counters['skipped']++;
                    $bar->advance();

                    continue;
                }

                $meta = is_array($payment->meta) ? $payment->meta : json_decode($payment->meta, true);
                $paymentSlipPath = $meta['payment_slip'] ?? null;

                if (empty($paymentSlipPath)) {
                    $this->counters['missing']++;
                    $bar->advance();

                    continue;
                }

                // Payment slip path format: /transactions/bills/payment_slips/{id}.{ext}
                // Resolve to full path
                $fullPath = $this->legacyBasePath.$paymentSlipPath;

                if (! File::exists($fullPath)) {
                    // Try finding by payment ID in the flat directory
                    $filePath = $this->findFileById($paymentSlipsDir, (string) $payment->id);

                    if ($filePath === null) {
                        $this->counters['missing']++;
                        $bar->advance();

                        continue;
                    }

                    $fullPath = $filePath;
                }

                if ($dryRun) {
                    $this->counters['attached']++;
                    $bar->advance();

                    continue;
                }

                try {
                    $payment->clearMediaCollection('payment_proof');

                    $payment->addMedia($fullPath)
                        ->preservingOriginal()
                        ->withCustomProperties([
                            'legacy_source' => $paymentSlipPath,
                        ])
                        ->toMediaCollection('payment_proof');

                    $this->counters['attached']++;
                    $logger->incrementMigrated();
                } catch (\Throwable $e) {
                    $this->counters['errors']++;
                    $logger->logError("Payment {$payment->id}: {$e->getMessage()}", $payment->id);
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $logger->complete();
        $this->printStepSummary('Payment Proof Media');

        return Command::SUCCESS;
    }

    /**
     * Find a file matching the given base name with any extension.
     * Handles case-insensitive extension matching (jpg, JPG, jpeg, JPEG, etc.).
     */
    private function findFileWithAnyExtension(string $directory, string $baseName): ?string
    {
        // Common image/document extensions to check
        $extensions = ['jpg', 'jpeg', 'png', 'pdf', 'webp', 'jfif', 'bmp', 'heic'];

        // Try exact case first (most common)
        foreach ($extensions as $ext) {
            $path = $directory.'/'.$baseName.'.'.$ext;
            if (File::exists($path)) {
                return $path;
            }
        }

        // Try uppercase extensions
        foreach ($extensions as $ext) {
            $path = $directory.'/'.$baseName.'.'.strtoupper($ext);
            if (File::exists($path)) {
                return $path;
            }
        }

        // Fallback: glob for any matching file (catches unusual cases)
        $matches = glob($directory.'/'.$baseName.'.*');
        if (! empty($matches)) {
            return $matches[0];
        }

        return null;
    }

    /**
     * Find a file by numeric ID in a flat directory (for payment slips).
     * Files are named {id}.{ext} in the payment_slips directory.
     */
    private function findFileById(string $directory, string $id): ?string
    {
        $matches = glob($directory.'/'.$id.'.*');
        if (! empty($matches)) {
            // Filter out extensionless files (e.g., "22740.")
            $validMatches = array_filter($matches, function ($path) {
                $ext = pathinfo($path, PATHINFO_EXTENSION);

                return ! empty($ext);
            });

            return ! empty($validMatches) ? reset($validMatches) : null;
        }

        return null;
    }

    /**
     * Reset step counters.
     */
    private function resetCounters(): void
    {
        $this->counters = [
            'processed' => 0,
            'attached' => 0,
            'skipped' => 0,
            'missing' => 0,
            'errors' => 0,
        ];
    }

    /**
     * Print summary for a migration step.
     */
    private function printStepSummary(string $stepName): void
    {
        $this->info("{$stepName} Summary:");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Processed', $this->counters['processed']],
                ['Attached', $this->counters['attached']],
                ['Skipped (existing)', $this->counters['skipped']],
                ['Missing files', $this->counters['missing']],
                ['Errors', $this->counters['errors']],
            ]
        );
    }
}
