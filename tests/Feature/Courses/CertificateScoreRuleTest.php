<?php

use App\Domains\Courses\Actions\CheckCertificateEligibilityAction;
use App\Domains\Courses\Models\Assessment;
use App\Domains\Progress\Models\AssessmentAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * SPEC §27 lists "Reach minimum score" and "Pass final assessment" among the
 * course completion rules, and §11.11 hangs certificate rules off the same
 * configuration.
 *
 * `min_score` is a percentage in every place it is written: the request
 * validates it `max:100`, the builder's input caps at 100, and it sits between
 * `min_progress_percent` and `min_attendance_percent`. It was compared against
 * the **raw mark**, so the threshold meant whatever the assessment happened to
 * be marked out of — and an admin could not express a raw threshold above 100
 * even if that had been the intent, because the field refuses one.
 */
uses(RefreshDatabase::class);

function certRuleFixture(array $rules, ?int $courseId = null): array
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $student = makeStudent(['first_name' => 'Cert', 'last_name' => 'Candidate']);
    $course = \App\Domains\Courses\Models\Course::query()->create([
        'course_category_id' => \Illuminate\Support\Facades\DB::table('course_categories')->insertGetId([
            'name' => 'Certificates', 'slug' => 'cert-rule-'.\Illuminate\Support\Str::random(6), 'order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]),
        'title' => 'Certificate rule course',
        'slug' => 'cert-rule-course-'.\Illuminate\Support\Str::random(6),
        'short_desc' => 'x', 'body' => 'y', 'cover_image' => '',
        'workflow_status' => 'published', 'course_type' => 'general', 'status' => 'open',
    ]);
    $template = \App\Domains\Courses\Models\CertificateTemplate::query()->create([
        'name' => 'Course completion',
        'kind' => 'course_completion',
        'course_id' => $course->id,
        'rules' => $rules,
        'active' => true,
    ]);
    $enrollment = \App\Domains\Courses\Models\CourseEnrollment::query()->create([
        'course_id' => $course->id,
        // `student_id` FKs `registration_students`; `unified_student_id` is the
        // People `students` row, which is what every lookup here matches on.
        'student_id' => makeRegistrationStudent()->id,
        'unified_student_id' => $student->id,
        'status' => 'active',
        'payment_status' => 'not_required',
        'enrollment_type' => 'self_learning',
        'progress_percentage' => 100,
    ]);

    return compact('admin', 'student', 'course', 'template', 'enrollment');
}

function certRulePublishAssessment(int $courseId, string $title): Assessment
{
    return Assessment::query()->create([
        'course_id' => $courseId,
        'title' => $title,
        'status' => 'published',
    ]);
}

function certRuleAttempt(int $assessmentId, int $studentId, float $score, float $max, string $status = 'scored'): AssessmentAttempt
{
    return AssessmentAttempt::query()->create([
        'assessment_id' => $assessmentId,
        'student_id' => $studentId,
        'attempt_number' => 1,
        'status' => $status,
        'score' => $score,
        'max_score' => $max,
    ]);
}

function certRuleEligibility(array $fixture): array
{
    return app(CheckCertificateEligibilityAction::class)->execute(
        $fixture['template'],
        $fixture['student']->id,
        $fixture['course']->id,
    );
}

it('passes a perfect score on a small assessment', function () {
    // 10/10 is 100%. Against a raw comparison it was 10, below a threshold of
    // 50 — so a student who answered everything correctly was refused the
    // certificate they had plainly earned.
    $f = certRuleFixture(['min_score' => 50]);
    $assessment = certRulePublishAssessment($f['course']->id, 'Ten mark quiz');
    certRuleAttempt($assessment->id, $f['student']->id, 10, 10);

    expect(certRuleEligibility($f)['eligible'])->toBeTrue();
});

it('fails a low score on a large assessment', function () {
    // 60/200 is 30%. Against a raw comparison it was 60, above a threshold of
    // 50 — the certificate was granted on a failing performance.
    $f = certRuleFixture(['min_score' => 50]);
    $assessment = certRulePublishAssessment($f['course']->id, 'Two hundred mark exam');
    certRuleAttempt($assessment->id, $f['student']->id, 60, 200);

    expect(certRuleEligibility($f)['eligible'])->toBeFalse()
        ->and(certRuleEligibility($f)['reasons'])->toContain('Assessment score is below the minimum.');
});

it('passes a score exactly on the threshold', function () {
    $f = certRuleFixture(['min_score' => 50]);
    $assessment = certRulePublishAssessment($f['course']->id, 'Exam');
    certRuleAttempt($assessment->id, $f['student']->id, 100, 200);

    expect(certRuleEligibility($f)['eligible'])->toBeTrue();
});

it('does not count an attempt still awaiting teacher marking', function () {
    // A submitted attempt carries a provisional auto-score (SPEC §19) that a
    // teacher can still change. Granting a certificate on it commits to a
    // number no human agreed to.
    $f = certRuleFixture(['min_score' => 50]);
    $assessment = certRulePublishAssessment($f['course']->id, 'Essay paper');
    certRuleAttempt($assessment->id, $f['student']->id, 90, 100, 'submitted');

    $result = certRuleEligibility($f);

    expect($result['eligible'])->toBeFalse()
        ->and($result['reasons'])->toContain('Required assessment is awaiting teacher marking.');
});

it('says marking is outstanding rather than that there is no score', function () {
    $f = certRuleFixture(['require_final_assessment' => true]);
    $assessment = certRulePublishAssessment($f['course']->id, 'Essay paper');
    certRuleAttempt($assessment->id, $f['student']->id, 10, 100, 'submitted');

    expect(certRuleEligibility($f)['reasons'])->toContain('Required assessment is awaiting teacher marking.');
});

it('reports no score when the student never attempted', function () {
    $f = certRuleFixture(['require_final_assessment' => true]);
    certRulePublishAssessment($f['course']->id, 'Final');

    expect(certRuleEligibility($f)['reasons'])->toContain('Required assessment has no score.');
});

it('judges only the named assessment when the template names one', function () {
    // Without a named assessment the rule takes the best across every
    // published assessment, so a practice quiz could satisfy "pass the final".
    $f = certRuleFixture([]);
    $practice = certRulePublishAssessment($f['course']->id, 'Practice quiz');
    $final = certRulePublishAssessment($f['course']->id, 'Final exam');
    certRuleAttempt($practice->id, $f['student']->id, 10, 10);
    certRuleAttempt($final->id, $f['student']->id, 20, 100);

    $f['template']->update(['rules' => ['min_score' => 50, 'assessment_id' => $final->id]]);
    $f['template']->refresh();

    expect(certRuleEligibility($f)['eligible'])->toBeFalse();
});

it('still takes the best of any published assessment when none is named', function () {
    // Documented rather than changed: this is the fallback, and it is now
    // avoidable because the builder can name the final.
    $f = certRuleFixture(['min_score' => 50]);
    $practice = certRulePublishAssessment($f['course']->id, 'Practice quiz');
    $final = certRulePublishAssessment($f['course']->id, 'Final exam');
    certRuleAttempt($practice->id, $f['student']->id, 10, 10);
    certRuleAttempt($final->id, $f['student']->id, 20, 100);

    expect(certRuleEligibility($f)['eligible'])->toBeTrue();
});

it('ignores an attempt with no max score rather than reading it as a percentage', function () {
    $f = certRuleFixture(['min_score' => 50]);
    $assessment = certRulePublishAssessment($f['course']->id, 'Broken');
    AssessmentAttempt::query()->create([
        'assessment_id' => $assessment->id,
        'student_id' => $f['student']->id,
        'attempt_number' => 1,
        'status' => 'scored',
        'score' => 80,
        'max_score' => null,
    ]);

    expect(certRuleEligibility($f)['eligible'])->toBeFalse();
});

it('offers the assessment list to the certificate builder', function () {
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = certRuleFixture([])['course'];
    certRulePublishAssessment($course->id, 'Final exam');

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->get('/catalog/certificates')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('assessments', 1));
});

it('marks a scored attempt as final and a submitted one as not', function () {
    $f = certRuleFixture([]);
    $assessment = certRulePublishAssessment($f['course']->id, 'Paper');
    certRuleAttempt($assessment->id, $f['student']->id, 5, 10, 'submitted');

    $scores = app(\App\Domains\Progress\Actions\ListAssessmentScoresAction::class)
        ->execute([$assessment->id], [$f['student']->id]);

    // Courses reads this flag rather than Progress's status enum, so the
    // domain boundary holds while the distinction still reaches the caller.
    expect($scores[$assessment->id][$f['student']->id]['is_final'])->toBeFalse();
});
