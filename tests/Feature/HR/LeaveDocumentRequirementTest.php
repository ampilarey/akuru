<?php

use App\Domains\Academics\Actions\ReviewSchoolRequestAction;
use App\Domains\Academics\Enums\SchoolRequestStatus;
use App\Domains\Academics\Models\SchoolRequest;
use App\Domains\HR\Actions\ApproveStaffLeaveAction;
use App\Domains\Identity\Models\User;
use App\Domains\Media\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * S5.2: a leave type can require a supporting document. `requires_document`
 * was saved, listed and shown as a column, and enforced nowhere (S5 audit
 * D4, STATUS §5ff). Sick leave is seeded as requiring one.
 */
it('refuses sick leave without a document, takes one with, and lets the right people open it', function () {
    Storage::fake('local');
    makeYear(['is_current' => true, 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $requester = actingPeopleAdmin(['requests.submit']);
    $staff = makeStaffProfile(['user_id' => $requester->id]);
    $sick = leaveType('sick');
    expect($sick->requires_document)->toBeTrue();

    $fields = [
        'type' => 'staff_leave',
        'reason' => 'Flu',
        'leave_type_id' => $sick->id,
        'from_date' => '2026-09-28',
        'to_date' => '2026-09-28',
    ];

    // Nothing attached: told at submission, nothing stored.
    $this->withoutLocalizationMiddleware()->actingAs($requester)
        ->post(route('academics.requests.store'), $fields)
        ->assertSessionHasErrors('document');
    expect(SchoolRequest::query()->count())->toBe(0);

    // A spreadsheet is not a certificate.
    $this->withoutLocalizationMiddleware()->actingAs($requester)
        ->post(route('academics.requests.store'), $fields + ['document' => UploadedFile::fake()->create('note.xlsx', 10, 'application/vnd.ms-excel')])
        ->assertSessionHasErrors('document');

    // A PDF is.
    $this->withoutLocalizationMiddleware()->actingAs($requester)
        ->post(route('academics.requests.store'), $fields + ['document' => UploadedFile::fake()->create('certificate.pdf', 10, 'application/pdf')])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $request = SchoolRequest::query()->sole();
    $document = Document::query()->findOrFail((int) $request->payload['document_id']);
    expect($document->documentable_type)->toBe('staff_profile')
        ->and((int) $document->documentable_id)->toBe($staff->id)
        ->and($document->title)->toContain('certificate.pdf')
        ->and(Storage::disk('local')->exists($document->media_path))->toBeTrue();

    // The requester and a reviewer can open it; a stranger cannot.
    $this->withoutLocalizationMiddleware()->actingAs($requester)
        ->get(route('academics.requests.document', $request))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    $reviewer = actingPeopleAdmin(['requests.review']);
    $this->withoutLocalizationMiddleware()->actingAs($reviewer)
        ->get(route('academics.requests.document', $request))
        ->assertOk();
    $this->withoutLocalizationMiddleware()->actingAs(User::factory()->create())
        ->get(route('academics.requests.document', $request))
        ->assertForbidden();

    // And approval goes through with the document on the payload.
    app(ReviewSchoolRequestAction::class)->execute($request, SchoolRequestStatus::Approved, $reviewer->id);
    expect($request->fresh()->status)->toBe(SchoolRequestStatus::Approved);
});

it('refuses at approval too, so a payload built some other way cannot slip past', function () {
    makeYear(['is_current' => true, 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $staff = makeStaffProfile();

    expect(fn () => app(ApproveStaffLeaveAction::class)->execute([
        'staff_profile_id' => $staff->id,
        'leave_type_id' => leaveType('sick')->id,
        'from_date' => '2026-09-28',
        'to_date' => '2026-09-28',
    ]))->toThrow(ValidationException::class, 'supporting document');

    // Annual leave never needed one.
    $result = app(ApproveStaffLeaveAction::class)->execute([
        'staff_profile_id' => $staff->id,
        'leave_type_id' => leaveType('annual')->id,
        'from_date' => '2026-09-28',
        'to_date' => '2026-09-28',
    ]);
    expect($result['days'])->toBe(1.0);
});
