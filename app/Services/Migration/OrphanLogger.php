<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\DB;

class OrphanLogger
{
    /**
     * Log an orphaned record that could not be migrated.
     *
     * @param  array<string, mixed>|null  $data  The original record data
     */
    public static function log(string $sourceTable, int $sourceId, string $reason, ?array $data = null): void
    {
        DB::table('migration_orphans')->insert([
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
            'reason' => $reason,
            'data' => $data ? json_encode($data) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Get all orphaned records for a specific source table.
     *
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    public static function getOrphans(string $sourceTable): \Illuminate\Support\Collection
    {
        return DB::table('migration_orphans')
            ->where('source_table', $sourceTable)
            ->get();
    }

    /**
     * Get total count of orphaned records for a specific source table.
     */
    public static function count(?string $sourceTable = null): int
    {
        $query = DB::table('migration_orphans');

        if ($sourceTable) {
            $query->where('source_table', $sourceTable);
        }

        return $query->count();
    }

    /**
     * Clear all orphan records for a specific source table (useful for re-runs).
     */
    public static function clear(?string $sourceTable = null): int
    {
        $query = DB::table('migration_orphans');

        if ($sourceTable) {
            $query->where('source_table', $sourceTable);
        }

        return $query->delete();
    }
}
