<?php

use App\Domains\Academics\Actions\AssignClassTeacherAction;
use App\Domains\Academics\Actions\ReviewSchoolRequestAction;
use App\Domains\Academics\Enums\SchoolRequestStatus;
use App\Domains\Academics\Models\SchoolRequest;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * E5's acceptance line: "a parent files a leave request, the class teacher
 * approves it, both are notified … rejected requests state a reason". The
 * staff half was walked (hr.mjs); this is the family half, found wanting by
 * the requests walk (STATUS §5fw): a family was offered the staff types and
 * refused, could not say which child, nobody was told a request was waiting,
 * a rejection could be empty, and a decided card showed neither date nor
 * reason.
 */
function familyWithClassTeacher(): array
{
    ['teacherUser' => $teacherUser, 'class' => $class, 'students' => $students, 'guardians' => $guardians] = seedClassWithFamilies(2);
    app(AssignClassTeacherAction::class)->execute($class, (int) $teacherUser->id);
    Permission::findOrCreate('requests.submit', 'web');
    Permission::findOrCreate('requests.review', 'web');
    $parent = User::query()->findOrFail($guardians[0]->user_id);
    $parent->givePermissionTo('requests.submit');
    $office = actingPeopleAdmin(['requests.review', 'requests.submit']);

    return compact('teacherUser', 'class', 'students', 'guardians', 'parent', 'office');
}

it('offers a family only the types they can file, and their own children', function () {
    ['parent' => $parent, 'students' => $students] = familyWithClassTeacher();

    $this->withoutLocalizationMiddleware()
        ->actingAs($parent)
        ->get(route('academics.requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Requests/Index')
            ->where('types', ['parent_general', 'schedule_change', 'other'])
            ->has('children', 1)
            ->where('children.0.id', $students[0]->id));
});

it('files a request about the family\'s own child, names the child, and tells the office and the class teacher', function () {
    ['parent' => $parent, 'students' => $students, 'teacherUser' => $teacherUser, 'office' => $office] = familyWithClassTeacher();

    $this->withoutLocalizationMiddleware()
        ->actingAs($parent)
        ->post(route('academics.requests.store'), [
            'type' => 'parent_general',
            'student_id' => $students[0]->id,
            'reason' => 'Travelling for a family wedding',
            'from_date' => '2026-10-01',
            'to_date' => '2026-10-03',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $request = SchoolRequest::query()->sole();
    expect($request->regarding_type)->toBe('student')
        ->and($request->regarding_id)->toBe($students[0]->id)
        ->and($request->payload['from_date'])->toBe('2026-10-01');

    // Both halves of "both are notified": the office and the class teacher, not the parent.
    $told = fn (int $userId) => DB::table('user_notifications')->where('user_id', $userId)->where('title', 'like', 'New request from%')->exists();
    expect($told($office->id))->toBeTrue()
        ->and($told($teacherUser->id))->toBeTrue()
        ->and($told($parent->id))->toBeFalse();

    $this->withoutLocalizationMiddleware()
        ->actingAs($office)
        ->get(route('academics.requests.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requests.0.regarding_name', trim($students[0]->first_name.' '.$students[0]->last_name))
            ->where('requests.0.requester_name', $parent->name)
            ->where('requests.0.submitted_at', now()->toDateString()));
});

it('refuses a request about somebody else\'s child', function () {
    ['parent' => $parent, 'students' => $students] = familyWithClassTeacher();

    $this->withoutLocalizationMiddleware()
        ->actingAs($parent)
        ->post(route('academics.requests.store'), [
            'type' => 'parent_general',
            'student_id' => $students[1]->id,
            'reason' => 'Not my child',
        ])
        ->assertSessionHasErrors('student_id');

    expect(SchoolRequest::query()->count())->toBe(0);
});

it('makes a rejection state its reason, and shows the family the decision with its date and reason', function () {
    ['parent' => $parent, 'students' => $students, 'office' => $office] = familyWithClassTeacher();

    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->post(route('academics.requests.store'), ['type' => 'parent_general', 'student_id' => $students[0]->id, 'reason' => 'Two days off']);
    $request = SchoolRequest::query()->sole();

    expect(fn () => app(ReviewSchoolRequestAction::class)->execute($request, SchoolRequestStatus::Rejected, $office->id, '   '))
        ->toThrow(ValidationException::class);
    expect($request->fresh()->status)->toBe(SchoolRequestStatus::Pending);

    $this->withoutLocalizationMiddleware()
        ->actingAs($office)
        ->post(route('academics.requests.review', $request), ['status' => 'rejected', 'review_notes' => ''])
        ->assertSessionHasErrors('review_notes');

    $this->withoutLocalizationMiddleware()
        ->actingAs($office)
        ->post(route('academics.requests.review', $request), ['status' => 'rejected', 'review_notes' => 'Term exams that week'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->withoutLocalizationMiddleware()
        ->actingAs($parent)
        ->get(route('academics.requests.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('requests', 1)
            ->where('requests.0.status', 'rejected')
            ->where('requests.0.review_notes', 'Term exams that week')
            ->where('requests.0.reviewed_at', now()->toDateString()));

    expect(DB::table('user_notifications')->where('user_id', $parent->id)->where('message', 'like', '%Term exams that week%')->exists())->toBeTrue();
});
