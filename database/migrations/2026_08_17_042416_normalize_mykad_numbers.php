<?php

use App\Support\MyKadNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('user_profiles')
            ->orderBy('id')
            ->chunkById(100, function ($profiles): void {
                foreach ($profiles as $profile) {
                    $nric = MyKadNumber::format($profile->nric);

                    if ($nric !== $profile->nric) {
                        DB::table('user_profiles')->where('id', $profile->id)->update(['nric' => $nric]);
                    }
                }
            });

        DB::table('family_members')
            ->orderBy('id')
            ->chunkById(100, function ($familyMembers): void {
                foreach ($familyMembers as $familyMember) {
                    $nric = MyKadNumber::format($familyMember->nric);

                    if ($nric !== $familyMember->nric) {
                        DB::table('family_members')->where('id', $familyMember->id)->update(['nric' => $nric]);
                    }
                }
            });

        DB::table('users')
            ->whereNotNull('registration_data')
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $registrationData = json_decode($user->registration_data, true);

                    if (! is_array($registrationData) || ! isset($registrationData['step_1']['mykad_number'])) {
                        continue;
                    }

                    $myKadNumber = MyKadNumber::format($registrationData['step_1']['mykad_number']);

                    if ($myKadNumber === $registrationData['step_1']['mykad_number']) {
                        continue;
                    }

                    $registrationData['step_1']['mykad_number'] = $myKadNumber;

                    DB::table('users')->where('id', $user->id)->update([
                        'registration_data' => json_encode($registrationData),
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The original separator format cannot be recovered safely.
    }
};
