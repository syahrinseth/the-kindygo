<?php

use App\Models\Centre;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates and cleans test CHIP payments using the current invoice schema', function (): void {
    $tenant = Tenant::factory()->create();
    $centre = Centre::factory()->create(['tenant_id' => $tenant->id]);
    User::factory()->create([
        'current_tenant_id' => $tenant->id,
    ]);

    $this->artisan('payments:create-test-chip')
        ->assertExitCode(0);

    expect(Invoice::query()->where('number', 'like', 'TEST-%')->count())->toBe(1)
        ->and(Payment::query()->where('reference_no', 'like', 'TEST-CHIP-%')->count())->toBe(2)
        ->and(Payment::query()->whereHas('centres', fn ($query) => $query->whereKey($centre->id))->count())->toBe(2);

    $this->artisan('payments:create-test-chip', ['--clean' => true])
        ->assertExitCode(0);

    expect(Invoice::query()->where('number', 'like', 'TEST-%')->count())->toBe(0)
        ->and(Payment::query()->where('reference_no', 'like', 'TEST-CHIP-%')->count())->toBe(0);
});
