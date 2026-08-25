<?php

namespace App\Support\Schema;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Live-schema helpers for additive migrations that must not assume a
 * Laravel-default foreign-key name exists on an older staging/production DB.
 */
final class ForeignKeys
{
    /**
     * Drop every foreign key that includes $column. Safe when none exist.
     *
     * @return list<string> Constraint names that were dropped
     */
    public static function dropOnColumn(string $table, string $column): array
    {
        $dropped = [];

        foreach (self::namesOnColumn($table, $column) as $name) {
            Schema::table($table, function (Blueprint $blueprint) use ($name): void {
                $blueprint->dropForeign($name);
            });
            $dropped[] = $name;
        }

        return $dropped;
    }

    public static function existsOnColumn(string $table, string $column): bool
    {
        return self::namesOnColumn($table, $column) !== [];
    }

    /**
     * Set $column to NULL where it does not match a parent row.
     * Required before adding an FK on a live table that already has orphans
     * (staging 2026-08-25: 1452 on students.user_id → users.id).
     */
    public static function nullOrphans(string $table, string $column, string $parentTable, string $parentColumn): int
    {
        return (int) DB::table($table)
            ->whereNotNull($column)
            ->whereNotIn($column, DB::table($parentTable)->select($parentColumn))
            ->update([$column => null]);
    }

    /**
     * @return list<string>
     */
    public static function namesOnColumn(string $table, string $column): array
    {
        $names = [];

        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            $columns = $foreignKey['columns'] ?? [];
            if (in_array($column, $columns, true)) {
                $names[] = $foreignKey['name'];
            }
        }

        return $names;
    }
}
