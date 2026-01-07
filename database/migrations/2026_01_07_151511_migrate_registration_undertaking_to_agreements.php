<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get all users with completed registration and undertaking acceptance
        $users = DB::table('users')
            ->where('profile_completed', true)
            ->where('registration_step', 4)
            ->whereNotNull('registration_data')
            ->get();

        foreach ($users as $user) {
            $registrationData = json_decode($user->registration_data, true);

            // Check if step 4 has undertaking acceptance
            if (isset($registrationData['step_4']['undertaking_accepted'])
                && $registrationData['step_4']['undertaking_accepted'] === true) {

                // Determine agreed_at timestamp
                $agreedAt = $registrationData['step_4']['completed_at']
                    ?? $user->updated_at
                    ?? now()->toDateTimeString();

                // Get all tenants this user belongs to
                $tenants = DB::table('tenant_user')
                    ->where('user_id', $user->id)
                    ->pluck('tenant_id');

                // Create agreement records for each tenant
                foreach ($tenants as $tenantId) {
                    // Check if agreement already exists
                    $exists = DB::table('parent_undertaking_agreements')
                        ->where('user_id', $user->id)
                        ->where('tenant_id', $tenantId)
                        ->whereNull('letter_of_undertaking_id')
                        ->exists();

                    if (! $exists) {
                        DB::table('parent_undertaking_agreements')->insert([
                            'user_id' => $user->id,
                            'letter_of_undertaking_id' => null, // Legacy acceptance
                            'tenant_id' => $tenantId,
                            'agreed_at' => $agreedAt,
                            'ip_address' => null, // No historical IP data
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove migrated legacy agreements (where letter_of_undertaking_id is null)
        DB::table('parent_undertaking_agreements')
            ->whereNull('letter_of_undertaking_id')
            ->delete();
    }
};
