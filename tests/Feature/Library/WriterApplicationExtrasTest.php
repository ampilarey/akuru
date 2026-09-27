<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\ListWriterQueuesAction;
use App\Domains\Library\Models\WriterApplication;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * B9 (LIBRARY_PLAN §11.1, STATUS §5iv): an applicant may add a portrait,
 * their previous publications and an identity document. The office sees
 * all three in the queue; the document is private and opened from there
 * only; the portrait becomes the author page's face at approval.
 */
it('takes the extras with the application, shows them to the office only, and carries the portrait onto the profile', function () {
    Storage::fake('public');
    Storage::fake('local');
    $applicant = User::factory()->create();
    $office = actingSystemAdmin(['library.manage']);

    $this->withoutLocalizationMiddleware()->actingAs($applicant)
        ->post(route('write.apply'), [
            'display_name' => 'Ustadha Aminath',
            'previous_publications' => "Sun letters — Haveeru, 2019\nMoon letters — self-published, 2021",
            'photo' => UploadedFile::fake()->image('portrait.jpg', 400, 400),
            'id_document' => UploadedFile::fake()->image('id-card.png', 600, 400),
            'agreement_accepted' => '1',
        ])->assertSessionHasNoErrors();

    $application = WriterApplication::query()->firstOrFail();
    expect($application->previous_publications)->toContain('Moon letters')
        ->and($application->photo_media_file_id)->not->toBeNull()
        ->and($application->id_document_media_file_id)->not->toBeNull();

    $queued = app(ListWriterQueuesAction::class)->execute()['applications'][0];
    expect($queued['previous_publications'])->toContain('Sun letters')
        ->and($queued['photo_url'])->not->toBeNull()
        ->and($queued['has_id_document'])->toBeTrue()
        ->and($queued)->not->toHaveKey('id_document_media_file_id');

    // The office opens the document from the queue; the applicant, or anyone
    // else, cannot reach it — the route is the office's.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->get(route('admin.library.applications.document', $application->id))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'no-store, private');
    $this->withoutLocalizationMiddleware()->actingAs($applicant)
        ->get(route('admin.library.applications.document', $application->id))
        ->assertForbidden();

    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('admin.library.applications.decide', $application->id), ['approve' => 1])
        ->assertSessionHasNoErrors();
    $profile = WriterProfile::query()->where('user_id', $applicant->id)->firstOrFail();
    expect((int) $profile->photo_media_file_id)->toBe((int) $application->photo_media_file_id);
});

it('refuses a portrait that is not an image and a document of the wrong kind, and has nothing to open when none was given', function () {
    Storage::fake('public');
    Storage::fake('local');
    $applicant = User::factory()->create();
    $office = actingSystemAdmin(['library.manage']);

    $this->withoutLocalizationMiddleware()->actingAs($applicant)
        ->post(route('write.apply'), [
            'display_name' => 'Ustadha Aminath',
            'photo' => UploadedFile::fake()->create('not-a-portrait.pdf', 10, 'application/pdf'),
            'id_document' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
            'agreement_accepted' => '1',
        ])->assertSessionHasErrors(['photo', 'id_document']);
    expect(WriterApplication::query()->count())->toBe(0);

    $this->withoutLocalizationMiddleware()->actingAs($applicant)
        ->post(route('write.apply'), ['display_name' => 'Ustadha Aminath', 'agreement_accepted' => '1'])
        ->assertSessionHasNoErrors();
    $application = WriterApplication::query()->firstOrFail();
    expect($application->photo_media_file_id)->toBeNull()
        ->and(app(ListWriterQueuesAction::class)->execute()['applications'][0]['has_id_document'])->toBeFalse();

    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->get(route('admin.library.applications.document', $application->id))
        ->assertNotFound();
});
