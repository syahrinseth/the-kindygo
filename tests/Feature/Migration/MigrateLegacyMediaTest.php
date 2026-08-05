<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Traits\LegacyMigrationTestHelper;

uses(LegacyMigrationTestHelper::class);

beforeEach(function () {
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
