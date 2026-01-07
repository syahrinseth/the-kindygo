<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Find all users with incomplete profiles
        $incompleteUsers = User::where('profile_completed', false)
            ->whereNull('registration_token')
            ->with(['profile', 'address', 'officeInfo', 'centres'])
            ->get();

        foreach ($incompleteUsers as $user) {
            // Generate registration token
            $registrationToken = Str::random(40);
            $tokenExpiry = now()->addDays(30);

            // Determine registration step based on existing data
            $registrationStep = $this->determineRegistrationStep($user);

            // Build registration data from existing information
            $registrationData = $this->buildRegistrationData($user, $registrationStep);

            // Update user with migration data
            $user->update([
                'registration_step' => $registrationStep,
                'registration_token' => $registrationToken,
                'registration_token_expires_at' => $tokenExpiry,
                'registration_data' => $registrationData,
            ]);

            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'registration_step' => $registrationStep,
                    'registration_token' => $registrationToken,
                    'registration_token_expires_at' => $tokenExpiry,
                    'registration_data' => json_encode($registrationData),
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Determine the registration step based on existing user data.
     */
    protected function determineRegistrationStep(User $user): int
    {
        // If user has basic info and centres, they've completed at least step 1
        if ($user->profile && $user->centres->isNotEmpty()) {
            // If user has address info, they've completed step 2
            if ($user->address) {
                // Check if user has children
                if ($user->children()->exists()) {
                    // Has children, assume they need to complete step 4 (agreement)
                    return 3;
                }

                // No children, they're on step 3
                return 2;
            }

            // Has profile and centres but no address
            return 1;
        }

        // No complete data, start from beginning
        return 1;
    }

    /**
     * Build registration data array from existing user information.
     */
    protected function buildRegistrationData(User $user, int $currentStep): array
    {
        $data = [];

        // Step 1 data
        if ($user->profile || $user->centres->isNotEmpty()) {
            $data['step_1'] = [
                'name' => $user->name,
                'email' => $user->email,
                'mykad_number' => $user->profile?->nric,
                'phone' => $user->profile?->phone,
                'centre_ids' => $user->centres->pluck('id')->toArray(),
                'completed_at' => $user->created_at?->toDateTimeString(),
            ];
        }

        // Step 2 data
        if ($user->address && $currentStep >= 2) {
            $data['step_2'] = [
                'occupation' => $user->profile?->occupation,
                'address' => $user->address?->address,
                'postal_code' => $user->address?->postal_code,
                'city' => $user->address?->city,
                'state' => $user->address?->state_code,
                'office_name' => $user->officeInfo?->name,
                'office_address' => $user->officeInfo?->address,
                'office_postal_code' => $user->officeInfo?->postal_code,
                'office_city' => $user->officeInfo?->city,
                'office_state' => $user->officeInfo?->state_code,
                'has_profile_photo' => $user->getMedia('photo')->isNotEmpty(),
                'has_mykad_image' => $user->getMedia('mykad')->isNotEmpty(),
                'has_immunization_card' => $user->getMedia('immunization')->isNotEmpty(),
                'information_confirmed' => true,
                'completed_at' => $user->address?->created_at?->toDateTimeString(),
            ];
        }

        // Step 3 data
        if ($user->children()->exists() && $currentStep >= 3) {
            $children = $user->children->map(function ($child) {
                return [
                    'first_name' => $child->first_name,
                    'last_name' => $child->last_name,
                    'date_of_birth' => $child->date_of_birth?->format('Y-m-d'),
                    'gender' => $child->gender,
                ];
            })->toArray();

            $data['step_3'] = [
                'children' => $children,
                'children_count' => count($children),
                'completed_at' => now()->toDateTimeString(),
            ];
        }

        return $data;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reset registration tracking fields for migrated users
        DB::table('users')
            ->where('profile_completed', false)
            ->whereNotNull('registration_token')
            ->update([
                'registration_step' => 0,
                'registration_token' => null,
                'registration_token_expires_at' => null,
                'registration_data' => null,
                'updated_at' => now(),
            ]);
    }
};
