<?php

use Illuminate\Support\Facades\Schema;

it('drops the direct centre child pivot and recreates an empty table on rollback', function () {
    $migration = require database_path('migrations/2026_08_17_054550_drop_centre_child_table.php');

    expect(Schema::hasTable('centre_child'))->toBeFalse();

    $migration->down();

    expect(Schema::hasTable('centre_child'))->toBeTrue()
        ->and(Schema::hasColumns('centre_child', ['id', 'centre_id', 'child_id', 'created_at', 'updated_at']))->toBeTrue();

    $migration->up();

    expect(Schema::hasTable('centre_child'))->toBeFalse();
});
