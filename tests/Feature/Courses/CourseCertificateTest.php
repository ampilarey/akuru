<?php

use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\IssueCertificateAction;
use App\Domains\Courses\Actions\SaveCertificateTemplateAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CertificateKind;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CertificateTemplate;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Courses\Models\IssuedCertificate;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function publishCertificateCourse(): array
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Nahw Completion Lab',
        'subject_id' => CourseSubject::query()->where('slug', 'nahw')->value('id'),
        'created_by' => $admin->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    $user = User::factory()->create(['email' => 'secret-student@example.test']);
    $student = makeStudent([
        'user_id' => $user->id,
        'first_name' => 'Yusuf',
        'last_name' => 'Manik',
        'national_id' => 'A123456',
        'student_id' => 'STU-SECRET',
    ]);
    $year = makeYear(['is_current' => true, 'status' => 'active']);

    return compact('admin', 'course', 'user', 'student', 'year');
}

it('saves a trilingual template and exports issued certificates as csv', function () {
    $admin = actingPeopleAdmin(['courses.manage']);
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $student = makeStudent(['first_name' => 'Aisha']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('catalog.certificates.store'), [
            'name' => 'Manual honour',
            'name_dv' => 'އިއްޒަތް',
            'name_ar' => 'شهادة',
            'kind' => CertificateKind::Manual->value,
            'body_html' => '<p>This certifies that <strong>{{student_name}}</strong> completed {{course_name}}.</p>',
            'active' => true,
            'rules' => ['min_progress_percent' => 80],
        ])
        ->assertRedirect(route('catalog.certificates.index'));

    $template = CertificateTemplate::query()->where('name', 'Manual honour')->sole();
    expect($template->name_ar)->toBe('شهادة')
        ->and($template->kind)->toBe(CertificateKind::Manual)
        ->and($template->rules['min_progress_percent'])->toBe(80);

    app(IssueCertificateAction::class)->execute([
        'certificate_template_id' => $template->id,
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
    ], $admin->id);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.certificates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Catalog/Certificates')
            ->has('templates', 1)
            ->has('issued', 1)
            ->where('templates.0.name_ar', 'شهادة')
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.certificates.export'));
    $csv->assertOk();
    expect($csv->headers->get('content-type'))->toStartWith('text/csv')
        ->and($csv->streamedContent())->toContain('Aisha')
        ->and($csv->streamedContent())->toContain('issued');
});

it('issues a manual certificate as HTML with a QR verify URL and never labels it PDF', function () {
    ['admin' => $admin, 'student' => $student, 'year' => $year] = publishCertificateCourse();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('catalog.certificates.store'), [
            'name' => 'Course completion',
            'kind' => CertificateKind::Manual->value,
            'active' => true,
        ])
        ->assertRedirect(route('catalog.certificates.index'));

    $template = CertificateTemplate::query()->sole();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('catalog.certificates.issue'), [
            'certificate_template_id' => $template->id,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'grade' => 'A',
        ])
        ->assertRedirect(route('catalog.certificates.index'));

    $issued = IssuedCertificate::query()->sole();
    expect($issued->public_id)->toHaveLength(26)
        ->and($issued->document_id)->not->toBeNull()
        ->and($issued->academic_year_id)->toBe($year->id);

    $html = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.certificates.download', $issued))
        ->assertOk()
        ->assertHeader('content-type', 'text/html; charset=UTF-8')
        ->getContent();

    expect($html)->toContain('Yusuf Manik')
        ->and($html)->toContain($issued->certificate_number)
        ->and($html)->toContain('/verify/certificates/'.$issued->public_id)
        ->and($html)->toContain('data-qr="')
        ->and($html)->not->toContain('PDF')
        ->and($html)->not->toContain('application/pdf');
});

it('blocks course completion below min progress and allows it at or above', function () {
    ['admin' => $admin, 'course' => $course, 'user' => $user, 'student' => $student, 'year' => $year] = publishCertificateCourse();

    $enrollment = app(EnrollSelfLearningAction::class)->execute($user->id, $course->id);
    $enrollment->update(['progress_percentage' => 40]);

    $template = app(SaveCertificateTemplateAction::class)->execute([
        'name' => 'Completion',
        'kind' => CertificateKind::CourseCompletion->value,
        'course_id' => $course->id,
        'active' => true,
        'created_by' => $admin->id,
        'rules' => ['min_progress_percent' => 80],
    ]);

    expect(fn () => app(IssueCertificateAction::class)->execute([
        'certificate_template_id' => $template->id,
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'course_id' => $course->id,
    ], $admin->id))->toThrow(ValidationException::class);

    CourseEnrollment::query()->whereKey($enrollment->id)->update(['progress_percentage' => 80]);

    $issued = app(IssueCertificateAction::class)->execute([
        'certificate_template_id' => $template->id,
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'course_id' => $course->id,
    ], $admin->id);

    expect($issued->id)->toBeInt();

    expect(fn () => app(IssueCertificateAction::class)->execute([
        'certificate_template_id' => $template->id,
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'course_id' => $course->id,
    ], $admin->id))->toThrow(ValidationException::class);
});

it('lets a guest verify authenticity and shows only the certificate face', function () {
    ['admin' => $admin, 'student' => $student, 'year' => $year] = publishCertificateCourse();
    $template = app(SaveCertificateTemplateAction::class)->execute([
        'name' => 'Face only',
        'kind' => CertificateKind::Manual->value,
        'active' => true,
        'created_by' => $admin->id,
    ]);
    $issued = app(IssueCertificateAction::class)->execute([
        'certificate_template_id' => $template->id,
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'grade' => 'B',
    ], $admin->id);

    $this->get(route('public.certificates.verify', $issued->public_id))
        ->assertOk()
        ->assertSee('Yusuf Manik')
        ->assertSee($issued->certificate_number)
        ->assertSee('This certificate is authentic.')
        ->assertSee('B')
        ->assertDontSee('secret-student@example.test')
        ->assertDontSee('A123456')
        ->assertDontSee('STU-SECRET')
        ->assertDontSee('national_id');

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('catalog.certificates.revoke', $issued))
        ->assertRedirect(route('catalog.certificates.index'));

    $this->get(route('public.certificates.verify', $issued->public_id))
        ->assertOk()
        ->assertSee('This certificate has been revoked.')
        ->assertDontSee('secret-student@example.test');
});

it('returns a generic 404 for unknown or malformed public tokens', function () {
    $this->get('/verify/certificates/01ARZ3NDEKTSV4RRFFQ69G5FAV')->assertNotFound();
    $this->get('/verify/certificates/not-a-token')->assertNotFound();
    $this->get('/verify/certificates/1')->assertNotFound();
});

it('forbids the certificate screens without courses.manage', function () {
    $other = actingPeopleAdmin(['hr.manage']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($other)
        ->get(route('catalog.certificates.index'))
        ->assertForbidden();
});

it('registers morph-map aliases for certificate models', function () {
    expect(config('morph-map'))->toHaveKeys(['certificate_template', 'issued_certificate'])
        ->and(config('morph-map.certificate_template'))->toBe(CertificateTemplate::class)
        ->and(config('morph-map.issued_certificate'))->toBe(IssuedCertificate::class);
});
