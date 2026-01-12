<?php

use App\Actions\Registration\UpdateParentDetailsAction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    $this->action = new UpdateParentDetailsAction;
    $this->user = User::factory()->create();

    Storage::fake('public');
});

it('creates UserProfile with occupation', function () {
    $validated = [
        'occupation' => 'Software Engineer',
        'address' => '123 Main St',
        'postal_code' => '50000',
        'city' => 'Kuala Lumpur',
        'state' => '14',
        'information_confirmed' => true,
    ];

    $this->action->execute($this->user, $validated);

    assertDatabaseHas('user_profiles', [
        'user_id' => $this->user->id,
        'occupation' => 'Software Engineer',
    ]);
});

it('creates UserAddress with all required fields', function () {
    $validated = [
        'address' => '123 Jalan Test',
        'address_2' => 'Taman Testing',
        'postal_code' => '47800',
        'city' => 'Petaling Jaya',
        'state' => '10',
        'information_confirmed' => true,
    ];

    $this->action->execute($this->user, $validated);

    assertDatabaseHas('user_addresses', [
        'user_id' => $this->user->id,
        'address' => '123 Jalan Test',
        'address_2' => 'Taman Testing',
        'postal_code' => '47800',
        'city' => 'Petaling Jaya',
        'state_code' => '10',
    ]);
});

it('creates UserOfficeInfo when office address provided', function () {
    $validated = [
        'address' => '123 Main St',
        'postal_code' => '50000',
        'city' => 'Kuala Lumpur',
        'state' => '14',
        'office_address' => '456 Office Tower',
        'office_address_2' => 'Suite 10',
        'office_postal_code' => '50100',
        'office_city' => 'Kuala Lumpur',
        'office_state' => '14',
        'information_confirmed' => true,
    ];

    $this->action->execute($this->user, $validated);

    assertDatabaseHas('user_office_infos', [
        'user_id' => $this->user->id,
        'office_address' => '456 Office Tower',
        'office_address_2' => 'Suite 10',
        'office_postal_code' => '50100',
        'office_city' => 'Kuala Lumpur',
        'office_state_code' => '14',
    ]);
});

it('handles optional file uploads', function () {
    $profilePhoto = UploadedFile::fake()->image('photo.jpg');
    $mykadImage = UploadedFile::fake()->image('mykad.jpg');
    $immunizationCard = UploadedFile::fake()->create('immunization.pdf', 1000);

    $validated = [
        'address' => '123 Main St',
        'postal_code' => '50000',
        'city' => 'Kuala Lumpur',
        'state' => '14',
        'profile_photo' => $profilePhoto,
        'mykad_image' => $mykadImage,
        'immunization_card' => $immunizationCard,
        'information_confirmed' => true,
    ];

    $this->action->execute($this->user, $validated);

    expect($this->user->getMedia('photo'))->toHaveCount(1);
    expect($this->user->getMedia('mykad'))->toHaveCount(1);
    expect($this->user->getMedia('immunization'))->toHaveCount(1);
});

it('skips file uploads when not provided', function () {
    $validated = [
        'address' => '123 Main St',
        'postal_code' => '50000',
        'city' => 'Kuala Lumpur',
        'state' => '14',
        'information_confirmed' => true,
    ];

    $this->action->execute($this->user, $validated);

    expect($this->user->getMedia('photo'))->toHaveCount(0);
    expect($this->user->getMedia('mykad'))->toHaveCount(0);
    expect($this->user->getMedia('immunization'))->toHaveCount(0);
});

it('sets registration_step to 2', function () {
    $validated = [
        'address' => '123 Main St',
        'postal_code' => '50000',
        'city' => 'Kuala Lumpur',
        'state' => '14',
        'information_confirmed' => true,
    ];

    $this->action->execute($this->user, $validated);

    $this->user->refresh();

    expect($this->user->registration_step)->toBe(2);
});

it('updates registration_data correctly', function () {
    $validated = [
        'occupation' => 'Doctor',
        'address' => '789 Health St',
        'postal_code' => '60000',
        'city' => 'Kuala Lumpur',
        'state' => '14',
        'information_confirmed' => true,
    ];

    $this->action->execute($this->user, $validated);

    $this->user->refresh();

    $stepData = $this->user->getRegistrationData('step_2');

    expect($stepData)->toBeArray();
    expect($stepData['occupation'])->toBe('Doctor');
    expect($stepData['address'])->toBe('789 Health St');
    expect($stepData['city'])->toBe('Kuala Lumpur');
});

it('does not create office info when office address is empty', function () {
    $validated = [
        'address' => '123 Main St',
        'postal_code' => '50000',
        'city' => 'Kuala Lumpur',
        'state' => '14',
        'information_confirmed' => true,
    ];

    $this->action->execute($this->user, $validated);

    expect($this->user->officeInfo)->toBeNull();
});
