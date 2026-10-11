<?php

use App\Domains\Courses\Actions\IssueCertificateAction;
use App\Domains\Courses\Enums\CertificateKind;
use App\Domains\Courses\Models\CertificateTemplate;
use App\Domains\ExamsGrades\Actions\GenerateIdCardAction;
use App\Domains\ExamsGrades\Actions\GenerateTransferCertificateAction;
use App\Domains\ExamsGrades\Actions\IssueStudentAwardsAction;
use App\Domains\ExamsGrades\Actions\SaveAwardAction;
use App\Domains\ExamsGrades\Enums\AwardLevel;
use App\Domains\People\Actions\ChangeStudentStatusAction;
use App\Domains\People\Enums\StudentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The certificates and the student ID card in Dhivehi and Arabic (BACKLOG
 * C21, slice DOC2, STATUS §5rj).
 *
 * The award certificate, the course certificate, the ID card and the
 * transfer certificate were each written in English, whatever page asked for
 * them: every action passed `'locale' => 'en'`. A student's status was
 * printed as its code (*transferred*), a date as `2026-08-01`, and a course
 * certificate carried its template's English name though the office names
 * each template in Dhivehi and Arabic too.
 */
uses(RefreshDatabase::class);

function doc2Views(): array
{
    return ['award-certificate', 'course-certificate', 'id-card', 'transfer-certificate'];
}

it('prints no English of its own on the four documents', function () {
    $found = [];
    foreach (doc2Views() as $view) {
        $path = resource_path("views/documents/{$view}.blade.php");
        foreach ([...bladeBareEnglish($path), ...bladeEnglishLiterals($path)] as $text) {
            $found[] = "{$view}: {$text}";
        }
    }
    expect($found)->toBe([]);

    foreach (['award_certificate', 'course_certificate', 'id_card', 'transfer_certificate'] as $section) {
        $en = (require base_path('resources/lang/en/documents.php'))[$section];
        foreach (array_keys($en) as $key) {
            expect((require base_path('resources/lang/dv/documents.php'))[$section][$key] ?? '')->toMatch('/\p{Thaana}/u', "{$section}.{$key}")
                ->and((require base_path('resources/lang/ar/documents.php'))[$section][$key] ?? '')->toMatch('/\p{Arabic}/u', "{$section}.{$key}");
        }
    }

    foreach (['ExamsGrades/Actions/GenerateIdCardAction', 'ExamsGrades/Actions/GenerateTransferCertificateAction', 'ExamsGrades/Actions/IssueStudentAwardsAction', 'Courses/Actions/IssueCertificateAction'] as $action) {
        expect((string) file_get_contents(app_path("Domains/{$action}.php")))->not->toContain("'locale' => 'en'", $action);
    }
});

it('writes the ID card and the transfer certificate in the language of the page that asked', function () {
    $admin = actingPeopleAdmin(['exams.manage']);
    $student = makeStudent(['first_name' => 'Bilal', 'student_id' => 'STU-2002']);
    app(ChangeStudentStatusAction::class)->execute($student, StudentStatus::Transferred, $admin->id, 'Moved island', '2026-07-01');
    $dv = require base_path('resources/lang/dv/documents.php');

    $card = app(GenerateIdCardAction::class)->execute($student->id, $admin->id, 'dv')['html'];
    expect($card)->toContain('dir="rtl"')
        ->toContain(e($dv['id_card']['heading']))
        ->toContain(e($dv['id_card']['number']))
        ->toContain('STU-2002')
        ->not->toContain('Student ID');

    $transfer = app(GenerateTransferCertificateAction::class)->execute($student->id, $admin->id, 'dv')['html'];
    expect($transfer)->toContain(e($dv['transfer_certificate']['heading']))
        ->toContain(e($dv['transcript']['statuses']['transferred']))
        ->toContain('ޖުލައި')
        ->toContain('Moved island')
        ->not->toContain('Transfer / leaving');

    // From a Dhivehi page, the office's own route writes it in Dhivehi.
    $this->withoutLocalizationMiddleware()->actingAs($admin);
    app()->setLocale('dv');
    $this->get(route('exams.awards.id-card', ['student_id' => $student->id]))->assertOk()
        ->assertSee($dv['id_card']['heading']);
});

it('writes an award certificate in Arabic, its date by the month\'s name', function () {
    $admin = actingPeopleAdmin(['exams.manage']);
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $student = makeStudent(['first_name' => 'Aisha']);
    $award = app(SaveAwardAction::class)->execute(['title' => 'Honour roll', 'level' => AwardLevel::School->value]);

    $issued = app(IssueStudentAwardsAction::class)->execute([
        'award_id' => $award->id, 'student_ids' => [$student->id], 'academic_year_id' => $year->id, 'awarded_date' => '2026-08-01',
    ], $admin->id, 'ar');

    $html = app(\App\Domains\Media\Actions\ReadGeneratedDocumentAction::class)->execute((int) $issued->first()->certificate_document_id)['contents'];
    $ar = require base_path('resources/lang/ar/documents.php');
    expect($html)->toContain('dir="rtl"')
        ->toContain(e($ar['award_certificate']['heading']))
        ->toContain('أغسطس')
        ->not->toContain('Certificate of achievement');
});

it('writes a course certificate in Dhivehi under its Dhivehi name', function () {
    $admin = actingPeopleAdmin(['courses.manage']);
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $student = makeStudent(['first_name' => 'Aisha']);
    $template = CertificateTemplate::query()->create([
        'name' => 'Manual honour', 'name_dv' => 'އިއްޒަތުގެ ސެޓްފިކެޓް', 'name_ar' => 'شهادة شرف',
        'kind' => CertificateKind::Manual->value, 'active' => true,
    ]);

    $issued = app(IssueCertificateAction::class)->execute([
        'certificate_template_id' => $template->id, 'student_id' => $student->id, 'academic_year_id' => $year->id,
    ], $admin->id, 'dv');

    $html = $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('catalog.certificates.download', $issued))->assertOk()->getContent();
    $dv = require base_path('resources/lang/dv/documents.php');
    expect($html)->toContain('dir="rtl"')
        ->toContain('އިއްޒަތުގެ ސެޓްފިކެޓް')
        ->toContain(e($dv['course_certificate']['number']))
        ->toContain(e($dv['course_certificate']['signature']))
        ->toContain('Aisha')
        ->not->toContain('This certifies')
        ->not->toContain('Manual honour')
        ->not->toContain('Authorized signature');
});
