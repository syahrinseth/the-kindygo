<?php

use App\Services\Migration\MigrationLogger;
use Illuminate\Support\Facades\DB;

it('caps stored error samples while retaining the total error count', function () {
    $logger = new MigrationLogger('test', 'legacy_files', 'media');

    foreach (range(1, 101) as $sourceId) {
        $logger->logError("Failure {$sourceId}", $sourceId);
    }

    $log = DB::table('migration_logs')->find($logger->getLogId());

    expect($log->total_errors)->toBe(101);
    expect(json_decode($log->errors, true))->toHaveCount(100);
});
