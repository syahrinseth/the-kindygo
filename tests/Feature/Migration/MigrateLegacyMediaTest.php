<?php

use App\Models\Child;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Traits\LegacyMigrationTestHelper;

uses(LegacyMigrationTestHelper::class);

beforeEach(function () {
    Storage::fake('private');

    $this->setUpLegacyDatabase();
    $this->createTestTenant();
    config([
        'legacy-migration.app_path' => storage_path('framework/testing/legacy-app'),
        'legacy-migration.website_uuid' => 'test-website-uuid',
    ]);
});

afterEach(function () {
    File::deleteDirectory(storage_path('framework/testing/legacy-app'));
    File::deleteDirectory(storage_path('media-library'));
});

it('migrates payment proof media from the documented nested legacy metadata', function () {
    $sourceDirectory = storage_path('framework/testing/legacy-app/storage/app/test-website-uuid/transactions/bills/payment_slips');
    File::ensureDirectoryExists($sourceDirectory);
    File::put($sourceDirectory.'/100.jpg', base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/Aaf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/Aaf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Ar//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IX//2gAMAwEAAgADAAAAEP/EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQMBAT8QH//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQIBAT8QH//EABQQAQAAAAAAAAAAAAAAAAAAABD/2gAIAQEAAT8QH//Z'));

    DB::table('payments')->insert([
        [
            'id' => 100,
            'tenant_id' => 1,
            'user_id' => 1,
            'gateway' => 'bank_transfer',
            'reference_no' => 'PAY-100',
            'status' => 'paid',
            'amount' => 100,
            'meta' => json_encode(['legacy' => ['transaction_type' => 'payment', 'payment_slip_path' => '/transactions/bills/payment_slips/100.jpg']]),
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => 101,
            'tenant_id' => 1,
            'user_id' => 1,
            'gateway' => 'bank_transfer',
            'reference_no' => 'PAY-101',
            'status' => 'paid',
            'amount' => 100,
            'meta' => json_encode(['payment_slip' => '/transactions/bills/payment_slips/100.jpg']),
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $this->artisan('migrate:legacy-media', ['--step' => 4, '--skip-existing' => true])
        ->assertSuccessful();

    expect(DB::table('media')->where('model_type', 'App\\Models\\Payment')->where('model_id', 100)->count())->toBe(1);
    expect(DB::table('media')->where('model_type', 'App\\Models\\Payment')->where('model_id', 101)->exists())->toBeFalse();
});

it('processes media in resumable ID batches without duplicating completed collections', function () {
    $sourceDirectory = storage_path('framework/testing/legacy-app/storage/app/test-website-uuid/children');
    $jpeg = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/Aaf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/Aaf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Ar//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IX//2gAMAwEAAgADAAAAEP/EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQMBAT8QH//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQIBAT8QH//EABQQAQAAAAAAAAAAAAAAAAAAABD/2gAIAQEAAT8QH//Z');

    $firstChild = Child::factory()->create(['id' => 10]);
    $secondChild = Child::factory()->create(['id' => 11]);

    foreach ([$firstChild, $secondChild] as $child) {
        $profileDirectory = $sourceDirectory.'/'.$child->id.'/profile';
        File::ensureDirectoryExists($profileDirectory);
        File::put($profileDirectory.'/passport_sized_image.jpg', $jpeg);
    }

    $this->artisan('migrate:legacy-media', [
        '--step' => 1,
        '--chunk' => 1,
        '--start-id' => 10,
    ])->assertSuccessful();

    expect(DB::table('media')->where('model_type', Child::class)->where('model_id', 10)->count())->toBe(0);
    expect(DB::table('media')->where('model_type', Child::class)->where('model_id', 11)->count())->toBe(1);

    $this->artisan('migrate:legacy-media', [
        '--step' => 1,
        '--chunk' => 1,
        '--skip-existing' => true,
    ])->assertSuccessful();

    expect(DB::table('media')->where('model_type', Child::class)->where('model_id', 10)->count())->toBe(1);
    expect(DB::table('media')->where('model_type', Child::class)->where('model_id', 11)->count())->toBe(1);
});

it('rejects unsafe media chunk sizes and invalid memory limits', function () {
    $this->artisan('migrate:legacy-media', ['--chunk' => 0])
        ->expectsOutput('The --chunk option must be an integer between 1 and 500.')
        ->assertFailed();

    $this->artisan('migrate:legacy-media', ['--memory-limit' => '512'])
        ->expectsOutput('The --memory-limit option must use K, M, or G units, for example 512M.')
        ->assertFailed();
});

it('forwards media resume options through the full migrator', function () {
    $sourceDirectory = storage_path('framework/testing/legacy-app/storage/app/test-website-uuid/children');
    $jpeg = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/Aaf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/Aaf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Ar//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IX//2gAMAwEAAgADAAAAEP/EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQMBAT8QH//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQIBAT8QH//EABQQAQAAAAAAAAAAAAAAAAAAABD/2gAIAQEAAT8QH//Z');
    $skippedChild = Child::factory()->create(['id' => 20]);
    $resumedChild = Child::factory()->create(['id' => 21]);

    foreach ([$skippedChild, $resumedChild] as $child) {
        $profileDirectory = $sourceDirectory.'/'.$child->id.'/profile';
        File::ensureDirectoryExists($profileDirectory);
        File::put($profileDirectory.'/passport_sized_image.jpg', $jpeg);
    }

    $this->artisan('migrate:legacy', [
        '--from-phase' => 4,
        '--to-phase' => 4,
        '--media-chunk' => 1,
        '--media-start-id' => 20,
        '--skip-existing' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(DB::table('media')->where('model_type', Child::class)->where('model_id', 20)->exists())->toBeFalse();
    expect(DB::table('media')->where('model_type', Child::class)->where('model_id', 21)->count())->toBe(1);
});
