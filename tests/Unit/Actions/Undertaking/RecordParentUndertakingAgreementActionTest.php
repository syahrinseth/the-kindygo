<?php

use App\Actions\Undertaking\RecordParentUndertakingAgreementAction;
use App\Models\LetterOfUndertaking;
use App\Models\ParentUndertakingAgreement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->action = new RecordParentUndertakingAgreementAction;
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create();
    $this->letter = LetterOfUndertaking::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
});

it('creates agreement record with all required fields', function () {
    $request = Request::create('/test', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.1.1']);

    $agreement = $this->action->execute($this->user, $this->letter, $this->tenant, $request);

    expect($agreement)->toBeInstanceOf(ParentUndertakingAgreement::class);
    expect($agreement->user_id)->toBe($this->user->id);
    expect($agreement->letter_of_undertaking_id)->toBe($this->letter->id);
    expect($agreement->tenant_id)->toBe($this->tenant->id);
    expect($agreement->agreed_at)->not->toBeNull();
    expect($agreement->ip_address)->toBe('192.168.1.1');
});

it('captures IP address from request', function () {
    $request = Request::create('/test', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.5']);

    $agreement = $this->action->execute($this->user, $this->letter, $this->tenant, $request);

    expect($agreement->ip_address)->toBe('10.0.0.5');
});

it('sets agreed_at timestamp to current time', function () {
    $request = Request::create('/test');

    Carbon::setTestNow($now = now());

    $agreement = $this->action->execute($this->user, $this->letter, $this->tenant, $request);

    expect($agreement->agreed_at->timestamp)->toBe($now->timestamp);
});

it('persists agreement to database', function () {
    $request = Request::create('/test', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.1.100']);

    $agreement = $this->action->execute($this->user, $this->letter, $this->tenant, $request);

    $this->assertDatabaseHas('parent_undertaking_agreements', [
        'id' => $agreement->id,
        'user_id' => $this->user->id,
        'letter_of_undertaking_id' => $this->letter->id,
        'tenant_id' => $this->tenant->id,
        'ip_address' => '192.168.1.100',
    ]);
});

it('handles multiple tenants for same user', function () {
    $tenant2 = Tenant::factory()->create();
    $letter2 = LetterOfUndertaking::factory()->create([
        'tenant_id' => $tenant2->id,
    ]);

    $request = Request::create('/test');

    $agreement1 = $this->action->execute($this->user, $this->letter, $this->tenant, $request);
    $agreement2 = $this->action->execute($this->user, $letter2, $tenant2, $request);

    expect($agreement1->tenant_id)->toBe($this->tenant->id);
    expect($agreement2->tenant_id)->toBe($tenant2->id);

    $this->assertDatabaseCount('parent_undertaking_agreements', 2);
});
