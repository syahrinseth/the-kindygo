<?php

namespace App\Console\Commands;

use App\Services\Migration\MigrationLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateLegacyRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:legacy-roles
                            {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create target roles and prepare role mapping for legacy user migration';

    /**
     * Legacy role ID → target role name mapping.
     *
     * @var array<int, string|null>
     */
    private const ROLE_MAPPING = [
        1 => 'Super Admin',
        2 => 'Admin',
        3 => 'Accountant',
        4 => 'Principal',
        5 => 'Accountant',
        6 => 'Teacher',
        7 => 'Parent',
        8 => 'Staff',
        9 => 'Staff',
        10 => null, // Application - skip
        11 => 'Auditor',
        12 => 'Owner',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $this->info('Starting legacy roles migration...');

        $logger = new MigrationLogger('phase_1', '1_roles', 'roles');

        // Step 1: Ensure all target roles exist
        $targetRoles = collect(self::ROLE_MAPPING)
            ->filter()
            ->unique()
            ->values();

        $logger->setTotalSource($targetRoles->count());

        $this->info("Ensuring {$targetRoles->count()} target roles exist...");

        foreach ($targetRoles as $roleName) {
            $exists = DB::table('roles')
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->exists();

            if (! $exists) {
                if (! $dryRun) {
                    DB::table('roles')->insert([
                        'name' => $roleName,
                        'guard_name' => 'web',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $this->line("  Created role: {$roleName}");
                } else {
                    $this->line("  Would create role: {$roleName}");
                }
                $logger->incrementMigrated();
            } else {
                $this->line("  Role exists: {$roleName}");
                $logger->incrementSkipped();
            }
        }

        // Step 2: Display the role mapping for reference
        $this->newLine();
        $this->info('Role mapping reference (legacy → target):');

        $mappingTable = [];
        foreach (self::ROLE_MAPPING as $legacyId => $targetName) {
            $legacyRole = DB::connection('legacy')
                ->table('1_roles')
                ->where('id', $legacyId)
                ->value('name');

            $mappingTable[] = [
                $legacyId,
                $legacyRole ?? 'Unknown',
                $targetName ?? 'SKIP',
            ];
        }

        $this->table(['Legacy ID', 'Legacy Name', 'Target Role'], $mappingTable);

        $logger->complete();

        $log = $logger->getLog();
        $this->newLine();
        $this->table(['Metric', 'Count'], [
            ['New Roles Created', $log->total_migrated],
            ['Already Existed', $log->total_skipped],
            ['Errors', $log->total_errors],
        ]);

        $this->info('Legacy roles migration completed!');

        return Command::SUCCESS;
    }

    /**
     * Get the target role name for a legacy role ID.
     */
    public static function getTargetRoleName(int $legacyRoleId): ?string
    {
        return self::ROLE_MAPPING[$legacyRoleId] ?? null;
    }
}
