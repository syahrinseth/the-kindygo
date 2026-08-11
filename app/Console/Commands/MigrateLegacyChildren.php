<?php

namespace App\Console\Commands;

use App\Services\Migration\MigrationLogger;
use App\Services\Migration\NameParser;
use App\Services\Migration\OrphanLogger;
use App\Services\Migration\StatusMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MigrateLegacyChildren extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:legacy-children
                            {--dry-run : Run without making changes}
                            {--chunk=500 : Number of records to process at once}
                            {--tenant-id=1 : Target tenant ID}
                            {--start-id=0 : Migrate children after this legacy child ID}
                            {--end-id= : Migrate children up to and including this legacy child ID}
                            {--skip-existing : Skip children already migrated (faster re-runs)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate legacy children (1_child → children, child_enrolments, child_user, tenant_child, centre_child)';

    /**
     * Counters for summary table.
     *
     * @var array<string, int>
     */
    private array $counts = [
        'enrolments' => 0,
        'child_user' => 0,
        'tenant_child' => 0,
        'centre_child' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');
        $tenantId = (int) $this->option('tenant-id');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $this->info('Starting legacy children migration...');

        // Step 1: Migrate child profiles + pivots + enrolments
        $result = $this->migrateChildren($tenantId, $chunkSize, $dryRun);
        if ($result !== Command::SUCCESS) {
            return $result;
        }

        $this->newLine();
        $this->info('Legacy children migration completed!');

        return Command::SUCCESS;
    }

    /**
     * Migrate legacy children and all related data.
     */
    private function migrateChildren(int $tenantId, int $chunkSize, bool $dryRun): int
    {
        $this->newLine();
        $this->info('--- Migrating Children ---');

        $logger = new MigrationLogger('phase_2b', '1_child', 'children');
        $skipExisting = $this->option('skip-existing');
        $startId = (int) $this->option('start-id');
        $endId = $this->option('end-id') !== null ? (int) $this->option('end-id') : null;

        // Pre-load existing child IDs if skipping (include soft-deleted)
        $existingChildIds = $skipExisting
            ? DB::table('children')->pluck('id')->toArray()
            : [];

        // NOTE: Children include soft-deleted records (unlike other tables).
        $legacyChildrenQuery = DB::connection('legacy')
            ->table('1_child')
            ->where('id', '>', $startId)
            ->when($endId !== null, fn ($query) => $query->where('id', '<=', $endId));

        $totalCount = (clone $legacyChildrenQuery)->count();

        $logger->setTotalSource($totalCount);
        $this->info("Found {$totalCount} legacy children to migrate (including soft-deleted).");

        if ($skipExisting && count($existingChildIds) > 0) {
            $this->info('Skip-existing mode: will skip '.count($existingChildIds).' already-migrated children.');
        }

        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        // Pre-load valid IDs for FK validation
        $validCentreIds = DB::table('centres')->pluck('id')->toArray();
        $validProductIds = DB::table('products')->pluck('id')->toArray();
        $validUserIds = DB::table('users')->pluck('id')->toArray();

        $legacyChildrenQuery
            ->orderBy('id')
            ->chunk($chunkSize, function ($legacyChildren) use ($tenantId, $dryRun, $logger, $bar, $validCentreIds, $validProductIds, $validUserIds, $skipExisting, $existingChildIds) {
                foreach ($legacyChildren as $legacy) {
                    try {
                        if ($skipExisting && in_array($legacy->id, $existingChildIds)) {
                            $logger->incrementSkipped();
                            $bar->advance();

                            continue;
                        }

                        $this->migrateChild($legacy, $tenantId, $dryRun, $validCentreIds, $validProductIds, $validUserIds);
                        $logger->incrementMigrated();
                    } catch (\Exception $e) {
                        $logger->logError("Child {$legacy->id} ({$legacy->fullname}): {$e->getMessage()}", $legacy->id);
                        $this->newLine();
                        $this->error("Error migrating child {$legacy->id}: {$e->getMessage()}");
                    }

                    $bar->advance();
                }
            });

        $bar->finish();
        $logger->complete();
        $this->newLine();

        $log = $logger->getLog();
        $this->table(['Metric', 'Count'], [
            ['Source', $log->total_source],
            ['Migrated', $log->total_migrated],
            ['Skipped', $log->total_skipped],
            ['Errors', $log->total_errors],
            ['Enrolments', $this->counts['enrolments']],
            ['Child-User Links', $this->counts['child_user']],
            ['Tenant-Child Links', $this->counts['tenant_child']],
            ['Centre-Child Links', $this->counts['centre_child']],
        ]);

        return Command::SUCCESS;
    }

    /**
     * Migrate a single legacy child and all related data.
     *
     * @param  array<int>  $validCentreIds
     * @param  array<int>  $validProductIds
     * @param  array<int>  $validUserIds
     */
    private function migrateChild(
        object $legacy,
        int $tenantId,
        bool $dryRun,
        array $validCentreIds,
        array $validProductIds,
        array $validUserIds,
    ): void {
        // 1. Insert/update children table (profile data only)
        $this->migrateChildProfile($legacy, $dryRun);

        // 2. Create child_user pivot (parent_id → user_id)
        $this->createChildUserPivot($legacy, $dryRun, $validUserIds);

        // 3. Create tenant_child pivot
        $this->createTenantChildPivot($legacy, $tenantId, $dryRun);

        // 4. Create centre_child pivot
        $this->createCentreChildPivot($legacy, $dryRun, $validCentreIds);

        // 5. Create child_enrolments from product field
        $this->createEnrolment($legacy, $tenantId, $dryRun, $validCentreIds, $validProductIds);
    }

    /**
     * Migrate child profile data into children table.
     */
    private function migrateChildProfile(object $legacy, bool $dryRun): void
    {
        // Split fullname into first_name and last_name
        $name = NameParser::split($legacy->fullname ?? '');

        // Parse date of birth (datetime → date)
        $dateOfBirth = null;
        if (! empty($legacy->dob)) {
            try {
                $dateOfBirth = Carbon::parse($legacy->dob)->format('Y-m-d');
            } catch (\Exception $e) {
                $dateOfBirth = null;
            }
        }

        // Map gender, race, religion
        $gender = StatusMapper::gender(! empty($legacy->gender) ? (int) $legacy->gender : null);
        $race = StatusMapper::race(! empty($legacy->race) ? (int) $legacy->race : null);
        $religion = StatusMapper::religion(! empty($legacy->religion) ? (int) $legacy->religion : null);

        // Parse languages (longtext JSON)
        $languages = $this->parseJsonField($legacy->languages);

        // Parse allergies (varchar → wrap as JSON array)
        $allergies = $this->parseAllergies($legacy->allergies);

        // Parse diseases (longtext JSON)
        $diseases = $this->parseJsonField($legacy->diseases);

        $childData = [
            'id' => $legacy->id,
            'first_name' => $name['first_name'] ?: 'Unknown',
            'last_name' => $name['last_name'] ?: '',
            'mykid_no' => $legacy->mykid_no ?: null,
            'date_of_birth' => $dateOfBirth ?? '2000-01-01',
            'place_of_birth' => $legacy->pob ?: null,
            'gender' => $gender,
            'cert_number' => $legacy->cert_no ?: null,
            'position_of_child' => ! empty($legacy->post_of_child) ? (int) $legacy->post_of_child : null,
            'race' => $race,
            'religion' => $religion,
            'languages' => $languages ? json_encode($languages) : null,
            'allergies' => $allergies ? json_encode($allergies) : null,
            'diseases' => $diseases ? json_encode($diseases) : null,
            'family_clinic' => $legacy->family_clinic ?: null,
            'family_clinic_phone' => $legacy->family_clinic_phone ?: null,
            'created_at' => $legacy->created_at,
            'updated_at' => $legacy->updated_at ?? now(),
            'deleted_at' => $legacy->deleted_at,
        ];

        if (! $dryRun) {
            DB::table('children')->updateOrInsert(
                ['id' => $legacy->id],
                $childData
            );
        }
    }

    /**
     * Create child_user pivot record linking child to parent.
     *
     * @param  array<int>  $validUserIds
     */
    private function createChildUserPivot(object $legacy, bool $dryRun, array $validUserIds): void
    {
        if (empty($legacy->parent_id) || (int) $legacy->parent_id <= 0) {
            return;
        }

        $parentId = (int) $legacy->parent_id;

        if (! in_array($parentId, $validUserIds)) {
            OrphanLogger::log(
                '1_child',
                $legacy->id,
                "parent_id {$parentId} not found in users table",
                ['child_id' => $legacy->id, 'parent_id' => $parentId]
            );

            return;
        }

        if ($dryRun) {
            $this->counts['child_user']++;

            return;
        }

        DB::table('child_user')->updateOrInsert(
            [
                'child_id' => $legacy->id,
                'user_id' => $parentId,
            ],
            [
                'relationship_type' => 'parent',
                'created_at' => $legacy->created_at ?? now(),
                'updated_at' => $legacy->updated_at ?? now(),
            ]
        );

        $this->counts['child_user']++;
    }

    /**
     * Create tenant_child pivot record with status mapping.
     */
    private function createTenantChildPivot(object $legacy, int $tenantId, bool $dryRun): void
    {
        // Map child status for tenant_child pivot
        $childStatus = ! empty($legacy->status)
            ? StatusMapper::childStatusToChildStatus((int) $legacy->status)
            : \App\Enums\ChildStatus::INACTIVE;

        if ($dryRun) {
            $this->counts['tenant_child']++;

            return;
        }

        DB::table('tenant_child')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'child_id' => $legacy->id,
            ],
            [
                'status' => $childStatus->value,
                'created_at' => $legacy->created_at ?? now(),
                'updated_at' => $legacy->updated_at ?? now(),
            ]
        );

        $this->counts['tenant_child']++;
    }

    /**
     * Create centre_child pivot record from preschool_id.
     *
     * @param  array<int>  $validCentreIds
     */
    private function createCentreChildPivot(object $legacy, bool $dryRun, array $validCentreIds): void
    {
        if (empty($legacy->preschool_id) || (int) $legacy->preschool_id <= 0) {
            return;
        }

        $centreId = (int) $legacy->preschool_id;

        if (! in_array($centreId, $validCentreIds)) {
            OrphanLogger::log(
                '1_child',
                $legacy->id,
                "preschool_id {$centreId} not found in centres table",
                ['child_id' => $legacy->id, 'preschool_id' => $centreId]
            );

            return;
        }

        if ($dryRun) {
            $this->counts['centre_child']++;

            return;
        }

        DB::table('centre_child')->updateOrInsert(
            [
                'centre_id' => $centreId,
                'child_id' => $legacy->id,
            ],
            [
                'created_at' => $legacy->created_at ?? now(),
                'updated_at' => $legacy->updated_at ?? now(),
            ]
        );

        $this->counts['centre_child']++;
    }

    /**
     * Create child_enrolments record from product field.
     *
     * @param  array<int>  $validCentreIds
     * @param  array<int>  $validProductIds
     */
    private function createEnrolment(
        object $legacy,
        int $tenantId,
        bool $dryRun,
        array $validCentreIds,
        array $validProductIds,
    ): void {
        // Skip if no product assigned
        if (empty($legacy->product) || (int) $legacy->product <= 0) {
            return;
        }

        $productId = (int) $legacy->product;
        $centreId = ! empty($legacy->preschool_id) ? (int) $legacy->preschool_id : null;

        // Validate product exists in target DB
        if (! in_array($productId, $validProductIds)) {
            OrphanLogger::log(
                '1_child',
                $legacy->id,
                "product_id {$productId} not found in products table (enrolment skipped)",
                ['child_id' => $legacy->id, 'product_id' => $productId]
            );

            return;
        }

        // Validate centre exists
        if ($centreId === null || $centreId <= 0 || ! in_array($centreId, $validCentreIds)) {
            OrphanLogger::log(
                '1_child',
                $legacy->id,
                "preschool_id {$centreId} not valid for enrolment, skipping enrolment",
                ['child_id' => $legacy->id, 'centre_id' => $centreId, 'product_id' => $productId]
            );

            return;
        }

        // Map enrolment status
        $enrolmentStatus = ! empty($legacy->status)
            ? StatusMapper::childStatusToEnrolmentStatus((int) $legacy->status)
            : \App\Enums\ChildEnrolmentStatus::INACTIVE;

        // Map enrolment type
        $enrolmentType = StatusMapper::enrolmentType($legacy->type ?? null);

        // Generate date_start: use is_registered date if available, otherwise
        // for active statuses use the 24th of current month as specified in data mapping
        $dateStart = $this->determineDateStart($legacy);

        // Parse other_products → additional_products JSON structure
        $additionalProducts = $this->parseAdditionalProducts($legacy, $validProductIds);

        if ($dryRun) {
            $this->counts['enrolments']++;

            return;
        }

        DB::table('child_enrolments')->updateOrInsert(
            [
                'child_id' => $legacy->id,
                'product_id' => $productId,
                'tenant_id' => $tenantId,
            ],
            [
                'centre_id' => $centreId,
                'status' => $enrolmentStatus->value,
                'billed_every' => 'monthly',
                'date_start' => $dateStart,
                'date_end' => null,
                'next_bill_date' => null,
                'type' => $enrolmentType->value,
                'additional_products' => ! empty($additionalProducts) ? json_encode($additionalProducts) : null,
                'created_at' => $legacy->created_at ?? now(),
                'updated_at' => $legacy->updated_at ?? now(),
            ]
        );

        $this->counts['enrolments']++;
    }

    /**
     * Determine the date_start for an enrolment based on legacy data.
     */
    private function determineDateStart(object $legacy): string
    {
        // If is_registered has a value, try using it as a date
        if (! empty($legacy->is_registered)) {
            // is_registered could be a date string or a boolean-like value
            if (strlen((string) $legacy->is_registered) > 4) {
                try {
                    return Carbon::parse($legacy->is_registered)->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    // Fall through to default
                }
            }
        }

        // For active-ish statuses, use created_at as start date
        if (! empty($legacy->status) && StatusMapper::isActiveChildStatus((int) $legacy->status)) {
            if (! empty($legacy->created_at)) {
                return Carbon::parse($legacy->created_at)->format('Y-m-d H:i:s');
            }
        }

        // Default: use created_at or now
        if (! empty($legacy->created_at)) {
            return Carbon::parse($legacy->created_at)->format('Y-m-d H:i:s');
        }

        return now()->format('Y-m-d H:i:s');
    }

    /**
     * Parse other_products JSON into additional_products structure.
     *
     * Legacy format: JSON array of product IDs like ["235"] or ["155","225"]
     * Target format: [{"product_id": N, "date_start": "...", "date_end": null, "billed_every": "monthly", "notes": null}]
     *
     * @param  array<int>  $validProductIds
     * @return array<int, array<string, mixed>>
     */
    private function parseAdditionalProducts(object $legacy, array $validProductIds): array
    {
        if (empty($legacy->other_products) || $legacy->other_products === '[]' || $legacy->other_products === '' || $legacy->other_products === 'null') {
            return [];
        }

        $otherProducts = json_decode($legacy->other_products, true);
        if (! is_array($otherProducts) || empty($otherProducts)) {
            return [];
        }

        $dateStart = $this->determineDateStart($legacy);
        $additionalProducts = [];

        foreach ($otherProducts as $otherProductId) {
            $productId = (int) $otherProductId;

            if ($productId <= 0) {
                continue;
            }

            if (! in_array($productId, $validProductIds)) {
                OrphanLogger::log(
                    '1_child',
                    $legacy->id,
                    "other_product_id {$productId} not found in products table (additional_products entry skipped)",
                    ['child_id' => $legacy->id, 'other_product_id' => $productId]
                );

                continue;
            }

            $additionalProducts[] = [
                'product_id' => $productId,
                'date_start' => $dateStart,
                'date_end' => null,
                'billed_every' => 'monthly',
                'notes' => null,
            ];
        }

        return $additionalProducts;
    }

    /**
     * Parse a JSON field that may contain a JSON array or string.
     *
     * @return array<int, string>|null
     */
    private function parseJsonField(?string $value): ?array
    {
        if (empty($value) || $value === '[]' || $value === '' || $value === 'null') {
            return null;
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded) && ! empty($decoded)) {
            // Filter out empty strings
            $filtered = array_filter($decoded, fn ($item) => ! empty(trim((string) $item)));

            return ! empty($filtered) ? array_values($filtered) : null;
        }

        return null;
    }

    /**
     * Parse allergies field (varchar) into JSON array.
     * Allergies is a simple string, not JSON. Wrap it as a single-element array.
     *
     * @return array<int, string>|null
     */
    private function parseAllergies(?string $value): ?array
    {
        if (empty($value) || $value === '[]' || $value === '' || $value === 'null') {
            return null;
        }

        // First try to decode as JSON (in case some records are already JSON)
        $decoded = json_decode($value, true);
        if (is_array($decoded) && ! empty($decoded)) {
            $filtered = array_filter($decoded, fn ($item) => ! empty(trim((string) $item)));

            return ! empty($filtered) ? array_values($filtered) : null;
        }

        // Otherwise treat as plain string, wrap in array
        $trimmed = trim($value);

        return ! empty($trimmed) ? [$trimmed] : null;
    }
}
