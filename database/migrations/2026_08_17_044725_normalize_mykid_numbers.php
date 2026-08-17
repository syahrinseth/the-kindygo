<?php

use App\Support\MalaysianIdentificationNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('children')
            ->orderBy('id')
            ->chunkById(100, function ($children): void {
                foreach ($children as $child) {
                    $myKidNumber = MalaysianIdentificationNumber::format($child->mykid_no);

                    if ($myKidNumber !== $child->mykid_no) {
                        DB::table('children')->where('id', $child->id)->update(['mykid_no' => $myKidNumber]);
                    }
                }
            });

        DB::table('users')
            ->whereNotNull('registration_data')
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $registrationData = json_decode($user->registration_data, true);

                    if (! is_array($registrationData) || ! isset($registrationData['step_3']['children']) || ! is_array($registrationData['step_3']['children'])) {
                        continue;
                    }

                    $hasChanges = false;

                    foreach ($registrationData['step_3']['children'] as $index => $child) {
                        if (! is_array($child) || ! array_key_exists('mykid_no', $child)) {
                            continue;
                        }

                        $myKidNumber = MalaysianIdentificationNumber::format($child['mykid_no']);

                        if ($myKidNumber !== $child['mykid_no']) {
                            $registrationData['step_3']['children'][$index]['mykid_no'] = $myKidNumber;
                            $hasChanges = true;
                        }
                    }

                    if ($hasChanges) {
                        DB::table('users')->where('id', $user->id)->update([
                            'registration_data' => json_encode($registrationData),
                        ]);
                    }
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
