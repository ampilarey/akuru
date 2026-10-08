<?php

namespace App\Support;

/**
 * A person's name from its parts (C17 slice R4b, STATUS §5op).
 *
 * Maldivian names have a first, a middle and a last name; slice R4 gave the
 * student record a `middle_name` column and the registration forms a field for
 * it, and the lists, reports and CSVs that build a student's name went on
 * joining the first and the last only. Every one of them joins through here,
 * so a student is called the same thing everywhere.
 *
 * The parts that are empty are left out, so a name without a middle name has
 * no double space; the rest is joined by one space.
 */
final class PersonName
{
    public static function join(?string ...$parts): string
    {
        return implode(' ', array_values(array_filter(
            array_map(fn (?string $part): string => trim((string) $part), $parts),
            fn (string $part): bool => $part !== '',
        )));
    }

    /**
     * A student's full name from a row or an array with `first_name`,
     * `middle_name` and `last_name` (a missing middle name is no middle name).
     */
    public static function ofStudent(object|array|null $student): string
    {
        if ($student === null) {
            return '';
        }
        $row = is_array($student) ? (object) $student : $student;

        return self::join($row->first_name ?? null, $row->middle_name ?? null, $row->last_name ?? null);
    }

    /**
     * The same, for SQL: the full name of a `students` row as an expression,
     * for searching and sorting. `CONCAT_WS` skips a NULL middle name.
     */
    public static function studentSql(string $table = 'students'): string
    {
        return "CONCAT_WS(' ', {$table}.first_name, NULLIF({$table}.middle_name, ''), {$table}.last_name)";
    }
}
