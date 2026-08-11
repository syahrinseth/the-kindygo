<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class MigrateLegacyAll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:legacy
                            {--dry-run : Run all phases in dry-run mode}
                            {--chunk=500 : Number of records to process per chunk}
                            {--media-chunk=50 : Number of media records to process per chunk}
                            {--media-start-id=0 : Resume media migration after this record ID}
                            {--media-memory-limit=512M : PHP memory limit used by the media migration}
                            {--tenant-id=1 : Target tenant ID}
                            {--skip-existing : Skip media records that already have media attached}
                            {--skip-media : Skip media migration (Phase 4)}
                            {--skip-validation : Skip validation (Phase 5)}
                            {--from-phase=1 : Start from a specific phase (1-5)}
                            {--to-phase=5 : Stop after a specific phase (1-5)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run all legacy migration phases in order (orchestrator)';

    /**
     * Phase definitions in execution order.
     *
     * @var array<int, array{name: string, command: string, options: array<string, mixed>, description: string}>
     */
    private array $phases = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $chunkSize = $this->option('chunk');
        $mediaChunkSize = $this->option('media-chunk');
        $mediaStartId = $this->option('media-start-id');
        $mediaMemoryLimit = $this->option('media-memory-limit');
        $tenantId = $this->option('tenant-id');
        $skipExisting = $this->option('skip-existing');
        $skipMedia = $this->option('skip-media');
        $skipValidation = $this->option('skip-validation');
        $fromPhase = (int) $this->option('from-phase');
        $toPhase = (int) $this->option('to-phase');

        if (! $this->passesPreflight((int) $tenantId)) {
            return Command::FAILURE;
        }

        $this->buildPhaseList($dryRun, $chunkSize, $mediaChunkSize, $mediaStartId, $mediaMemoryLimit, $tenantId, $skipExisting, $skipMedia, $skipValidation);

        $this->newLine();
        $this->info('==============================================');
        $this->info('  KindyGo Legacy Migration — Full Orchestrator');
        $this->info('==============================================');

        if ($dryRun) {
            $this->warn('DRY RUN MODE — No changes will be made');
        }

        $this->info("Phases: {$fromPhase} to {$toPhase}");
        $this->info("Tenant ID: {$tenantId}");
        $this->info("Chunk size: {$chunkSize}");
        $this->info("Media chunk size: {$mediaChunkSize}");
        $this->newLine();

        $totalPhases = count($this->phases);
        $completedPhases = 0;
        $failedPhases = 0;
        $skippedPhases = 0;

        foreach ($this->phases as $phase) {
            $phaseNumber = $phase['phase'];

            if ($phaseNumber < $fromPhase || $phaseNumber > $toPhase) {
                $skippedPhases++;

                continue;
            }

            $this->info('----------------------------------------------');
            $this->info("  Phase {$phaseNumber}: {$phase['description']}");
            $this->info("  Command: {$phase['command']}");
            $this->info('----------------------------------------------');

            $startTime = microtime(true);
            $exitCode = $this->call($phase['command'], $phase['options']);
            $duration = round(microtime(true) - $startTime, 1);

            if ($exitCode !== Command::SUCCESS) {
                $this->error("Phase {$phaseNumber} FAILED (exit code: {$exitCode}) after {$duration}s");
                $failedPhases++;

                if (! $this->confirm("Phase {$phaseNumber} failed. Continue with remaining phases?", false)) {
                    $this->error('Migration aborted by user.');

                    return Command::FAILURE;
                }
            } else {
                $this->info("Phase {$phaseNumber} completed in {$duration}s");
                $completedPhases++;
            }

            $this->newLine();
        }

        // Summary
        $this->newLine();
        $this->info('==============================================');
        $this->info('  Migration Summary');
        $this->info('==============================================');

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Phases', $totalPhases],
                ['Completed', $completedPhases],
                ['Failed', $failedPhases],
                ['Skipped', $skippedPhases],
            ]
        );

        if ($failedPhases > 0) {
            $this->error("{$failedPhases} phase(s) failed. Review the output above for details.");

            return Command::FAILURE;
        }

        $this->info('All migration phases completed successfully!');

        return Command::SUCCESS;
    }

    /**
     * Verify required target and source dependencies before importing anything.
     */
    private function passesPreflight(int $tenantId): bool
    {
        if (! DB::table('tenants')->where('id', $tenantId)->exists()) {
            $this->error("Target tenant {$tenantId} does not exist. Run the legacy migration bootstrap seeder first.");

            return false;
        }

        try {
            DB::connection('legacy')->getPdo();
            DB::connection('legacy')->table('1_users')->limit(1)->exists();
        } catch (Throwable $exception) {
            $this->error('Legacy database preflight failed: '.$exception->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Build the phase list with options.
     */
    private function buildPhaseList(
        bool $dryRun,
        string $chunkSize,
        string $mediaChunkSize,
        string $mediaStartId,
        string $mediaMemoryLimit,
        string $tenantId,
        bool $skipExisting,
        bool $skipMedia,
        bool $skipValidation,
    ): void {
        $baseOptions = array_filter([
            '--chunk' => $chunkSize,
            '--tenant-id' => $tenantId,
            '--dry-run' => $dryRun ?: null,
        ], fn ($v) => $v !== null);

        $this->phases = [
            [
                'phase' => 1,
                'command' => 'migrate:legacy-centres',
                'options' => $baseOptions,
                'description' => 'Foundation — Centres & Campuses',
            ],
            [
                'phase' => 1,
                'command' => 'migrate:legacy-roles',
                'options' => array_filter([
                    '--dry-run' => $dryRun ?: null,
                ], fn ($v) => $v !== null),
                'description' => 'Foundation — Roles',
            ],
            [
                'phase' => 2,
                'command' => 'migrate:legacy-users',
                'options' => $baseOptions,
                'description' => 'Master Data — Users, Profiles, Addresses',
            ],
            [
                'phase' => 2,
                'command' => 'migrate:legacy-products',
                'options' => $baseOptions,
                'description' => 'Master Data — Products & Prices',
            ],
            [
                'phase' => 2,
                'command' => 'migrate:legacy-children',
                'options' => $baseOptions,
                'description' => 'Master Data — Children & Enrolments',
            ],
            [
                'phase' => 3,
                'command' => 'migrate:legacy-invoices',
                'options' => $baseOptions,
                'description' => 'Financial — Invoices & Invoice Items',
            ],
            [
                'phase' => 3,
                'command' => 'migrate:legacy-payments',
                'options' => $baseOptions,
                'description' => 'Financial — Payments & Invoice-Payment Pivot',
            ],
            [
                'phase' => 3,
                'command' => 'migrate:legacy-quotations',
                'options' => $baseOptions,
                'description' => 'Financial — Historical Quotations & Items',
            ],
        ];

        if (! $skipMedia) {
            $this->phases[] = [
                'phase' => 4,
                'command' => 'migrate:legacy-media',
                'options' => array_filter([
                    '--chunk' => $mediaChunkSize,
                    '--start-id' => $mediaStartId,
                    '--memory-limit' => $mediaMemoryLimit,
                    '--skip-existing' => $skipExisting ?: null,
                    '--dry-run' => $dryRun ?: null,
                ], fn ($v) => $v !== null),
                'description' => 'Media — Children, Users, Family Members, Payment Proof',
            ];
        }

        if (! $skipValidation) {
            $this->phases[] = [
                'phase' => 5,
                'command' => 'migrate:legacy-validate',
                'options' => array_filter([
                    '--tenant-id' => $tenantId,
                ], fn ($v) => $v !== null),
                'description' => 'Validation — Data Integrity Checks',
            ];
        }
    }
}
