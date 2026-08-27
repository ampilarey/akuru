<?php

use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\Hifz\Models\HifzSession;
use App\Domains\Offerings\Actions\ListOfferingSessionsAction;
use App\Domains\Offerings\Actions\VerifyHalaqaMirrorAction;
use App\Domains\Offerings\Models\CourseOffering;
use App\Domains\Offerings\Models\CourseOfferingSession;
use App\Domains\Offerings\Models\OfferingHalaqaLink;
use App\Domains\Offerings\Models\OfferingHalaqaSessionLink;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedMirrorFixture(bool $dualWrite = true): array
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Mirror verify',
        'subject_id' => CourseSubject::query()->where('slug', 'nahw')->value('id'),
        'created_by' => $admin->id,
    ]);
    $offering = CourseOffering::query()->create([
        'course_id' => $course->id,
        'title' => 'Mirror halaqa',
        'slug' => 'mirror-halaqa-'.($dualWrite ? 'on' : 'off'),
        'delivery_mode' => 'face_to_face',
        'status' => 'open',
        'pin_mode' => 'latest',
        'created_by' => $admin->id,
    ]);
    $program = HifzProgram::query()->create(['name' => 'Mirror program', 'status' => 'active']);
    $link = OfferingHalaqaLink::query()->create([
        'course_offering_id' => $offering->id,
        'hifz_program_id' => $program->id,
        'dual_write' => $dualWrite,
    ]);
    $teacher = makeTeacherRow();
    $hifzSession = HifzSession::query()->create([
        'hifz_program_id' => $program->id,
        'teacher_id' => $teacher->id,
        'session_date' => now()->subDay()->toDateString(),
        'title' => 'Morning halaqa',
        'status' => 'draft',
    ]);

    return compact('offering', 'program', 'link', 'hifzSession');
}

it('fails on an unmirrored session, heals with mirror-missing, then passes', function () {
    config()->set('quran.halaqa_dual_write', true);
    $ctx = seedMirrorFixture();

    $report = app(VerifyHalaqaMirrorAction::class)->execute();
    expect($report['ok'])->toBeFalse()
        ->and($report['missing'])->toHaveCount(1)
        ->and($report['missing'][0]['hifz_session_id'])->toBe($ctx['hifzSession']->id);

    $this->artisan('halaqa:verify-mirror')->assertFailed();
    $this->artisan('halaqa:verify-mirror', ['--mirror-missing' => true])->assertSuccessful();

    $report = app(VerifyHalaqaMirrorAction::class)->execute();
    expect($report['ok'])->toBeTrue()
        ->and($report['mirrored'])->toBe(1)
        ->and(OfferingHalaqaSessionLink::query()->count())->toBe(1);

    // Idempotent: a second heal creates nothing new.
    expect(app(VerifyHalaqaMirrorAction::class)->mirrorMissing())->toBe(0);
});

it('ignores links that are not dual-write and flags orphaned links', function () {
    $ctx = seedMirrorFixture(dualWrite: false);

    // Mapped-but-not-dual-writing links may lag without failing the gate.
    expect(app(VerifyHalaqaMirrorAction::class)->execute()['ok'])->toBeTrue();

    // An engine session that vanishes under its link is drift the other way.
    $ctx['link']->update(['dual_write' => true]);
    $engine = CourseOfferingSession::query()->create([
        'course_offering_id' => $ctx['offering']->id,
        'title' => 'Ghost',
        'session_type' => 'face_to_face',
        'starts_at' => now(),
        'ends_at' => now()->addHour(),
    ]);
    OfferingHalaqaSessionLink::query()->create([
        'course_offering_session_id' => $engine->id,
        'hifz_session_id' => $ctx['hifzSession']->id,
    ]);
    $engine->delete();

    $report = app(VerifyHalaqaMirrorAction::class)->execute();
    expect($report['ok'])->toBeFalse()
        ->and($report['orphan_links'])->toHaveCount(1);
});

it('exposes engine read source and unmirrored ids without changing existing keys', function () {
    $ctx = seedMirrorFixture();

    $payload = app(ListOfferingSessionsAction::class)->execute($ctx['offering']->id);

    expect($payload['read_source'])->toBe('engine')
        ->and($payload['unmirrored_halaqa_session_ids'])->toBe([$ctx['hifzSession']->id])
        ->and($payload)->toHaveKeys(['halaqa_sessions', 'sessions', 'dual_write_enabled']);

    $this->artisan('halaqa:verify-mirror', ['--mirror-missing' => true])->assertSuccessful();

    $payload = app(ListOfferingSessionsAction::class)->execute($ctx['offering']->id);
    expect($payload['unmirrored_halaqa_session_ids'])->toBe([]);
});
