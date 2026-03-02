<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\DB;

class MigrationLogger
{
    protected int $logId;

    public function __construct(
        public string $phase,
        public string $sourceTable,
        public string $targetTable,
    ) {
        $this->logId = DB::table('migration_logs')->insertGetId([
            'phase' => $this->phase,
            'source_table' => $this->sourceTable,
            'target_table' => $this->targetTable,
            'total_source' => 0,
            'total_migrated' => 0,
            'total_skipped' => 0,
            'total_errors' => 0,
            'errors' => json_encode([]),
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Update the total source count.
     */
    public function setTotalSource(int $count): void
    {
        DB::table('migration_logs')
            ->where('id', $this->logId)
            ->update(['total_source' => $count, 'updated_at' => now()]);
    }

    /**
     * Increment the migrated count.
     */
    public function incrementMigrated(int $count = 1): void
    {
        DB::table('migration_logs')
            ->where('id', $this->logId)
            ->increment('total_migrated', $count, ['updated_at' => now()]);
    }

    /**
     * Increment the skipped count.
     */
    public function incrementSkipped(int $count = 1): void
    {
        DB::table('migration_logs')
            ->where('id', $this->logId)
            ->increment('total_skipped', $count, ['updated_at' => now()]);
    }

    /**
     * Log an error and increment the error count.
     */
    public function logError(string $message, ?int $sourceId = null): void
    {
        $log = DB::table('migration_logs')->where('id', $this->logId)->first();
        $errors = json_decode($log->errors ?? '[]', true);
        $errors[] = [
            'message' => $message,
            'source_id' => $sourceId,
            'timestamp' => now()->toIso8601String(),
        ];

        DB::table('migration_logs')
            ->where('id', $this->logId)
            ->update([
                'total_errors' => count($errors),
                'errors' => json_encode($errors),
                'updated_at' => now(),
            ]);
    }

    /**
     * Mark the migration as completed.
     */
    public function complete(): void
    {
        DB::table('migration_logs')
            ->where('id', $this->logId)
            ->update([
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Get the log ID.
     */
    public function getLogId(): int
    {
        return $this->logId;
    }

    /**
     * Get the current log record.
     */
    public function getLog(): \stdClass
    {
        return DB::table('migration_logs')->where('id', $this->logId)->first();
    }

    /**
     * Clear previous logs for the same phase and tables (for re-runs).
     */
    public static function clearPreviousLogs(string $phase, string $sourceTable, string $targetTable): void
    {
        DB::table('migration_logs')
            ->where('phase', $phase)
            ->where('source_table', $sourceTable)
            ->where('target_table', $targetTable)
            ->where('id', '!=', 0)
            ->delete();
    }
}
