<?php

use App\Domains\Academics\Enums\AbsenceNoteStatus;
use App\Domains\Academics\Models\AbsenceNote;
use App\Domains\Academics\Models\AbsenceType;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * S2.4: "Parent submits notes from Portal with attachment". The controller
 * accepted a file and a period from the start; the form offered neither, and
 * nothing served a stored file back. So a reason an admin marked "needs a
 * document" (`requires_evidence`) could not be sent from the portal at all —
 * the form warned, the action refused, and there was no way to comply.
 */
function guardianWithChild(): array
{
    $guardian = makeGuardian();
    $student = makeStudent(['first_name' => 'Noted', 'last_name' => 'Child']);
    app(\App\Domains\People\Actions\AttachGuardianAction::class)->execute($student, $guardian, 'mother', true);

    return [User::query()->findOrFail($guardian->user_id), $student];
}

function evidenceReason(): AbsenceType
{
    return AbsenceType::query()->create([
        'code' => 'hospital',
        'name' => 'Hospital admission',
        'excuses_absence' => true,
        'requires_evidence' => true,
        'is_active' => true,
        'sort_order' => 9,
    ]);
}

it('offers the periods and lets a parent attach a document to a note for one lesson', function () {
    Storage::fake('local');
    [$parent, $student] = guardianWithChild();
    $period = makePeriodRow();
    $type = evidenceReason();

    $this->withoutLocalizationMiddleware()
        ->actingAs($parent)
        ->get('/portal/absence-notes')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/AbsenceNotes')
            ->has('periods', 1)
            ->where('periods.0.id', $period->id));

    $this->withoutLocalizationMiddleware()
        ->actingAs($parent)
        ->post('/portal/absence-notes', [
            'student_id' => $student->id,
            'date' => now()->toDateString(),
            'period_id' => $period->id,
            'absence_type_id' => $type->id,
            'reason' => 'Admitted overnight.',
            'attachment' => UploadedFile::fake()->create('discharge.pdf', 40, 'application/pdf'),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/portal/absence-notes');

    $note = AbsenceNote::query()->where('student_id', $student->id)->firstOrFail();

    expect($note->period_id)->toBe($period->id)
        ->and($note->attachment_path)->toStartWith('absence-notes/')
        ->and(Storage::disk('local')->exists($note->attachment_path))->toBeTrue();

    // And the family's own list links to it.
    $this->withoutLocalizationMiddleware()
        ->actingAs($parent)
        ->get('/portal/absence-notes')
        ->assertInertia(fn (Assert $page) => $page
            ->where('notes.0.period_name', $period->name)
            ->where('notes.0.attachment_url', fn ($url) => str_ends_with((string) $url, '/absence-notes/'.$note->id.'/attachment')));
});

it('still refuses a document-required reason without a document, and says so on the form', function () {
    Storage::fake('local');
    [$parent, $student] = guardianWithChild();
    $type = evidenceReason();

    $this->withoutLocalizationMiddleware()
        ->actingAs($parent)
        ->from('/portal/absence-notes')
        ->post('/portal/absence-notes', [
            'student_id' => $student->id,
            'date' => now()->toDateString(),
            'absence_type_id' => $type->id,
            'reason' => 'No paperwork yet.',
        ])
        ->assertRedirect('/portal/absence-notes')
        ->assertSessionHasErrors('attachment_path');

    expect(AbsenceNote::query()->count())->toBe(0);
});

it('serves the document to the reviewer and to the guardian, and to nobody else', function () {
    Storage::fake('local');
    [$parent, $student] = guardianWithChild();
    $path = UploadedFile::fake()->create('note.pdf', 10, 'application/pdf')->store('absence-notes', 'local');
    $note = AbsenceNote::query()->create([
        'student_id' => $student->id,
        'created_by' => $parent->id,
        'date' => now()->toDateString(),
        'reason' => 'With document.',
        'type' => 'illness',
        'status' => AbsenceNoteStatus::Submitted->value,
        'attachment_path' => $path,
        'affects_attendance' => true,
    ]);

    Permission::findOrCreate('manage_attendance', 'web');
    $reviewer = User::factory()->create();
    $reviewer->givePermissionTo('manage_attendance');

    $this->withoutLocalizationMiddleware()->actingAs($reviewer)
        ->get('/absence-notes/'.$note->id.'/attachment')
        ->assertOk()
        ->assertDownload('absence-note-'.$note->id.'.pdf');

    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->get('/absence-notes/'.$note->id.'/attachment')
        ->assertOk();

    // A different family, and a staff account without the permission.
    [$otherParent] = guardianWithChild();
    $this->withoutLocalizationMiddleware()->actingAs($otherParent)
        ->get('/absence-notes/'.$note->id.'/attachment')
        ->assertForbidden();
    $this->withoutLocalizationMiddleware()->actingAs(User::factory()->create())
        ->get('/absence-notes/'.$note->id.'/attachment')
        ->assertForbidden();

    // The review screen carries the link.
    $this->withoutLocalizationMiddleware()->actingAs($reviewer)
        ->get('/academics/absence-notes')
        ->assertInertia(fn (Assert $page) => $page
            ->where('notes.0.attachment_url', fn ($url) => str_contains((string) $url, '/absence-notes/'.$note->id.'/attachment')));
});

it('answers 404, not 500, for a note whose file is gone', function () {
    Storage::fake('local');
    [$parent, $student] = guardianWithChild();
    $note = AbsenceNote::query()->create([
        'student_id' => $student->id,
        'created_by' => $parent->id,
        'date' => now()->toDateString(),
        'reason' => 'File lost.',
        'type' => 'illness',
        'status' => AbsenceNoteStatus::Submitted->value,
        'attachment_path' => 'absence-notes/missing.pdf',
        'affects_attendance' => true,
    ]);

    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->get('/absence-notes/'.$note->id.'/attachment')
        ->assertNotFound();
});
