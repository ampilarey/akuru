<?php

namespace App\Support\Schema;

use Illuminate\Database\Schema\Blueprint;
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
