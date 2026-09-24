<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §29 "Soft Deletes and Historical Data":
 *
 *   > Never hard-delete a course, offering, session, module, lesson, activity,
 *   > assessment, or **enrollment** if it has: enrolments · attendance records ·
 *   > attempts · progress records · student submissions · teacher feedback ·
 *   > issued certificates · payment records.
 *   >
 *   > **Historical student data must remain intact.**
 *
 * `AdminUserController::destroy` did the exact opposite, from an ordinary admin
 * screen, and did it with the safety rails switched off:
 *
 * ```php
 * DB::statement('SET FOREIGN_KEY_CHECKS=0;');
 * DB::table('course_enrollments')->whereIn('student_id', $studentIds)->delete();
 * DB::table('registration_students')->where('user_id', $user->id)->delete();
 * DB::table('payments')->where('user_id', $user->id)->delete();
 * $user->delete();
 * DB::statement('SET FOREIGN_KEY_CHECKS=1;');
 * ```
 *
 * Four separate problems, each bad on its own:
 *
 * 1. **`DB::table(...)->delete()` bypasses `SoftDeletes` entirely.**
 *    `CourseEnrollment` carries the trait — added precisely so §29 would hold —
 *    and a query-builder delete never consults it. The enrolments were gone,
 *    not soft-deleted.
 * 2. **Foreign key checks were disabled**, so the `student_lesson_progress →
 *    course_enrollments` cascade never fired. Progress rows, attempts,
 *    attendance and issued certificates were left **orphaned**, pointing at
 *    enrolment ids that no longer exist — worse than either deleting or keeping
 *    them, because nothing downstream can tell.
 * 3. **`payments` rows were destroyed.** CLAUDE.md rule 12: money tables are
 *    append-only — reversals, never deletes. A deleted payment is a
 *    reconciliation that can never be done again.
 * 4. `users` has **no `deleted_at`**, so `$user->delete()` was a hard delete of
 *    the account too.
 *
 * ## What this does instead
 *
 * `users.is_active` already exists and is already enforced — password login,
 * OTP login, account linking and account switching all refuse an inactive user.
 * So there is a real "this person can no longer sign in" that costs nobody
 * their history, and deactivation is the honest meaning of "remove this user"
 * once anything depends on them.
 *
 * A hard delete stays available for exactly what §29 permits: an account with
 * no student activity and no dependent records — a mistyped signup, a test
 * account. It runs **with foreign key checks on**, so if this list of
 * dependants is ever incomplete the database refuses rather than silently
 * orphaning rows.
 */
class DeleteUserAccountAction
{
    /**
     * §29's list, as tables and the column each one hangs a student off.
     *
     * `payments` is keyed by user rather than student because rule 12 is about
     * the payer, and a user with any payment history is never deletable
     * regardless of whether they are a student.
     *
     * @var array<string, string>
     */
    private const STUDENT_DEPENDENTS = [
        'course_enrollments' => 'unified_student_id',
        'attendance_records' => 'student_id',
        'activity_attempts' => 'student_id',
        'assessment_attempts' => 'student_id',
        'student_lesson_progress' => 'student_id',
        'issued_certificates' => 'student_id',
    ];

    /**
     * @return array{deleted: bool, deactivated: bool, blocked_by: array<string, int>}
     */
    public function execute(User $user, ?int $actorId): array
    {
        $this->assertRemovable($user, $actorId);

        $counts = $this->dependentCounts($user);

        if ($counts !== []) {
            // §29: the history stays. The account stops working.
            $user->forceFill(['is_active' => false])->save();

            return ['deleted' => false, 'deactivated' => true, 'blocked_by' => $counts];
        }

        // Nothing of anyone's is attached. §29 allows the row to go — and it
        // goes with the foreign keys watching, not with them switched off.
        DB::transaction(function () use ($user): void {
            DB::table('user_contacts')->where('user_id', $user->id)->delete();
            $morph = $user->getMorphClass();
            DB::table('model_has_roles')->where('model_id', $user->id)->where('model_type', $morph)->delete();
            DB::table('model_has_permissions')->where('model_id', $user->id)->where('model_type', $morph)->delete();
            // Their archived registration row (Deploy 3 slice 3) goes with them.
            if (Schema::hasTable('archived_registration_students')) {
                DB::table('archived_registration_students')->where('user_id', $user->id)->delete();
            }
            $user->delete();
        });

        return ['deleted' => true, 'deactivated' => false, 'blocked_by' => []];
    }

    /**
     * The two refusals the controller already had, kept and moved here so they
     * cannot be forgotten by a second caller.
     */
    private function assertRemovable(User $user, ?int $actorId): void
    {
        if ($actorId !== null && (int) $user->id === $actorId) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own account.',
            ]);
        }

        if ($user->hasRole('super_admin')) {
            throw ValidationException::withMessages([
                'user' => 'Super admin accounts cannot be deleted.',
            ]);
        }
    }

    /**
     * Everything §29 says makes this person part of somebody's record.
     *
     * @return array<string, int> table => count, only where rows exist
     */
    public function dependentCounts(User $user): array
    {
        $counts = [];

        // Rule 12 first and unconditionally: money is never deleted.
        if (Schema::hasTable('payments')) {
            $payments = DB::table('payments')->where('user_id', $user->id)->count();
            if ($payments > 0) {
                $counts['payments'] = $payments;
            }
        }

        $studentIds = $this->studentIds($user);
        $legacyIds = $this->legacyStudentIds($user);

        if ($studentIds === [] && $legacyIds === []) {
            return $counts;
        }

        foreach (self::STUDENT_DEPENDENTS as $table => $column) {
            // Checked for existence because this list spans phases, exactly as
            // `DeleteCourseAction` does: a database part-way through migrations
            // should not fatal here.
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $query = DB::table($table)->whereIn($column, $studentIds ?: [0]);

            // An old enrolment the unification never placed keeps only its
            // archived registration id (Deploy 3 slice 3). Missing it would let
            // a real roster be deleted.
            if ($table === 'course_enrollments' && $legacyIds !== [] && Schema::hasColumn($table, 'archived_registration_student_id')) {
                $query->orWhereIn('archived_registration_student_id', $legacyIds);
            }

            $count = $query->count();
            if ($count > 0) {
                $counts[$table] = $count;
            }
        }

        return $counts;
    }

    /**
     * @return list<int>
     */
    private function studentIds(User $user): array
    {
        if (! Schema::hasTable('students')) {
            return [];
        }

        return DB::table('students')
            ->where('user_id', $user->id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function legacyStudentIds(User $user): array
    {
        if (! Schema::hasTable('archived_registration_students')) {
            return [];
        }

        return DB::table('archived_registration_students')
            ->where('user_id', $user->id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
