<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use App\Support\Authorization\RoleLabels;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The roles a person holds, set from the role screen (ADR-040 slice 4,
 * BACKLOG C8). Until now roles came from seeders, `bookshop:grant-manager`
 * or tinker: a system admin could not make somebody a dean, a teacher or a
 * Bookstore admin from the panel.
 *
 * Two protections, the same shape `DeleteUserAccountAction` has: the actor
 * never removes their own `super_admin` (the way a person locks themselves
 * out of the Institute), and the last system admin keeps the role (the way
 * a deployment loses its last line of access). Only roles the label file
 * names may be set, so a typo never mints a role. A new teacher gets their
 * `teachers` row, which the timetable, registers and pickers key on.
 */
class SetUserRolesAction
{
    /**
     * @param  list<string>  $roles
     * @return array{added: list<string>, removed: list<string>}
     */
    public function execute(User $user, array $roles, int $actorId): array
    {
        $roles = array_values(array_unique(array_map('strval', $roles)));
        $unknown = array_diff($roles, RoleLabels::KNOWN);
        if ($unknown !== []) {
            throw ValidationException::withMessages(['roles' => __('admin.roles_error_unknown', ['role' => implode(', ', $unknown)])]);
        }

        $current = $user->getRoleNames()->all();
        $removed = array_values(array_diff($current, $roles));
        $added = array_values(array_diff($roles, $current));

        if (in_array('super_admin', $removed, true)) {
            if ((int) $user->id === $actorId) {
                throw ValidationException::withMessages(['roles' => __('admin.roles_error_self')]);
            }
            if (User::query()->role('super_admin')->count() <= 1) {
                throw ValidationException::withMessages(['roles' => __('admin.roles_error_last')]);
            }
        }

        foreach ($roles as $role) {
            Role::findOrCreate($role, 'web');
        }
        $user->syncRoles($roles);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (in_array('teacher', $added, true)) {
            // Single-institute (ADR-001): the one school row, read the way
            // `CreateStaffAccountAction` reads it rather than through another
            // domain's model (rule 3).
            $schoolId = DB::table('schools')->orderBy('id')->value('id');
            if ($schoolId !== null) {
                app(\App\Domains\People\Actions\EnsureTeacherRowAction::class)->execute((int) $user->id, (int) $schoolId);
            }
        }

        // LENDING_AND_USED_BOOKS_PLAN L4: taking Lender away pauses a registered
        // lender (their books leave the shelf, they read why); giving it back resumes.
        if (in_array('lender', $added, true) || in_array('lender', $removed, true)) {
            app(\App\Domains\Lending\Actions\ModerateLendingAction::class)->roleChanged((int) $user->id, in_array('lender', $roles, true));
        }

        return ['added' => $added, 'removed' => $removed];
    }
}
