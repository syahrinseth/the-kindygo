<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserAddress;
use App\Models\UserOfficeInfo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateUserDataToSeparateTables extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:migrate-to-separate-tables';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing user data to separate profile, address, and office info tables';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting migration of user data to separate tables...');

        // Since the old fields were already removed from users table in the migration,
        // this command is mainly for future reference or if we need to rollback and migrate again
        
        $users = User::all();
        $this->info("Found {$users->count()} users to process.");

        $migratedCount = 0;

        foreach ($users as $user) {
            try {
                // Create user profile if it doesn't exist
                if (!$user->profile) {
                    UserProfile::create([
                        'user_id' => $user->id,
                        'nric' => null, // These would be populated if we had backup data
                        'passport' => null,
                        'phone' => null,
                        'occupation' => null,
                    ]);
                }

                // Create user address if it doesn't exist
                if (!$user->userAddress) {
                    UserAddress::create([
                        'user_id' => $user->id,
                        'address' => null,
                        'address_2' => null,
                        'city' => null,
                        'postal_code' => null,
                        'state_code' => null,
                    ]);
                }

                // Create user office info if it doesn't exist
                if (!$user->officeInfo) {
                    UserOfficeInfo::create([
                        'user_id' => $user->id,
                        'office_phone' => null,
                        'office_address' => null,
                        'office_address_2' => null,
                        'office_city' => null,
                        'office_postal_code' => null,
                        'office_state_code' => null,
                    ]);
                }

                $migratedCount++;

            } catch (\Exception $e) {
                $this->error("Failed to migrate user {$user->id}: " . $e->getMessage());
            }
        }

        $this->info("Successfully created related records for {$migratedCount} users.");
        $this->info('Migration completed!');

        return Command::SUCCESS;
    }
}
