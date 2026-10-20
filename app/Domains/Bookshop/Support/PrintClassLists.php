<?php

namespace App\Domains\Bookshop\Support;

use App\Domains\Academics\Actions\ListClassesForYearAction;
use App\Domains\Academics\Actions\ListClassesTaughtByUserAction;
use App\Domains\Academics\Actions\ListClassRosterAction;
use App\Domains\Academics\Actions\ResolveAcademicYearForDateAction;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

/**
 * PRINT_PRODUCTS_PLAN PP6a (PD8): a name list from a class. Only a person
 * who may already see that class's roster: the school office (the roles
 * that open Academics' class directory) for this year's classes, and a
 * teacher for the classes they teach. A parent or any other customer has
 * no classes here and pastes names as before. Through Academics' own
 * actions (rule 3); only names and the class leave it.
 */
final class PrintClassLists
{
    /** The roles that open Academics' class directory (`routes/web_localized.php`, `academics` group). */
    public const OFFICE_ROLES = ['super_admin', 'admin', 'headmaster', 'supervisor'];

    /** @return list<array{id: int, label: string}> */
    public static function classesFor(?Authenticatable $user): array
    {
        if ($user === null) {
            return [];
        }
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(self::OFFICE_ROLES)) {
            $year = app(ResolveAcademicYearForDateAction::class)->execute();

            return app(ListClassesForYearAction::class)->execute($year === null ? null : (int) $year['id'])
                ->filter(fn (array $c) => (bool) $c['is_active'])
                ->map(fn (array $c) => ['id' => (int) $c['id'], 'label' => (string) $c['label']])->values()->all();
        }

        return app(ListClassesTaughtByUserAction::class)->execute((int) $user->getAuthIdentifier())
            ->map(fn (array $c) => ['id' => (int) $c['id'], 'label' => (string) $c['name']])->values()->all();
    }

    /**
     * The class's pupils as list rows — the name, and the class where the
     * design prints one. Refused for a class not the caller's.
     *
     * @param  list<string>  $fieldKeys
     * @return list<array<string, string>>
     */
    public static function rows(?Authenticatable $user, int $classId, array $fieldKeys): array
    {
        $class = collect(self::classesFor($user))->firstWhere('id', $classId);
        if ($class === null) {
            throw ValidationException::withMessages(['class' => __('print.error_class_not_yours')]);
        }
        if (! in_array('name', $fieldKeys, true)) {
            throw ValidationException::withMessages(['class' => __('print.error_class_no_name')]);
        }
        $rows = app(ListClassRosterAction::class)->execute($classId)
            ->map(fn (array $pupil) => array_filter(['name' => (string) $pupil['name'], 'class' => in_array('class', $fieldKeys, true) ? $class['label'] : null], fn ($v) => $v !== null))
            ->filter(fn (array $row) => trim($row['name']) !== '')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
        if ($rows === []) {
            throw ValidationException::withMessages(['class' => __('print.error_class_empty')]);
        }

        return $rows;
    }
}
