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
            // COMMERCE_PARITY_PLAN P2: both sides of the ID card, kept by Identity.
            'id_front' => UploadedFile::fake()->image('id-front.png', 600, 400),
            'id_back' => UploadedFile::fake()->image('id-back.png', 600, 400),
            'agreement_accepted' => '1',
        ])->assertSessionHasNoErrors();

    $application = WriterApplication::query()->firstOrFail();
    expect($application->previous_publications)->toContain('Moon letters')
        ->and($application->photo_media_file_id)->not->toBeNull();

    $queued = app(ListWriterQueuesAction::class)->execute()['applications'][0];
    expect($queued['previous_publications'])->toContain('Sun letters')
        ->and($queued['photo_url'])->not->toBeNull()
        ->and($queued['identity']['status'])->toBe('pending')
        ->and($queued)->not->toHaveKey('id_document_media_file_id');

    // The office opens both sides from the queue; the applicant cannot — the route is the office's.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->get($queued['identity']['front_url'])
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'no-store, private');
    $this->withoutLocalizationMiddleware()->actingAs($office)->get($queued['identity']['back_url'])->assertOk();
    $this->withoutLocalizationMiddleware()->actingAs($applicant)
        ->get($queued['identity']['front_url'])
        ->assertForbidden();

    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('admin.library.applications.decide', $application->id), ['approve' => 1])
        ->assertSessionHasNoErrors();
    $profile = WriterProfile::query()->where('user_id', $applicant->id)->firstOrFail();
    expect((int) $profile->photo_media_file_id)->toBe((int) $application->photo_media_file_id);
});

it('refuses a portrait that is not an image, an ID card of the wrong kind, and an application without both sides', function () {
    Storage::fake('public');
    Storage::fake('local');
    $applicant = User::factory()->create();

    $this->withoutLocalizationMiddleware()->actingAs($applicant)
        ->post(route('write.apply'), [
            'display_name' => 'Ustadha Aminath',
            'photo' => UploadedFile::fake()->create('not-a-portrait.pdf', 10, 'application/pdf'),
            'id_front' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
            'id_back' => UploadedFile::fake()->image('id-back.png', 600, 400),
            'agreement_accepted' => '1',
        ])->assertSessionHasErrors(['photo', 'id_front']);

    $this->withoutLocalizationMiddleware()->actingAs($applicant)
        ->post(route('write.apply'), ['display_name' => 'Ustadha Aminath', 'agreement_accepted' => '1'])
        ->assertSessionHasErrors(['id_front'])->assertSessionDoesntHaveErrors(['id_back']);
    expect(WriterApplication::query()->count())->toBe(0);
});
