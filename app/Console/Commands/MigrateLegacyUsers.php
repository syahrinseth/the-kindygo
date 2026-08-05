<?php

namespace App\Console\Commands;

use App\Services\Migration\MigrationLogger;
use App\Services\Migration\OrphanLogger;
use App\Services\Migration\StatusMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateLegacyUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:legacy-users
                            {--dry-run : Run without making changes}
                            {--chunk=500 : Number of records to process at once}
                            {--tenant-id=1 : Target tenant ID}
                            {--skip-existing : Skip users already migrated (faster re-runs)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate legacy users (1_users → users, user_profiles, user_addresses, user_office_infos, family_members, tenant_user, centre_user, model_has_roles)';

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

        $this->info('Starting legacy users migration...');

        // Step 1: Migrate user records
        $result = $this->migrateUsers($tenantId, $chunkSize, $dryRun);
        if ($result !== Command::SUCCESS) {
            return $result;
        }

        // Step 2: Migrate roles
        $this->migrateRoles($dryRun);

        $this->newLine();
        $this->info('Legacy users migration completed!');

        return Command::SUCCESS;
    }

    /**
     * Migrate legacy users and all related data.
     */
    private function migrateUsers(int $tenantId, int $chunkSize, bool $dryRun): int
    {
        $this->newLine();
        $this->info('--- Migrating Users ---');

        $logger = new MigrationLogger('phase_2a', '1_users', 'users');
        $skipExisting = $this->option('skip-existing');

        // Pre-load existing user IDs if skipping
        $existingUserIds = $skipExisting ? DB::table('users')->pluck('id')->toArray() : [];

        $totalCount = DB::connection('legacy')
            ->table('1_users')
            ->whereNull('deleted_at')
            ->count();

        $logger->setTotalSource($totalCount);
        $this->info("Found {$totalCount} legacy users to migrate.");

        if ($skipExisting && count($existingUserIds) > 0) {
            $this->info('Skip-existing mode: will skip '.count($existingUserIds).' already-migrated users.');
        }

        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        // Pre-load valid centre IDs for validation
        $validCentreIds = DB::table('centres')->pluck('id')->toArray();

        DB::connection('legacy')
            ->table('1_users')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunk($chunkSize, function ($legacyUsers) use ($tenantId, $dryRun, $logger, $bar, $validCentreIds, $skipExisting, $existingUserIds) {
                foreach ($legacyUsers as $legacy) {
                    try {
                        if ($skipExisting && in_array($legacy->id, $existingUserIds)) {
                            $logger->incrementSkipped();
                            $bar->advance();

                            continue;
                        }

                        $this->migrateUser($legacy, $tenantId, $dryRun, $validCentreIds);
                        $logger->incrementMigrated();
                    } catch (\Exception $e) {
                        $logger->logError("User {$legacy->id} ({$legacy->email}): {$e->getMessage()}", $legacy->id);
                        $this->newLine();
                        $this->error("Error migrating user {$legacy->id}: {$e->getMessage()}");
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
        ]);

        return Command::SUCCESS;
    }

    /**
     * Migrate a single legacy user and all related data.
     *
     * @param  array<int>  $validCentreIds
     */
    private function migrateUser(object $legacy, int $tenantId, bool $dryRun, array $validCentreIds): void
    {
        // Build meta_data JSON
        $metaData = $this->buildMetaData($legacy);

        // 1. Insert/update users table
        $userData = [
            'id' => $legacy->id,
            'name' => $legacy->name,
            'email' => $legacy->email,
            'email_verified_at' => $legacy->email_verified_at,
            'password' => $legacy->password,
            'remember_token' => $legacy->remember_token,
            'current_tenant_id' => $tenantId,
            'meta_data' => ! empty($metaData) ? json_encode($metaData) : null,
            'created_at' => $legacy->created_at,
            'updated_at' => $legacy->updated_at ?? now(),
        ];

        if (! $dryRun) {
            DB::table('users')->updateOrInsert(
                ['id' => $legacy->id],
                $userData
            );
        }

        // 2. Create user_profiles record
        $this->migrateUserProfile($legacy, $dryRun);

        // 3. Create user_addresses record
        $this->migrateUserAddress($legacy, $dryRun);

        // 4. Create user_office_infos record
        $this->migrateUserOfficeInfo($legacy, $dryRun);

        // 5. Create family_members record from spouse data
        $this->migrateSpouseToFamilyMember($legacy, $dryRun);

        // 6. Attach user to tenant
        $this->attachToTenant($legacy->id, $tenantId, $dryRun, $validCentreIds, $legacy->preschool);

        // 7. Attach user to centres
        $this->attachToCentres($legacy, $dryRun, $validCentreIds);
    }

    /**
     * Build the meta_data JSON from legacy user fields.
     *
     * @return array<string, mixed>
     */
    private function buildMetaData(object $legacy): array
    {
        $metaData = [];

        // Legacy user status
        if ($legacy->user_status !== null) {
            $metaData['legacy_user_status'] = (int) $legacy->user_status;
            $metaData['legacy_user_status_name'] = StatusMapper::userStatusName((int) $legacy->user_status);
        }

        // Legacy ID reference
        $metaData['legacy_id'] = $legacy->id;

        // Discount configuration
        $discountConfig = [];
        $hasDiscount = false;

        if (! empty($legacy->discount_by_month) && $legacy->discount_by_month !== '[]') {
            $discountConfig['discount_by_month'] = json_decode($legacy->discount_by_month, true);
            $hasDiscount = true;
        }
        if (! empty($legacy->discount_by_month_amount)) {
            $discountConfig['discount_by_month_amount'] = $legacy->discount_by_month_amount;
            $hasDiscount = true;
        }
        if (! empty($legacy->discount_by_month_reason)) {
            $discountConfig['discount_by_month_reason'] = $legacy->discount_by_month_reason;
            $hasDiscount = true;
        }
        if (! empty($legacy->discount_by_month_year) && $legacy->discount_by_month_year !== '[]') {
            $discountConfig['discount_by_month_year'] = json_decode($legacy->discount_by_month_year, true);
            $hasDiscount = true;
        }
        if (! empty($legacy->monthly_discount_amount)) {
            $discountConfig['monthly_discount_amount'] = $legacy->monthly_discount_amount;
            $hasDiscount = true;
        }
        if (! empty($legacy->monthly_discount_reason)) {
            $discountConfig['monthly_discount_reason'] = $legacy->monthly_discount_reason;
            $hasDiscount = true;
        }
        if (! empty($legacy->discount_histories) && $legacy->discount_histories !== '[]') {
            $discountConfig['discount_histories'] = json_decode($legacy->discount_histories, true);
            $hasDiscount = true;
        }

        if ($hasDiscount) {
            $metaData['discount_config'] = $discountConfig;
        }

        // Guardians
        if (! empty($legacy->guardians) && $legacy->guardians !== '[]' && $legacy->guardians !== '') {
            $guardians = json_decode($legacy->guardians, true);
            if (! empty($guardians)) {
                $metaData['legacy_guardians'] = $guardians;
            }
        }

        return $metaData;
    }

    /**
     * Migrate user profile data (ic_no, phone_no, occupation).
     */
    private function migrateUserProfile(object $legacy, bool $dryRun): void
    {
        $hasProfileData = ! empty($legacy->ic_no) || ! empty($legacy->phone_no) || ! empty($legacy->occupation);

        if (! $hasProfileData) {
            return;
        }

        if ($dryRun) {
            return;
        }

        DB::table('user_profiles')->updateOrInsert(
            ['user_id' => $legacy->id],
            [
                'nric' => $legacy->ic_no ?: null,
                'phone' => $legacy->phone_no ?: null,
                'occupation' => $legacy->occupation ?: null,
                'created_at' => $legacy->created_at,
                'updated_at' => $legacy->updated_at ?? now(),
            ]
        );
    }

    /**
     * Migrate user address data (add_1, add_2, city, postcode, state).
     */
    private function migrateUserAddress(object $legacy, bool $dryRun): void
    {
        $hasAddressData = ! empty($legacy->add_1) || ! empty($legacy->add_2)
            || ! empty($legacy->city) || ! empty($legacy->postcode) || ! empty($legacy->state);

        if (! $hasAddressData) {
            return;
        }

        if ($dryRun) {
            return;
        }

        $stateCode = StatusMapper::state($legacy->state);

        DB::table('user_addresses')->updateOrInsert(
            ['user_id' => $legacy->id],
            [
                'address' => $legacy->add_1 ?: null,
                'address_2' => $legacy->add_2 ?: null,
                'city' => $legacy->city ?: null,
                'postal_code' => $legacy->postcode ?: null,
                'state_code' => $stateCode,
                'created_at' => $legacy->created_at,
                'updated_at' => $legacy->updated_at ?? now(),
            ]
        );
    }

    /**
     * Migrate user office/company info.
     */
    private function migrateUserOfficeInfo(object $legacy, bool $dryRun): void
    {
        $hasOfficeData = ! empty($legacy->company_name) || ! empty($legacy->company_add_1)
            || ! empty($legacy->company_phone) || ! empty($legacy->company_city);

        if (! $hasOfficeData) {
            return;
        }

        if ($dryRun) {
            return;
        }

        $officeStateCode = StatusMapper::state($legacy->company_state);

        DB::table('user_office_infos')->updateOrInsert(
            ['user_id' => $legacy->id],
            [
                'office_name' => $legacy->company_name ?: null,
                'office_phone' => $legacy->company_phone ?: null,
                'office_address' => $legacy->company_add_1 ?: null,
                'office_address_2' => $legacy->company_add_2 ?: null,
                'office_city' => $legacy->company_city ?: null,
                'office_postal_code' => $legacy->company_postcode ?: null,
                'office_state_code' => $officeStateCode,
                'created_at' => $legacy->created_at,
                'updated_at' => $legacy->updated_at ?? now(),
            ]
        );
    }

    /**
     * Migrate spouse data to family_members table.
     */
    private function migrateSpouseToFamilyMember(object $legacy, bool $dryRun): void
    {
        if (empty($legacy->spouse_name)) {
            return;
        }

        if ($dryRun) {
            return;
        }

        $spouseStateCode = StatusMapper::state($legacy->spouse_state);

        $spouseOfficeStateCode = StatusMapper::state($legacy->spouse_company_state);

        DB::table('family_members')->updateOrInsert(
            [
                'user_id' => $legacy->id,
                'relationship_type' => 'spouse',
            ],
            [
                'name' => $legacy->spouse_name,
                'nric' => $legacy->spouse_ic_no ?: null,
                'phone' => $legacy->spouse_phone_no ?: null,
                'email' => $legacy->spouse_email ?: null,
                'occupation' => $legacy->spouse_occupation ?: null,
                'address' => $legacy->spouse_add_1 ?: null,
                'address_2' => $legacy->spouse_add_2 ?: null,
                'city' => $legacy->spouse_city ?: null,
                'postal_code' => $legacy->spouse_postcode ?: null,
                'state_code' => $spouseStateCode,
                'office_address' => $legacy->spouse_company_add_1 ?: null,
                'office_address_2' => $legacy->spouse_company_add_2 ?: null,
                'office_city' => $legacy->spouse_company_city ?: null,
                'office_postal_code' => $legacy->spouse_company_postcode ?: null,
                'office_state_code' => $spouseOfficeStateCode,
                'created_at' => $legacy->created_at,
                'updated_at' => $legacy->updated_at ?? now(),
            ]
        );
    }

    /**
     * Attach user to tenant via tenant_user pivot.
     *
     * @param  array<int>  $validCentreIds
     */
    private function attachToTenant(int $userId, int $tenantId, bool $dryRun, array $validCentreIds, ?int $preschoolId): void
    {
        if ($dryRun) {
            return;
        }

        // Determine current_centre_id from user's primary preschool
        $currentCentreId = null;
        if ($preschoolId && in_array($preschoolId, $validCentreIds)) {
            $currentCentreId = $preschoolId;
        }

        DB::table('tenant_user')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
            ],
            [
                'current_centre_id' => $currentCentreId,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Attach user to centres via centre_user pivot (from preschool + other_preschools).
     *
     * @param  array<int>  $validCentreIds
     */
    private function attachToCentres(object $legacy, bool $dryRun, array $validCentreIds): void
    {
        if ($dryRun) {
            return;
        }

        $centreIds = [];

        // Primary preschool
        if (! empty($legacy->preschool) && $legacy->preschool > 0) {
            $centreIds[] = (int) $legacy->preschool;
        }

        // Other preschools (JSON array)
        if (! empty($legacy->other_preschools) && $legacy->other_preschools !== '[]') {
            $others = json_decode($legacy->other_preschools, true);
            if (is_array($others)) {
                foreach ($others as $otherId) {
                    if (is_numeric($otherId) && (int) $otherId > 0) {
                        $centreIds[] = (int) $otherId;
                    }
                }
            }
        }

        // Deduplicate
        $centreIds = array_unique($centreIds);

        foreach ($centreIds as $centreId) {
            if (! in_array($centreId, $validCentreIds)) {
                OrphanLogger::log(
                    '1_users',
                    $legacy->id,
                    "centre_id {$centreId} not found in centres table",
                    ['user_id' => $legacy->id, 'centre_id' => $centreId]
                );

                continue;
            }

            DB::table('centre_user')->updateOrInsert(
                [
                    'centre_id' => $centreId,
                    'user_id' => $legacy->id,
                ],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * Migrate user roles from legacy model_has_roles.
     */
    private function migrateRoles(bool $dryRun): void
    {
        $this->newLine();
        $this->info('--- Migrating User Roles ---');

        $logger = new MigrationLogger('phase_2a', '1_model_has_roles', 'model_has_roles');

        // Get all legacy role assignments for non-deleted users
        $legacyRoles = DB::connection('legacy')
            ->table('1_model_has_roles')
            ->join('1_users', '1_users.id', '=', '1_model_has_roles.model_id')
            ->where('1_model_has_roles.model_type', 'App\\User')
            ->whereNull('1_users.deleted_at')
            ->select('1_model_has_roles.*')
            ->get();

        $logger->setTotalSource($legacyRoles->count());
        $this->info("Found {$legacyRoles->count()} legacy role assignments to migrate.");

        // Pre-load target role IDs by name
        $targetRoles = DB::table('roles')
            ->where('guard_name', 'web')
            ->pluck('id', 'name')
            ->toArray();

        $bar = $this->output->createProgressBar($legacyRoles->count());
        $bar->start();

        foreach ($legacyRoles as $legacyRole) {
            try {
                $targetRoleName = MigrateLegacyRoles::getTargetRoleName((int) $legacyRole->role_id);

                // Skip role ID 10 (Application)
                if ($targetRoleName === null) {
                    $logger->incrementSkipped();
                    $bar->advance();

                    continue;
                }

                $targetRoleId = $targetRoles[$targetRoleName] ?? null;

                if ($targetRoleId === null) {
                    $logger->logError("Target role '{$targetRoleName}' not found for legacy role_id {$legacyRole->role_id}", $legacyRole->model_id);
                    $bar->advance();

                    continue;
                }

                if (! $dryRun) {
                    // Check if assignment already exists (avoid duplicate key)
                    $exists = DB::table('model_has_roles')
                        ->where('role_id', $targetRoleId)
                        ->where('model_type', 'App\\Models\\User')
                        ->where('model_id', $legacyRole->model_id)
                        ->exists();

                    if (! $exists) {
                        DB::table('model_has_roles')->insert([
                            'role_id' => $targetRoleId,
                            'model_type' => 'App\\Models\\User',
                            'model_id' => $legacyRole->model_id,
                        ]);
                    }
                }

                $logger->incrementMigrated();
            } catch (\Exception $e) {
                $logger->logError("Role assignment for user {$legacyRole->model_id}: {$e->getMessage()}", $legacyRole->model_id);
                $this->newLine();
                $this->error("Error migrating role for user {$legacyRole->model_id}: {$e->getMessage()}");
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
            ['Skipped', $log->total_skipped],
            ['Errors', $log->total_errors],
        ]);
    }
}
