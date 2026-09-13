<?php

use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Actions\DeleteUserAccountAction;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §29 "Soft Deletes and Historical Data".
 *
 * **Most of §29 is cleared.** All eight tables it names carry `deleted_at`, all
 * eight models carry the `SoftDeletes` trait, and the two delete paths built
 * for them — `DeleteCourseAction` and `DeleteCourseModuleAction` — already
 * count dependants and degrade to a soft delete. Club membership removal goes
 * through `CancelEnrollmentAction`, which cancels rather than deletes.
 *
 * One path did the opposite of all of it, from an ordinary admin screen:
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
 * Four problems, each bad alone:
 *
 * 1. **`DB::table(...)->delete()` bypasses `SoftDeletes`.** `CourseEnrollment`
 *    carries the trait — added precisely so §29 would hold — and a
 *    query-builder delete never consults it.
 * 2. **Foreign key checks were off**, so the `student_lesson_progress →
 *    course_enrollments` cascade never fired. Progress, attempts, attendance
 *    and certificates were left **orphaned** rather than removed — worse than
 *    either, because nothing downstream can tell.
 * 3. **`payments` rows were destroyed**, against rule 12's append-only money.
 * 4. `users` has no `deleted_at`, so the account went hard too.
 *
 * The replacement uses `users.is_active`, which already exists and is already
 * enforced at password login, OTP login, account linking and account
 * switching. "Remove this user" becomes "this person can no longer sign in",
 * which costs nobody their history — and a hard delete stays available for what
 * §29 actually permits: an account with nothing attached.
 */
uses(RefreshDatabase::class);

function deletableUser(): User
{
    return User::factory()->create(['is_active' => true]);
}

function enrolledUser(): array
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'History '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    $user = deletableUser();
    $student = makeStudent(['user_id' => $user->id, 'first_name' => 'Kept', 'last_name' => 'Pupil']);
    $enrollment = app(EnrollSelfLearningAction::class)->execute($user->id, $course->id, null);

    return compact('admin', 'course', 'user', 'student', 'enrollment');
}

it('deletes an account with nothing attached, with the foreign keys watching', function () {
    // §29 allows exactly this: no student activity, no dependent records.
    $user = deletableUser();

    $result = app(DeleteUserAccountAction::class)->execute($user, null);

    expect($result['deleted'])->toBeTrue()
        ->and($result['deactivated'])->toBeFalse()
        ->and(User::query()->find($user->id))->toBeNull();
});

it('deactivates instead of deleting when a student has a history', function () {
    ['user' => $user, 'enrollment' => $enrollment] = enrolledUser();

    $result = app(DeleteUserAccountAction::class)->execute($user, null);

    expect($result['deleted'])->toBeFalse()
        ->and($result['deactivated'])->toBeTrue()
        ->and($result['blocked_by'])->toHaveKey('course_enrollments');

    // The account cannot sign in — `is_active` is checked by LoginRequest,
    // OtpLoginController, LinkAccountAction and SwitchAccountAction.
    expect((bool) $user->fresh()->is_active)->toBeFalse();

    // And nothing of theirs is gone. This is the whole of §29's last line.
    expect(CourseEnrollment::query()->find($enrollment->id))->not->toBeNull()
        ->and(DB::table('students')->where('user_id', $user->id)->count())->toBe(1);
});

it('never deletes a payment, whatever else is true', function () {
    // Rule 12: money tables are append-only — reversals, not deletes. The old
    // code ran `DB::table('payments')->where('user_id', ...)->delete()`.
    $user = deletableUser();
    DB::table('payments')->insert([
        'user_id' => $user->id,
        'merchant_reference' => 'AKU-'.uniqueFixtureSuffix(),
        'amount' => 100,
        'currency' => 'MVR',
        'status' => 'confirmed',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $result = app(DeleteUserAccountAction::class)->execute($user, null);

    expect($result['deleted'])->toBeFalse()
        ->and($result['blocked_by'])->toHaveKey('payments')
        ->and(DB::table('payments')->where('user_id', $user->id)->count())->toBe(1)
        ->and(User::query()->find($user->id))->not->toBeNull();
});

it('leaves no orphan when it does delete', function () {
    // The old path switched foreign key checks off, so rows pointing at the
    // deleted enrolment survived as orphans. This one runs with the keys on:
    // if the dependant list were ever incomplete the database would refuse,
    // rather than the application silently corrupting itself.
    $user = deletableUser();
    makeStudent(['user_id' => $user->id, 'first_name' => 'No', 'last_name' => 'History']);

    app(DeleteUserAccountAction::class)->execute($user, null);

    expect(DB::table('registration_students')->where('user_id', $user->id)->count())->toBe(0)
        ->and(User::query()->find($user->id))->toBeNull();
});

it('keeps the two refusals the controller already had', function () {
    $user = deletableUser();

    expect(fn () => app(DeleteUserAccountAction::class)->execute($user, (int) $user->id))
        ->toThrow(ValidationException::class);

    $superAdmin = deletableUser();
    $superAdmin->assignRole('super_admin');

    expect(fn () => app(DeleteUserAccountAction::class)->execute($superAdmin->fresh(), null))
        ->toThrow(ValidationException::class);
});

it('counts a legacy enrolment as history too', function () {
    // `course_enrollments` carries both the unified id and the legacy
    // registration-student id, and the S1.1 read switch means either may be the
    // one populated on an older row. Missing one would let a real roster go.
    ['user' => $user, 'enrollment' => $enrollment] = enrolledUser();

    CourseEnrollment::query()->whereKey($enrollment->id)->update(['unified_student_id' => null]);

    $counts = app(DeleteUserAccountAction::class)->dependentCounts($user->fresh());

    expect($counts)->toHaveKey('course_enrollments');
});

it('no longer disables foreign key checks anywhere on the delete path', function () {
    // Comments are stripped first, because both files **quote** the old code in
    // order to explain it — and a guard that cannot tell documentation from
    // instruction would punish writing the explanation down.
    $code = function (string $path): string {
        $out = '';
        foreach (token_get_all((string) file_get_contents($path)) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $out .= is_array($token) ? $token[1] : $token;
        }

        return $out;
    };

    $controller = $code(app_path('Domains/Identity/Http/Controllers/AdminUserController.php'));
    $action = $code(app_path('Domains/Identity/Actions/DeleteUserAccountAction.php'));

    expect($controller)->not->toContain('FOREIGN_KEY_CHECKS')
        ->and($action)->not->toContain('FOREIGN_KEY_CHECKS')
        // Rule 12: the money table is never written on this path at all.
        ->and($controller)->not->toContain("table('payments')")
        ->and($action)->not->toContain("table('payments')->where('user_id', \$user->id)->delete()");
});
