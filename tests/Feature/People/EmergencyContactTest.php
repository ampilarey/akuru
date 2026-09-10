<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Actions\ListAbsencesForDayAction;
use App\Domains\Academics\Enums\AttendanceSource;
use App\Domains\Academics\Enums\AttendanceStatus;
use App\Domains\Academics\Models\ClassAttendance;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\ListEmergencyContactsAction;
use App\Domains\People\Actions\RemoveEmergencyContactAction;
use App\Domains\People\Actions\SaveEmergencyContactAction;
use App\Domains\People\Models\EmergencyContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Emergency contacts — who to ring when a child is hurt.
 *
 * `emergency_contacts` shipped with the unified student schema in August and
 * had never been written or read. `StudentDirectoryController` even
 * eager-loaded the relation and dropped it before serialising, so the query ran
 * on every student page and the answer reached nobody.
 */
it('records a contact against the student', function () {
    $student = makeStudent();

    $contact = app(SaveEmergencyContactAction::class)->execute((int) $student->id, [
        'name' => '  Aminath Ali  ',
        'phone' => ' 7770000 ',
        'relationship' => 'aunt',
    ]);

    expect($contact->name)->toBe('Aminath Ali')
        ->and($contact->phone)->toBe('7770000')
        ->and((int) $contact->student_id)->toBe((int) $student->id)
        // Defaulting to 1 rather than 0 keeps the column meaning "ring first".
        ->and($contact->priority)->toBe(1);
});

it('refuses a contact with no name', function () {
    // An unnamed number tells whoever dials it nothing about who is answering.
    app(SaveEmergencyContactAction::class)->execute((int) makeStudent()->id, ['name' => ' ', 'phone' => '7770000']);
})->throws(ValidationException::class);

it('refuses a contact with no number', function () {
    // A contact you cannot ring is not a contact.
    app(SaveEmergencyContactAction::class)->execute((int) makeStudent()->id, ['name' => 'Aminath', 'phone' => '']);
})->throws(ValidationException::class);

it('lists contacts in the order they should be rung', function () {
    $student = makeStudent();
    $save = app(SaveEmergencyContactAction::class);
    $save->execute((int) $student->id, ['name' => 'Third', 'phone' => '3', 'priority' => 3]);
    $save->execute((int) $student->id, ['name' => 'First', 'phone' => '1', 'priority' => 1]);
    $save->execute((int) $student->id, ['name' => 'Second', 'phone' => '2', 'priority' => 2]);

    // A list in insertion order is a list you have to think about while a child
    // is hurt.
    expect(app(ListEmergencyContactsAction::class)->execute((int) $student->id)->pluck('name')->all())
        ->toBe(['First', 'Second', 'Third']);
});

it('refuses to edit a contact through another childs record', function () {
    $mine = app(SaveEmergencyContactAction::class)
        ->execute((int) makeStudent()->id, ['name' => 'Mine', 'phone' => '1']);

    // Otherwise a stale page rewrites the wrong family's details.
    app(SaveEmergencyContactAction::class)
        ->execute((int) makeStudent()->id, ['name' => 'Theirs', 'phone' => '2'], $mine);
})->throws(ValidationException::class);

it('refuses to remove a contact through another childs record', function () {
    $mine = app(SaveEmergencyContactAction::class)
        ->execute((int) makeStudent()->id, ['name' => 'Mine', 'phone' => '1']);

    app(RemoveEmergencyContactAction::class)->execute((int) makeStudent()->id, $mine);
})->throws(ValidationException::class);

it('removes a contact from its own student', function () {
    $student = makeStudent();
    $contact = app(SaveEmergencyContactAction::class)
        ->execute((int) $student->id, ['name' => 'Mine', 'phone' => '1']);

    app(RemoveEmergencyContactAction::class)->execute((int) $student->id, $contact);

    expect(EmergencyContact::query()->count())->toBe(0);
});

it('answers who to ring for many children at once', function () {
    $one = makeStudent();
    $two = makeStudent();
    $save = app(SaveEmergencyContactAction::class);
    $save->execute((int) $one->id, ['name' => 'Backup', 'phone' => '9', 'priority' => 2]);
    $save->execute((int) $one->id, ['name' => 'Primary', 'phone' => '1', 'priority' => 1]);
    $save->execute((int) $two->id, ['name' => 'Only', 'phone' => '2']);

    $rows = app(ListEmergencyContactsAction::class)
        ->firstForStudents([(int) $one->id, (int) $two->id, (int) makeStudent()->id]);

    expect($rows->get((int) $one->id)['name'])->toBe('Primary')
        // So a screen can say "and 1 more" rather than implying this is the
        // only person who can be reached.
        ->and($rows->get((int) $one->id)['others'])->toBe(1)
        ->and($rows->get((int) $two->id)['others'])->toBe(0)
        ->and($rows->has((int) makeStudent()->id))->toBeFalse();
});

it('puts the number beside an unexplained absence', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $student = makeStudent();
    app(AssignStudentToClassAction::class)->execute($class, (int) $student->id);
    app(SaveEmergencyContactAction::class)
        ->execute((int) $student->id, ['name' => 'Aminath Ali', 'phone' => '7770000', 'relationship' => 'aunt']);

    $period = makePeriodRow('08:00:00', '08:45:00', 1)->id;
    ClassAttendance::query()->create([
        'academic_year_id' => $year->id,
        'class_id' => $class->id,
        'student_id' => $student->id,
        'date' => '2026-09-10',
        'period_id' => $period,
        'period_key' => $period,
        'status' => AttendanceStatus::Absent->value,
        'source' => AttendanceSource::Daily->value,
        'marked_by' => User::factory()->create()->id,
    ]);

    // The office ringing home needs the number on the row, not one screen away.
    // Academics asks People through an Action — arrays out, no model import.
    $row = app(ListAbsencesForDayAction::class)->execute(['date' => '2026-09-10'])['students'][0];

    expect($row['is_unexplained'])->toBeTrue()
        ->and($row['emergency_contact']['phone'])->toBe('7770000')
        ->and($row['emergency_contact']['name'])->toBe('Aminath Ali');
});

it('says plainly when there is nobody to ring', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $student = makeStudent();
    app(AssignStudentToClassAction::class)->execute($class, (int) $student->id);
    $period = makePeriodRow('08:00:00', '08:45:00', 1)->id;
    ClassAttendance::query()->create([
        'academic_year_id' => $year->id,
        'class_id' => $class->id,
        'student_id' => $student->id,
        'date' => '2026-09-10',
        'period_id' => $period,
        'period_key' => $period,
        'status' => AttendanceStatus::Absent->value,
        'source' => AttendanceSource::Daily->value,
        'marked_by' => User::factory()->create()->id,
    ]);

    expect(app(ListAbsencesForDayAction::class)->execute(['date' => '2026-09-10'])['students'][0]['emergency_contact'])
        ->toBeNull();
});

it('shows contacts on the student record and saves one over http', function () {
    $student = makeStudent();
    $admin = actingPeopleAdmin(['people.students.manage', 'people.students.view']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('people.students.emergency-contacts.store', $student), [
            'name' => 'Aminath Ali',
            'phone' => '7770000',
            'relationship' => 'aunt',
            'priority' => 1,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('people.students.show', ['student' => $student->id, 'tab' => 'emergency']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('People/Students/Show')
            ->has('emergencyContacts', 1)
            ->where('emergencyContacts.0.phone', '7770000')
        );
});

it('removes a contact over http', function () {
    $student = makeStudent();
    $contact = app(SaveEmergencyContactAction::class)
        ->execute((int) $student->id, ['name' => 'Aminath', 'phone' => '7770000']);
    $admin = actingPeopleAdmin(['people.students.manage', 'people.students.view']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->delete(route('people.students.emergency-contacts.destroy', [$student, $contact]))
        ->assertRedirect();

    expect(EmergencyContact::query()->count())->toBe(0);
});
