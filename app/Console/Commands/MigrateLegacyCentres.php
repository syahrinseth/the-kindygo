<?php

namespace App\Console\Commands;

use App\Services\Migration\MigrationLogger;
use App\Services\Migration\OrphanLogger;
use App\Services\Migration\StatusMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrateLegacyCentres extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:legacy-centres
                            {--dry-run : Run without making changes}
                            {--chunk=500 : Number of records to process at once}
                            {--tenant-id=1 : Target tenant ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate legacy campuses and centres (1_campuses → campuses, 1_preschool → centres)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $tenantId = (int) $this->option('tenant-id');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $this->info('Starting legacy centres migration...');

        // Step 1: Migrate campuses
        $campusResult = $this->migrateCampuses($tenantId, $dryRun);
        if ($campusResult !== Command::SUCCESS) {
            return $campusResult;
        }

        // Step 2: Migrate centres (preschools)
        $centreResult = $this->migrateCentres($tenantId, $dryRun);
        if ($centreResult !== Command::SUCCESS) {
            return $centreResult;
        }

        $this->newLine();
        $this->info('Legacy centres migration completed!');

        return Command::SUCCESS;
    }

    /**
     * Migrate legacy campuses to campuses table.
     */
    private function migrateCampuses(int $tenantId, bool $dryRun): int
    {
        $this->newLine();
        $this->info('--- Migrating Campuses ---');

        $logger = new MigrationLogger('phase_1', '1_campuses', 'campuses');

        $legacyCampuses = DB::connection('legacy')
            ->table('1_campuses')
            ->whereNull('deleted_at')
            ->get();

        $logger->setTotalSource($legacyCampuses->count());
        $this->info("Found {$legacyCampuses->count()} legacy campuses to migrate.");

        $bar = $this->output->createProgressBar($legacyCampuses->count());
        $bar->start();

        foreach ($legacyCampuses as $legacy) {
            try {
                $data = [
                    'id' => $legacy->id,
                    'tenant_id' => $tenantId,
                    'name' => $legacy->name,
                    'description' => null,
                    'meta_data' => json_encode([
                        'legacy_source_table' => '1_campuses',
                        'legacy_id' => $legacy->id,
                        'legacy_short_name' => $legacy->short_name ?: null,
                        'legacy_status' => $legacy->status ?: null,
                        'legacy_ssm_comp_name' => $legacy->ssm_comp_name ?: null,
                        'legacy_ssm_no' => $legacy->ssm_no ?: null,
                    ]),
                    'phone' => $legacy->no_phone ?: null,
                    'email' => null,
                    'address_1' => $legacy->add_1 ?: null,
                    'address_2' => $legacy->add_2 ?: null,
                    'postal_code' => $legacy->postcode ?: null,
                    'city' => $legacy->city ?: null,
                    'state' => StatusMapper::state($legacy->state),
                    'created_at' => $legacy->created_at ?? now(),
                    'updated_at' => $legacy->updated_at ?? now(),
                ];

                if (! $dryRun) {
                    DB::table('campuses')->updateOrInsert(
                        ['id' => $legacy->id],
                        $data
                    );
                }

                $logger->incrementMigrated();
            } catch (\Exception $e) {
                $logger->logError("Campus {$legacy->id}: {$e->getMessage()}", $legacy->id);
                $this->error("Error migrating campus {$legacy->id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $logger->complete();
        $this->newLine();

        $log = $logger->getLog();
        $this->table(['Metric', 'Count'], [
            ['Source', $log->total_source],
            ['Migrated', $log->total_migrated],
            ['Errors', $log->total_errors],
        ]);

        return Command::SUCCESS;
    }

    /**
     * Migrate legacy preschools to centres table.
     */
    private function migrateCentres(int $tenantId, bool $dryRun): int
    {
        $this->newLine();
        $this->info('--- Migrating Centres (Preschools) ---');

        $logger = new MigrationLogger('phase_1', '1_preschool', 'centres');

        $legacyCentres = DB::connection('legacy')
            ->table('1_preschool')
            ->whereNull('deleted_at')
            ->get();

        $logger->setTotalSource($legacyCentres->count());
        $this->info("Found {$legacyCentres->count()} legacy preschools to migrate.");

        // Collect existing slugs and codes to avoid unique constraint violations
        $existingSlugs = DB::table('centres')->pluck('slug')->toArray();
        $existingCodes = DB::table('centres')->pluck('code')->toArray();

        $bar = $this->output->createProgressBar($legacyCentres->count());
        $bar->start();

        foreach ($legacyCentres as $legacy) {
            try {
                // Generate slug from short_name or name
                $baseSlug = Str::slug($legacy->short_name ?: $legacy->name);
                $slug = $this->ensureUniqueSlug($baseSlug, $legacy->id, $existingSlugs);

                // Use short_name as code, generate from name if empty
                $code = $legacy->short_name ?: Str::upper(Str::substr(Str::slug($legacy->name, ''), 0, 10));
                $code = $this->ensureUniqueCode($code, $legacy->id, $existingCodes);

                $campusId = $this->resolveCampusId($legacy, $tenantId, $dryRun);

                // Map centre status (close → inactive, licensee → active)
                $mappedStatus = StatusMapper::centreStatus($legacy->status);

                // Build meta_data
                $metaData = [];
                if ($legacy->status !== $mappedStatus) {
                    $metaData['legacy_status'] = $legacy->status;
                }
                if (! empty($legacy->ssm_comp_name)) {
                    $metaData['legacy_ssm_comp_name'] = $legacy->ssm_comp_name;
                }
                if (! empty($legacy->ssm_no)) {
                    $metaData['legacy_ssm_no'] = $legacy->ssm_no;
                }
                if (! empty($legacy->capacity)) {
                    $metaData['legacy_capacity'] = (int) $legacy->capacity;
                }

                // Map state from int to MalaysianState enum value
                $stateValue = StatusMapper::state($legacy->state);

                $data = [
                    'id' => $legacy->id,
                    'tenant_id' => $tenantId,
                    'campus_id' => $campusId,
                    'slug' => $slug,
                    'code' => $code,
                    'name' => $legacy->name,
                    'status' => $mappedStatus,
                    'phone' => $legacy->no_phone ?: null,
                    'email' => null,
                    'address_1' => $legacy->add_1 ?: null,
                    'address_2' => $legacy->add_2 ?: null,
                    'postal_code' => $legacy->postcode ?: null,
                    'city' => $legacy->city ?: null,
                    'state' => $stateValue,
                    'meta_data' => ! empty($metaData) ? json_encode($metaData) : null,
                    'created_at' => $legacy->created_at ?? now(),
                    'updated_at' => $legacy->updated_at ?? now(),
                ];

                if (! $dryRun) {
                    DB::table('centres')->updateOrInsert(
                        ['id' => $legacy->id],
                        $data
                    );
                }

                // Track used slugs/codes to avoid duplicates within this batch
                $existingSlugs[] = $slug;
                $existingCodes[] = $code;

                $logger->incrementMigrated();
            } catch (\Exception $e) {
                $logger->logError("Centre {$legacy->id}: {$e->getMessage()}", $legacy->id);
                $this->error("Error migrating centre {$legacy->id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $logger->complete();
        $this->newLine();

        $log = $logger->getLog();
        $this->table(['Metric', 'Count'], [
            ['Source', $log->total_source],
            ['Migrated', $log->total_migrated],
            ['Errors', $log->total_errors],
        ]);

        return Command::SUCCESS;
    }

    /**
     * Resolve the campus for a legacy preschool.
     */
    private function resolveCampusId(object $legacy, int $tenantId, bool $dryRun): ?int
    {
        $legacyCampusId = is_numeric($legacy->campus_id) ? (int) $legacy->campus_id : 0;

        if ($legacyCampusId > 0) {
            if (DB::table('campuses')->where('id', $legacyCampusId)->where('tenant_id', $tenantId)->exists()) {
                return $legacyCampusId;
            }

            OrphanLogger::log('1_preschool', $legacy->id, "campus_id {$legacyCampusId} not found in campuses table", (array) $legacy);

            return null;
        }

        if ($dryRun) {
            return null;
        }

        return $this->createOrFindGeneratedCampus($legacy, $tenantId);
    }

    /**
     * Create one campus for a preschool that was not assigned to a legacy campus.
     */
    private function createOrFindGeneratedCampus(object $legacy, int $tenantId): int
    {
        $existingCampus = DB::table('campuses')
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->get(['id', 'meta_data'])
            ->first(function (object $campus) use ($legacy): bool {
                return data_get(json_decode($campus->meta_data ?? '[]', true), 'legacy_preschool_id') === (int) $legacy->id;
            });

        if ($existingCampus) {
            return $existingCampus->id;
        }

        $name = Str::limit("Legacy Campus — {$legacy->name}", 220, '');

        return DB::table('campuses')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => "{$name} ({$legacy->id})",
            'description' => 'Created during legacy migration because the source preschool had no campus assignment.',
            'meta_data' => json_encode([
                'legacy_source_table' => '1_preschool',
                'legacy_preschool_id' => (int) $legacy->id,
                'generated_for_unassigned_centre' => true,
            ]),
            'phone' => $legacy->no_phone ?: null,
            'email' => null,
            'address_1' => $legacy->add_1 ?: null,
            'address_2' => $legacy->add_2 ?: null,
            'postal_code' => $legacy->postcode ?: null,
            'city' => $legacy->city ?: null,
            'state' => StatusMapper::state($legacy->state),
            'created_at' => $legacy->created_at ?? now(),
            'updated_at' => $legacy->updated_at ?? now(),
        ]);
    }

    /**
     * Ensure the slug is unique by appending a suffix if needed.
     *
     * @param  array<string>  $existingSlugs
     */
    private function ensureUniqueSlug(string $baseSlug, int $currentId, array $existingSlugs): string
    {
        // Check if this ID already owns a slug in the DB
        $existing = DB::table('centres')->where('id', $currentId)->value('slug');
        if ($existing) {
            return $existing;
        }

        $slug = $baseSlug;
        $counter = 1;

        while (in_array($slug, $existingSlugs)) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * Ensure the code is unique by appending a number if needed.
     *
     * @param  array<string>  $existingCodes
     */
    private function ensureUniqueCode(string $baseCode, int $currentId, array $existingCodes): string
    {
        // Check if this ID already owns a code in the DB
        $existing = DB::table('centres')->where('id', $currentId)->value('code');
        if ($existing) {
            return $existing;
        }

        $code = $baseCode;
        $counter = 1;

        while (in_array($code, $existingCodes)) {
            $code = "{$baseCode}{$counter}";
            $counter++;
        }

        return $code;
    }
}
