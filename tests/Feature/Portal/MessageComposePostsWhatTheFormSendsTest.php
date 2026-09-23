<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Actions\SaveTimetableEntryAction;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Models\MessageThread;
use App\Domains\People\Actions\AttachGuardianAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * The compose form posts every field it owns, whichever branch is showing:
 * a family writing to a person still sends `class_id: ''`, a teacher writing
 * to a class still sends `recipient_id: ''`, and both send the empty poll
 * boxes. The browser turns those empty strings into null, and `integer`
 * refused null on a field that was not marked nullable — so every send from
 * the screen bounced back to the form, and the branch that would have shown
 * the error was the other one. `PortalMessagesPageTest` posts only the
 * fields it needs and never saw it; the family walk did (STATUS §5fp). This
 * posts exactly what the form posts.
 */
function seedComposePair(): array
{
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $student = makeStudent();
    app(AssignStudentToClassAction::class)->execute($class, (int) $student->id);
    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, 'father');

    $teacher = makeTeacherRow();
    Permission::findOrCreate('messages.broadcast', 'web');
    User::query()->find($teacher->user_id)->givePermissionTo('messages.broadcast');
    app(SaveTimetableEntryAction::class)->execute([
        'class_id' => $class->id,
        'subject_id' => makeSubject()->id,
        'teacher_id' => $teacher->id,
        'academic_year_id' => $year->id,
        'day_of_week' => 'monday',
        'period_id' => makePeriodRow()->id,
        'room_id' => makeRoomRow()->id,
        'is_active' => true,
    ]);

    return [
        'parent' => User::query()->find($guardian->user_id),
        'teacherUser' => User::query()->find($teacher->user_id),
        'class' => $class,
    ];
}

it('lets a family send to a person with the class field posted empty', function () {
    ['parent' => $parent, 'teacherUser' => $teacherUser] = seedComposePair();

    $this->withoutLocalizationMiddleware()
        ->actingAs($parent)
        ->from('/portal/messages/new')
        ->post('/portal/messages', [
            'target_type' => 'user',
            'recipient_id' => (string) $teacherUser->id,
            'class_id' => '',
            'audience' => 'guardians',
            'subject' => 'About Sunday',
            'body' => 'Late on Sunday.',
            'poll_question' => '',
            'poll_options' => ['', ''],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/portal/messages/'.MessageThread::query()->value('id'));
});

it('lets a teacher send to a class with the recipient field posted empty', function () {
    ['teacherUser' => $teacherUser, 'class' => $class] = seedComposePair();

    $this->withoutLocalizationMiddleware()
        ->actingAs($teacherUser)
        ->from('/portal/messages/new')
        ->post('/portal/messages', [
            'target_type' => 'class',
            'recipient_id' => '',
            'class_id' => (string) $class->id,
            'audience' => 'guardians',
            'subject' => 'Trip',
            'body' => 'Answer below.',
            'poll_question' => 'Coming?',
            'poll_options' => ['Yes', 'No'],
        ])
        ->assertSessionHasNoErrors();

    expect(MessageThread::query()->where('subject', 'Trip')->exists())->toBeTrue();
});
