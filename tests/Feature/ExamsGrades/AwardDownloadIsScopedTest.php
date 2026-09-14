<?php

use App\Domains\ExamsGrades\Models\Award;
use App\Domains\ExamsGrades\Models\StudentAward;
use App\Domains\Identity\Models\User;
use App\Domains\Media\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * `exams.awards.download` used to bind `{award}` — the award **template** —
 * and then read `?document_id=` straight from the query string, ignoring the
 * binding entirely:
 *
 *     $documentId = $request->integer('document_id');
 *     $file = app(ReadGeneratedDocumentAction::class)->execute($documentId);
 *
 * `exams.manage` is held by teachers and exam staff, so any of them could pass
 * any document id and receive its contents — another class's report cards,
 * anybody's certificates, every generated document in the application. The
 * route parameter made it look scoped and scoped nothing.
 *
 * That is the shape `PrivateMediaReadersAreScopedTest` was written about after
 * #336: *"the one that legitimately takes an id straight from the route ... is
 * exactly why it needs an allow-list and why it is the one that went wrong."*
 * Same lesson, on the **documents** path, which that gate does not reach — it
 * pins callers of `ReadPrivateMediaAction` and documents go through
 * `ReadGeneratedDocumentAction`.
 *
 * Nothing in the frontend ever linked to this route, which is presumably why
 * it lasted: it was reachable only by typing it.
 */
function awardStaff(): User
{
    Role::findOrCreate('admin', 'web');
    Permission::findOrCreate('exams.manage', 'web');

    $user = User::factory()->create();
    $user->assignRole('admin');
    $user->givePermissionTo('exams.manage');

    return $user->fresh();
}

function documentHolding(string $body): Document
{
    // No `Storage::fake` here: each call resets the disk, so faking inside the
    // helper silently deleted the file made by the previous call and the
    // action returned empty contents. The tests fake once, themselves.
    $path = 'documents/'.uniqueFixtureSuffix().'.html';
    Storage::disk('local')->put($path, $body);

    return Document::query()->create([
        'documentable_type' => 'student_award',
        'documentable_id' => 0,
        'document_type' => 'award_certificate',
        'title' => 'Generated',
        'media_path' => $path,
    ]);
}

function issuedAward(?Document $certificate): StudentAward
{
    $year = makeYear(['status' => \App\Domains\Academics\Enums\AcademicYearStatus::Active, 'is_current' => true]);
    $award = Award::query()->create(['title' => 'Best Attendance', 'level' => 'school', 'active' => true]);

    return StudentAward::query()->create([
        'student_id' => makeStudent()->id,
        'award_id' => $award->id,
        'academic_year_id' => $year->id,
        'awarded_date' => now()->toDateString(),
        'certificate_document_id' => $certificate?->id,
    ]);
}

it('serves only the certificate belonging to the award in the url', function () {
    Storage::fake('local');

    $mine = documentHolding('<p>THIS AWARD</p>');
    $somebodyElses = documentHolding('<p>ANOTHER CHILD REPORT CARD</p>');

    $issued = issuedAward($mine);

    $response = $this->withoutLocalizationMiddleware()->actingAs(awardStaff())
        // The old parameter, still supplied. It must now be ignored rather
        // than obeyed.
        ->get('/exams/awards/issued/'.$issued->id.'/download?document_id='.$somebodyElses->id)
        ->assertOk();

    expect($response->getContent())->toContain('THIS AWARD')
        ->and($response->getContent())->not->toContain('ANOTHER CHILD REPORT CARD');
});

it('404s an issued award that has no certificate rather than serving something else', function () {
    Storage::fake('local');

    $somebodyElses = documentHolding('<p>ANOTHER CHILD REPORT CARD</p>');
    $issued = issuedAward(null);

    $this->withoutLocalizationMiddleware()->actingAs(awardStaff())
        ->get('/exams/awards/issued/'.$issued->id.'/download?document_id='.$somebodyElses->id)
        ->assertNotFound();
});

it('still refuses a caller without exams.manage', function () {
    Storage::fake('local');

    $issued = issuedAward(documentHolding('<p>THIS AWARD</p>'));

    Role::findOrCreate('teacher', 'web');
    $outsider = User::factory()->create();
    $outsider->assignRole('teacher');

    $this->withoutLocalizationMiddleware()->actingAs($outsider->fresh())
        ->get('/exams/awards/issued/'.$issued->id.'/download')
        ->assertForbidden();
});
