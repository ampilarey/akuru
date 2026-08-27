<?php

use App\Domains\Courses\Actions\ComposeCoursePageCtaAction;
use App\Domains\Courses\Models\Course;
use App\Domains\Media\Models\MediaFile;
use App\Domains\Settings\Models\Setting;
use App\Domains\Website\Enums\LeadSource;
use App\Domains\Website\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function leadCourse(array $overrides = []): Course
{
    return Course::factory()->create(array_merge([
        'title' => 'W14 CTA Course',
        'slug' => 'w14-cta-'.fake()->unique()->numerify('###'),
        'status' => 'open',
        'fee' => 450,
        'seats' => 20,
        'whatsapp_number' => null,
        'syllabus_media_file_id' => null,
    ], $overrides));
}

function publicSyllabusFile(string $name = 'arabic-beginners.pdf'): MediaFile
{
    Storage::fake('public');
    $path = 'syllabi/'.$name;
    Storage::disk('public')->put($path, '%PDF-w14');

    return MediaFile::query()->create([
        'disk' => 'public',
        'path' => $path,
        'mime' => 'application/pdf',
        'original_name' => $name,
        'size' => 8,
        'visibility' => 'public',
        'process_status' => 'processed',
        'processed_at' => now(),
        'meta' => ['alt' => $name],
    ]);
}

it('builds a WhatsApp deep link from the course number and falls back to settings', function () {
    Setting::set('viber', '9607972434');
    Setting::set('conversion.whatsapp_number', '9603334444');

    $own = leadCourse(['whatsapp_number' => '960 111 2222', 'title' => 'W14 Own Number']);
    $fallback = leadCourse(['whatsapp_number' => null, 'title' => 'W14 Settings Number', 'slug' => 'w14-settings-number']);

    $action = app(ComposeCoursePageCtaAction::class);
    expect($action->execute($own->id)['whatsapp_url'])
        ->toBe('https://wa.me/9601112222?text='.rawurlencode('W14 Own Number'))
        ->and($action->execute($fallback->id)['whatsapp_url'])
        ->toBe('https://wa.me/9603334444?text='.rawurlencode('W14 Settings Number'));

    $this->withoutLocalizationMiddleware()
        ->get(route('public.courses.show', $own))
        ->assertOk()
        ->assertSee('id="course-sticky-cta"', false)
        ->assertSee('id="sticky-whatsapp"', false)
        ->assertSee('https://wa.me/9601112222', false)
        ->assertSee('Register', false)
        ->assertDontSee('https://wa.me/9603334444', false);
});

it('omits WhatsApp when the course and settings numbers are blank', function () {
    Setting::set('viber', '');
    Setting::set('conversion.whatsapp_number', '');
    $course = leadCourse(['whatsapp_number' => null, 'title' => 'W14 Silent WhatsApp']);

    expect(app(ComposeCoursePageCtaAction::class)->execute($course->id)['whatsapp_url'])->toBeNull();

    $this->withoutLocalizationMiddleware()
        ->get(route('public.courses.show', $course))
        ->assertOk()
        ->assertDontSee('wa.me/', false)
        ->assertDontSee('Ask on WhatsApp', false)
        ->assertDontSee('id="sticky-whatsapp"', false);
});

it('hides the syllabus magnet until a public media file is attached', function () {
    $bare = leadCourse(['slug' => 'w14-no-syllabus']);
    $file = publicSyllabusFile();
    $withFile = leadCourse(['slug' => 'w14-has-syllabus', 'syllabus_media_file_id' => $file->id]);

    $this->withoutLocalizationMiddleware()
        ->get(route('public.courses.show', $bare))
        ->assertOk()
        ->assertDontSee('Get full syllabus', false);

    $this->withoutLocalizationMiddleware()
        ->get(route('public.courses.show', $withFile))
        ->assertOk()
        ->assertSee('Get full syllabus', false);
});

it('stores a syllabus lead and flashes the public PDF link', function () {
    $file = publicSyllabusFile('w14-syllabus.pdf');
    $course = leadCourse(['syllabus_media_file_id' => $file->id]);

    $this->withoutLocalizationMiddleware()
        ->from(route('public.courses.show', $course))
        ->post(route('public.courses.syllabus', $course), [
            'name' => 'Hassan Ahmed',
            'mobile' => '7900123',
            'email' => 'hassan@example.com',
        ])
        ->assertRedirect()
        ->assertSessionHas('syllabus_url');

    expect(Lead::query()->count())->toBe(1);
    $lead = Lead::query()->sole();
    expect($lead->name)->toBe('Hassan Ahmed')
        ->and($lead->mobile)->toBe('7900123')
        ->and($lead->email)->toBe('hassan@example.com')
        ->and($lead->source)->toBe(LeadSource::Syllabus)
        ->and($lead->course_id)->toBe($course->id);

    $this->withoutLocalizationMiddleware()
        ->from(route('public.courses.show', $course))
        ->post(route('public.courses.syllabus', $course), [
            'website' => 'http://spam.test',
            'name' => 'Bot',
            'mobile' => '7900999',
        ])
        ->assertRedirect();

    expect(Lead::query()->count())->toBe(1);
});

it('rejects syllabus capture when the course has no public file', function () {
    $course = leadCourse();

    $this->withoutLocalizationMiddleware()
        ->from(route('public.courses.show', $course))
        ->post(route('public.courses.syllabus', $course), [
            'name' => 'Too Early',
            'mobile' => '7900001',
        ])
        ->assertSessionHasErrors('course');

    expect(Lead::query()->count())->toBe(0);
});

it('dual-writes a waiting-list inquiry onto the leads table', function () {
    $course = leadCourse(['seats' => 1, 'slug' => 'w14-full-waitlist']);
    $rs = makeRegistrationStudent(['first_name' => 'Wait', 'last_name' => 'Seat']);
    \Illuminate\Support\Facades\DB::table('course_enrollments')->insert([
        'student_id' => $rs->id,
        'course_id' => $course->id,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->withoutLocalizationMiddleware()
        ->from(route('public.courses.show', $course))
        ->post(route('public.courses.waitlist', $course), [
            'name' => 'Mariyam Wait',
            'phone' => '7900000',
            'email' => 'wait@example.com',
        ])
        ->assertRedirect();

    expect(Lead::query()->sole()->source)->toBe(LeadSource::WaitingList)
        ->and(Lead::query()->sole()->mobile)->toBe('7900000');
});

it('lists leads for staff and exports CSV', function () {
    $admin = actingPeopleAdmin();
    $course = leadCourse(['title' => 'W14 Listed Course']);
    Lead::query()->create([
        'course_id' => $course->id,
        'name' => 'Fatima Lead',
        'mobile' => '7771111',
        'email' => 'fatima@example.com',
        'source' => LeadSource::Syllabus,
        'status' => 'new',
    ]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('admin.leads.index'))
        ->assertOk()
        ->assertSee('Fatima Lead', false)
        ->assertSee('W14 Listed Course', false)
        ->assertSee('7771111', false);

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('admin.leads.export'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->streamedContent();

    expect($csv)->toContain('Fatima Lead')
        ->and($csv)->toContain('syllabus')
        ->and($csv)->toContain('W14 Listed Course');
});

it('saves WhatsApp and syllabus ids from the public-site admin form', function () {
    $admin = actingPeopleAdmin();
    $file = publicSyllabusFile();
    $course = leadCourse();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->from(route('admin.courses.edit', $course))
        ->put(route('admin.courses.update', $course), [
            'course_category_id' => $course->course_category_id,
            'title' => $course->title,
            'slug' => $course->slug,
            'short_desc' => $course->short_desc,
            'body' => $course->body,
            'cover_image' => $course->cover_image ?: 'courses/w14.jpg',
            'language' => $course->language,
            'level' => $course->level,
            'status' => $course->status,
            'fee' => $course->fee,
            'seats' => $course->seats,
            'whatsapp_number' => '+960 555 1212',
            'syllabus_media_file_id' => $file->id,
        ])
        ->assertRedirect(route('admin.courses.index'));

    $course->refresh();
    expect($course->whatsapp_number)->toBe('9605551212')
        ->and($course->syllabus_media_file_id)->toBe($file->id);
});

it('does not import other-domain models from new W1.4 files', function () {
    $files = [
        app_path('Domains/Courses/Actions/ComposeCoursePageCtaAction.php'),
        app_path('Domains/Courses/Actions/SaveCoursePublicCtaAction.php'),
        app_path('Domains/Courses/Actions/PresentCourseTitlesAction.php'),
        app_path('Domains/Website/Actions/CaptureCourseLeadAction.php'),
        app_path('Domains/Website/Actions/ListLeadsAction.php'),
        app_path('Domains/Website/Http/Controllers/Admin/PublicSite/LeadController.php'),
    ];
    foreach ($files as $file) {
        $src = file_get_contents($file);
        expect($src)->not->toContain('App\\Domains\\Hifz\\');
    }

    $website = [
        app_path('Domains/Website/Actions/CaptureCourseLeadAction.php'),
        app_path('Domains/Website/Actions/ListLeadsAction.php'),
        app_path('Domains/Website/Http/Controllers/Admin/PublicSite/LeadController.php'),
        app_path('Domains/Website/Models/Lead.php'),
    ];
    foreach ($website as $file) {
        expect(file_get_contents($file))->not->toMatch('/App\\\\Domains\\\\Courses\\\\Models\\\\/');
    }
});
