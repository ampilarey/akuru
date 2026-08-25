<?php

use App\Domains\ExamsGrades\Actions\GenerateIdCardAction;
use App\Domains\ExamsGrades\Actions\GenerateTransferCertificateAction;
use App\Domains\ExamsGrades\Actions\IssueStudentAwardsAction;
use App\Domains\ExamsGrades\Actions\ListPublicAchievementsAction;
use App\Domains\ExamsGrades\Actions\SaveAwardAction;
use App\Domains\ExamsGrades\Enums\AwardLevel;
use App\Domains\ExamsGrades\Models\StudentAward;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\AttachGuardianAction;
use App\Domains\People\Actions\ChangeStudentStatusAction;
use App\Domains\People\Actions\RecordConsentAction;
use App\Domains\People\Enums\StudentStatus;
use App\Support\Contracts\DocumentRendererInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('issues award certificates and lists them in the portal', function () {
    $admin = actingPeopleAdmin(['exams.manage']);
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $student = makeStudent(['first_name' => 'Aisha', 'student_id' => 'STU-2001']);
    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, 'father', true);

    $award = app(SaveAwardAction::class)->execute([
        'title' => 'Honour roll',
        'level' => AwardLevel::School->value,
        'description' => 'Top of class',
    ]);

    $issued = app(IssueStudentAwardsAction::class)->execute([
        'award_id' => $award->id,
        'student_ids' => [$student->id],
        'academic_year_id' => $year->id,
        'awarded_date' => '2026-08-01',
        'notes' => 'Term prize',
    ], $admin->id);

    expect($issued)->toHaveCount(1)
        ->and($issued->first()->certificate_document_id)->not->toBeNull()
        ->and(StudentAward::query()->count())->toBe(1);

    $this->withoutLocalizationMiddleware()
        ->actingAs(User::query()->findOrFail($guardian->user_id))
        ->get(route('portal.awards'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Awards')
            ->has('awards', 1)
            ->where('awards.0.award', 'Honour roll')
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('exams.awards.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('ExamsGrades/Awards/Index'));

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('exams.awards.export'))
        ->assertOk()
        ->streamedContent();
    expect($csv)->toContain('Aisha')->toContain('Honour roll');
});

it('renders an id card with the student number qr and a transfer certificate from status history', function () {
    $admin = actingPeopleAdmin(['exams.manage']);
    $student = makeStudent(['first_name' => 'Bilal', 'student_id' => 'STU-2002']);
    app(ChangeStudentStatusAction::class)->execute($student, StudentStatus::Transferred, $admin->id, 'Moved island', '2026-07-01');

    $id = app(GenerateIdCardAction::class)->execute($student->id, $admin->id);
    expect($id['html'])->toContain('STU-2002')
        ->and($id['html'])->toContain('data-qr="STU-2002"')
        ->and($id['html'])->toContain('Student ID');

    $transfer = app(GenerateTransferCertificateAction::class)->execute($student->id, $admin->id);
    expect($transfer['html'])->toContain('Transfer / leaving certificate')
        ->and($transfer['html'])->toContain('transferred')
        ->and($transfer['html'])->toContain('Moved island');

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('exams.awards.id-card', ['student_id' => $student->id]))
        ->assertOk()
        ->assertSee('STU-2002');
});

it('shows school awards on the public site and gates photos by photo_media_use', function () {
    $admin = actingPeopleAdmin(['exams.manage']);
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $withPhoto = makeStudent(['first_name' => 'Consenting']);
    $withoutPhoto = makeStudent(['first_name' => 'Private']);
    app(RecordConsentAction::class)->execute(
        'student',
        $withPhoto->id,
        'photo_media_use',
        true,
        $admin->id,
        'admin',
    );

    $award = app(SaveAwardAction::class)->execute([
        'title' => 'Quran prize',
        'level' => AwardLevel::School->value,
    ]);
    app(IssueStudentAwardsAction::class)->execute([
        'award_id' => $award->id,
        'student_ids' => [$withPhoto->id, $withoutPhoto->id],
        'academic_year_id' => $year->id,
        'awarded_date' => '2026-08-10',
    ], $admin->id);

    $rows = app(ListPublicAchievementsAction::class)->execute();
    expect($rows)->toHaveCount(2)
        ->and($rows->firstWhere('student_name', 'Consenting Ali')['photo_allowed'])->toBeTrue()
        ->and($rows->firstWhere('student_name', 'Private Ali')['photo_allowed'])->toBeFalse();

    $this->withoutLocalizationMiddleware()
        ->get(route('public.achievements'))
        ->assertOk()
        ->assertSee('Quran prize')
        ->assertSee('Consenting')
        ->assertSee('Private');

    expect(app(DocumentRendererInterface::class)->render('award-certificate', [
        'locale' => 'en',
        'dir' => 'ltr',
        'award' => ['title' => 'Quran prize', 'description' => null, 'level' => 'school'],
        'student' => ['name' => 'Consenting Ali', 'number' => null],
        'awarded_date' => '2026-08-10',
        'notes' => null,
    ]))->toContain('Certificate of achievement');
});
