<?php

use App\Enums\ApplicationRole;
use Database\Seeders\LegacyMigrationBootstrapSeeder;
use Illuminate\Support\Facades\DB;

it('creates only the dependencies required by the legacy migration', function () {
    $this->seed(LegacyMigrationBootstrapSeeder::class);

    expect(DB::table('tenants')->count())->toBe(1)
        ->and(DB::table('tenants')->where('id', 1)->value('user_id'))->toBe(1)
        ->and(DB::table('users')->count())->toBe(1)
        ->and(DB::table('roles')->count())->toBe(count(ApplicationRole::cases()))
        ->and(DB::table('campuses')->count())->toBe(0)
        ->and(DB::table('centres')->count())->toBe(0)
        ->and(DB::table('products')->count())->toBe(0)
        ->and(DB::table('invoices')->count())->toBe(0);
});
