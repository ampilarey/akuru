<?php

use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\Hifz\Models\HifzSession;
use App\Domains\Offerings\Actions\SaveOfferingHalaqaLinkAction;
use App\Domains\Offerings\Actions\SaveOfferingHalaqaSessionLinkAction;
use App\Domains\Offerings\Actions\SaveOfferingSessionAction;
use App\Domains\Offerings\Models\CourseOffering;
use App\Domains\Offerings\Models\OfferingHalaqaLink;
use App\Support\Contracts\HalaqaReferenceReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('maps an offering and session onto existing hifz records without writing hifz tables', function () {
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Halaqa map',
        'subject_id' => CourseSubject::query()->where('slug', 'nahw')->value('id'),
        'created_by' => $admin->id,
    ]);
    $offering = CourseOffering::query()->create([
        'course_id' => $course->id,
        'title' => 'Morning halaqa',
        'slug' => 'morning-halaqa',
        'delivery_mode' => 'face_to_face',
        'status' => 'open',
        'pin_mode' => 'latest',
        'created_by' => $admin->id,
    ]);
    $session = app(SaveOfferingSessionAction::class)->execute([
        'course_offering_id' => $offering->id,
        'title' => 'Tuesday circle',
        'session_type' => 'face_to_face',
        'starts_at' => now()->addDay(),
        'created_by' => $admin->id,
    ]);

    $program = HifzProgram::query()->create([
        'name' => 'Boys halaqa',
        'status' => 'active',
    ]);
    $teacher = makeTeacherRow();
    $hifzSession = HifzSession::query()->create([
        'hifz_program_id' => $program->id,
        'teacher_id' => $teacher->id,
        'session_date' => now()->toDateString(),
        'title' => 'Existing Hifz session',
        'status' => 'draft',
    ]);

    $reader = app(HalaqaReferenceReader::class);
    expect($reader->listPrograms())->toHaveCount(1)
        ->and($reader->findProgram($program->id)['name'])->toBe('Boys halaqa')
        ->and($reader->listSessions($program->id))->toHaveCount(1);

    app(SaveOfferingHalaqaLinkAction::class)->execute([
        'course_offering_id' => $offering->id,
        'hifz_program_id' => $program->id,
    ]);
    app(SaveOfferingHalaqaSessionLinkAction::class)->execute([
        'course_offering_session_id' => $session->id,
        'hifz_session_id' => $hifzSession->id,
    ]);

    expect(OfferingHalaqaLink::query()->where('course_offering_id', $offering->id)->value('hifz_program_id'))
        ->toBe($program->id)
        ->and(HifzProgram::query()->count())->toBe(1)
        ->and(HifzSession::query()->count())->toBe(1);

    expect(fn () => app(SaveOfferingHalaqaLinkAction::class)->execute([
        'course_offering_id' => $offering->id,
        'hifz_program_id' => 999999,
    ]))->toThrow(ValidationException::class);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.offerings.sessions.index', $offering->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Offerings/Catalog/Sessions')
            ->has('programs', 1)
            ->where('halaqa.hifz_program_id', $program->id)
            ->where('sessions.0.hifz_session_id', $hifzSession->id)
        );
});

it('lets catalog staff post halaqa links', function () {
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Halaqa http',
        'subject_id' => CourseSubject::query()->where('slug', 'nahw')->value('id'),
        'created_by' => $admin->id,
    ]);
    $offering = CourseOffering::query()->create([
        'course_id' => $course->id,
        'title' => 'HTTP halaqa',
        'slug' => 'http-halaqa',
        'delivery_mode' => 'face_to_face',
        'status' => 'open',
        'pin_mode' => 'latest',
    ]);
    $program = HifzProgram::query()->create([
        'name' => 'Girls halaqa',
        'status' => 'active',
    ]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('catalog.offerings.halaqa.store', $offering->id), [
            'hifz_program_id' => $program->id,
        ])
        ->assertRedirect(route('catalog.offerings.sessions.index', $offering->id));

    expect(OfferingHalaqaLink::query()->where('course_offering_id', $offering->id)->exists())->toBeTrue();
});

it('does not let offerings php import the hifz namespace', function () {
    $root = base_path('app/Domains/Offerings');
    $violations = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        if (str_contains((string) file_get_contents($file->getPathname()), 'App\\Domains\\Hifz\\')) {
            $violations[] = str_replace(base_path().'/', '', $file->getPathname());
        }
    }

    expect($violations)->toBeEmpty();
});
