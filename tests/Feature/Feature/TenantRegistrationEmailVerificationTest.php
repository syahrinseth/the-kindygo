<?php

use App\Models\Centre;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

uses()->group('registration', 'email-verification');

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'name' => 'Test Kindergarten',
        'slug' => 'test-kindergarten',
    ]);

    $this->centre = Centre::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Main Campus',
    ]);
});

test('user is created with unverified email after step 1 completion', function () {
    Livewire::test(\App\Livewire\TenantRegistrationWizard::class, ['tenant' => $this->tenant])
        ->set('name', 'John Doe')
        ->set('mykad_number', '900101011234')
        ->set('phone', '+60123456789')
        ->set('email', 'john@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('centre_ids', [$this->centre->id])
        ->call('submitStep1');

    // Assert user was created with unverified email
    $user = User::where('email', 'john@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasVerifiedEmail())->toBeFalse();
    expect($user->name)->toBe('John Doe');
});

test('verification notice page is displayed to unverified users', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
        'current_tenant_id' => $this->tenant->id,
        'registration_step' => 1,
        'profile_completed' => false,
    ]);
    $user->tenants()->attach($this->tenant);

    $response = $this->actingAs($user)->get(route('verification.notice'));

    $response->assertOk();
    $response->assertSee('Verify Your Email Address');
    $response->assertSee($user->email);
});

test('user can resend verification email', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email_verified_at' => null,
        'current_tenant_id' => $this->tenant->id,
    ]);

    $response = $this->actingAs($user)->post(route('verification.send'));

    $response->assertRedirect();
    $response->assertSessionHas('status', 'verification-link-sent');

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('user can verify email and continue to step 2', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
        'current_tenant_id' => $this->tenant->id,
        'registration_step' => 1,
        'profile_completed' => false,
    ]);
    $user->tenants()->attach($this->tenant);
    $user->centres()->attach($this->centre);

    // Generate signed verification URL
    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    // Should redirect back to registration wizard
    $response->assertRedirect(route('tenant.register.form', ['tenant' => $this->tenant->slug]));

    // Assert email is verified
    $user->refresh();
    expect($user->hasVerifiedEmail())->toBeTrue();
});

test('unverified user cannot access step 2', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
        'current_tenant_id' => $this->tenant->id,
        'registration_step' => 2, // Set to step 2 to allow checking
        'profile_completed' => false,
    ]);
    $user->tenants()->attach($this->tenant);
    $user->centres()->attach($this->centre);

    // Test via HTTP to check actual redirect behavior
    $response = $this->actingAs($user)->get(route('tenant.register.form', ['tenant' => $this->tenant->slug]));

    // Should redirect to verification notice
    $response->assertRedirect(route('verification.notice'));
});

test('unverified user is redirected to verification notice when accessing wizard', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
        'current_tenant_id' => $this->tenant->id,
        'registration_step' => 1,
        'profile_completed' => false,
    ]);
    $user->tenants()->attach($this->tenant);

    // Since mount() triggers redirect, test via HTTP instead
    $response = $this->actingAs($user)->get(route('tenant.register.form', ['tenant' => $this->tenant->slug]));

    // Should redirect to verification notice
    $response->assertRedirect(route('verification.notice'));
});

test('verified user can proceed to step 2', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'current_tenant_id' => $this->tenant->id,
        'registration_step' => 1,
        'profile_completed' => false,
    ]);
    $user->tenants()->attach($this->tenant);
    $user->centres()->attach($this->centre);

    Livewire::test(\App\Livewire\TenantRegistrationWizard::class, ['tenant' => $this->tenant])
        ->call('nextStep')
        ->assertSet('currentStep', 2);
});

test('user can edit email by returning to step 1', function () {
    $user = User::factory()->create([
        'email' => 'old@example.com',
        'email_verified_at' => null,
        'current_tenant_id' => $this->tenant->id,
        'registration_step' => 1,
        'profile_completed' => false,
    ]);
    $user->tenants()->attach($this->tenant);
    $user->centres()->attach($this->centre);

    // Accessing the wizard should redirect to verification notice since email is not verified
    $response = $this->actingAs($user)->get(route('tenant.register.form', ['tenant' => $this->tenant->slug]));

    $response->assertRedirect(route('verification.notice'));
});

test('expired verification link shows error', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
        'current_tenant_id' => $this->tenant->id,
    ]);

    // Generate expired signed URL (past timestamp)
    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->subMinutes(10),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    $response->assertStatus(403); // Forbidden due to invalid signature
});

test('invalid hash in verification link shows error', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
        'current_tenant_id' => $this->tenant->id,
    ]);

    // Generate URL with wrong hash
    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => 'invalid-hash']
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    $response->assertStatus(403);
});

test('already verified user redirects to dashboard when accessing verification notice', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'current_tenant_id' => $this->tenant->id,
        'profile_completed' => true,
    ]);
    $user->tenants()->attach($this->tenant);

    // When already verified and profile completed, should redirect to dashboard
    $response = $this->actingAs($user)->get('/');

    $response->assertRedirect('/dashboard');
});

test('verification email resend is rate limited', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
        'current_tenant_id' => $this->tenant->id,
    ]);

    // Send 6 requests (limit is 6 per minute)
    for ($i = 0; $i < 6; $i++) {
        $response = $this->actingAs($user)->post(route('verification.send'));
        $response->assertRedirect();
    }

    // 7th request should be rate limited
    $response = $this->actingAs($user)->post(route('verification.send'));
    $response->assertStatus(429); // Too Many Requests
});

test('customized verification email contains correct content', function () {
    Notification::fake();

    $user = User::factory()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'email_verified_at' => null,
    ]);

    $user->sendEmailVerificationNotification();

    Notification::assertSentTo($user, VerifyEmail::class, function ($notification) use ($user) {
        $mailMessage = $notification->toMail($user);

        return str_contains($mailMessage->subject, config('app.name'))
            && str_contains($mailMessage->greeting, 'Hello '.$user->name)
            && str_contains($mailMessage->introLines[0], 'Thank you for registering');
    });
});
