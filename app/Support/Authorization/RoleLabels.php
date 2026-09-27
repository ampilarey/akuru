<?php

namespace App\Support\Authorization;

/**
 * What a role is called where a person reads it (ADR-040 slice 3).
 *
 * The role keys stay what they are in the database (`super_admin`, `admin`,
 * `headmaster`, …); the labels are the owner's names for the jobs — System
 * admin, Educational admin, Dean — in `lang/roles.php`, in the three
 * languages. A role the file does not know is humanised from its key, so a
 * new role never renders as `some_key` on a screen.
 */
final class RoleLabels
{
    /** The roles the label file names, in the order the users screen offers them. */
    public const KNOWN = ['super_admin', 'admin', 'headmaster', 'supervisor', 'teacher', 'student', 'parent', 'course_creator', 'writer', 'reviewer', 'vendor', 'bookshop_manager'];

    public static function label(string $role, ?string $locale = null): string
    {
        $key = 'roles.'.$role;
        $label = trans($key, [], $locale);

        return $label === $key ? self::humanise($role) : $label;
    }

    /** @return array<string, string> role key => label */
    public static function all(?string $locale = null): array
    {
        $labels = [];
        foreach (self::KNOWN as $role) {
            $labels[$role] = self::label($role, $locale);
        }

        return $labels;
    }

    /**
     * Several roles, as one line ("Teacher, Parent"), or the "no role" label.
     *
     * @param  iterable<string>  $roles
     */
    public static function list(iterable $roles, ?string $locale = null): string
    {
        // In the label file's order (System admin first, the side roles last),
        // whatever order the roles were granted in.
        $held = is_array($roles) ? $roles : iterator_to_array($roles, false);
        $order = array_flip(self::KNOWN);
        usort($held, fn (string $a, string $b): int => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));

        $labels = [];
        foreach ($held as $role) {
            $labels[] = self::label($role, $locale);
        }

        return $labels === [] ? trans('roles.none', [], $locale) : implode(', ', $labels);
    }

    /** The old rendering ("Super Admin"), kept for a role the file does not name. */
    public static function humanise(string $role): string
    {
        return ucwords(str_replace('_', ' ', $role));
    }
}
