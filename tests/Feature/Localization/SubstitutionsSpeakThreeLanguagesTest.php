<?php

use App\Domains\Academics\Models\SubstitutionRequest;
use App\Domains\Academics\Models\TeacherAbsence;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The teachers' substitution requests and absences in Dhivehi and Arabic
 * (BACKLOG C21, slice TS1, STATUS §5rh), with the registers' last two
 * refusals.
 *
 * The seven Blade pages printed English, and so did what the server said on
 * them. Reading them found the pages barely worked:
 * - the resource's `{request}` never reached the methods' `$substitutionRequest`,
 *   so update, take and assign were handed an empty request: an open request
 *   could not be taken, saved or assigned;
 * - the New request button asked a policy nobody wrote, so it showed to
 *   nobody;
 * - Assign had a route and no form;
 * - the app layout prints no flash, so what the requests' pages were told was
 *   said nowhere;
 * - the absences list's heading and its Record button sat in sections the
 *   layout never prints, and nothing linked to the list.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutLocalizationMiddleware();
    app()->setLocale('dv');
});

function ts1Views(): array
{
    return [
        'substitutions/requests/index.blade.php',
        'substitutions/requests/create.blade.php',
        'substitutions/requests/edit.blade.php',
        'substitutions/requests/show.blade.php',
        'substitutions/absences/index.blade.php',
        'substitutions/absences/create.blade.php',
        'substitutions/absences/edit.blade.php',
        'substitutions/absences/_form.blade.php',
    ];
}

function ts1ServerFiles(): array
{
    return [
        'app/Domains/Academics/Http/Controllers/SubstitutionRequestController.php',
        'app/Domains/Academics/Actions/AttachMaterialsToLessonAction.php',
        'app/Domains/Academics/Actions/RecordClassAttendanceAction.php',
    ];
}

function ts1Say(string $key, array $replace = []): string
{
    return __($key, $replace, 'dv');
}

/** @return array{request: SubstitutionRequest, absent: \App\Domains\People\Models\Teacher, cover: \App\Domains\People\Models\Teacher, office: User} */
function ts1Plant(): array
{
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $absent = makeTeacherRow();
    $cover = makeTeacherRow();
    $cover->user->assignRole('teacher');
    $office = User::factory()->create();
    $office->assignRole('admin');

    $request = SubstitutionRequest::query()->create([
        'date' => now()->addDay()->toDateString(),
        'absent_teacher_id' => $absent->id,
        'subject_id' => makeSubject()->id,
        'classroom_id' => $class->id,
        'period_id' => makePeriodRow()->id,
        'status' => 'open',
    ]);

    return compact('request', 'absent', 'cover', 'office');
}

it('prints no English of the pages\' own', function () {
    $found = [];
    foreach (ts1Views() as $view) {
        $path = resource_path('views/'.$view);
        // The app's name, as the layout's own title falls back to it.
        foreach ([...bladeBareEnglish($path), ...bladeEnglishLiterals($path, ['Akuru LMS'])] as $text) {
            $found[] = "{$view}: {$text}";
        }
        // `__('English sentence')` with no book is English in every language.
        preg_match_all("/__\\('([A-Z][^']*\\s[^']*)'\\)/", (string) file_get_contents($path), $sentences);
        foreach ($sentences[1] as $sentence) {
            $found[] = "{$view}: {$sentence}";
        }
        expect(unnamedFields((string) file_get_contents($path)))->toBe([], $view);
    }

    expect($found)->toBe([]);
});

it('leaves no English on the server, and keys every phrase in three languages', function () {
    foreach (ts1ServerFiles() as $file) {
        expect(refusalEnglishIn($file))->toBe([], $file);
    }

    $sources = implode("\n", array_map(fn ($f) => (string) file_get_contents(base_path($f)), [...ts1ServerFiles(), ...array_map(fn ($v) => 'resources/views/'.$v, ts1Views())]));
    preg_match_all("/__\\('((?:substitutions|academics)\\.[a-z_]+)/", $sources, $keys);
    $keys = array_values(array_unique($keys[1]));
    expect(count($keys))->toBeGreaterThan(60);

    foreach ($keys as $key) {
        [$book, $phrase] = explode('.', $key, 2);
        if (str_ends_with($phrase, '_')) {
            continue; // a key built from a status, read below
        }
        expect((require base_path("resources/lang/en/{$book}.php"))[$phrase] ?? null)->not->toBeNull("{$key} has no English")
            ->and((require base_path("resources/lang/dv/{$book}.php"))[$phrase] ?? '')->toMatch('/\p{Thaana}/u', "{$key} in Dhivehi")
            ->and((require base_path("resources/lang/ar/{$book}.php"))[$phrase] ?? '')->toMatch('/\p{Arabic}/u', "{$key} in Arabic");
    }
    foreach (['status_open', 'status_assigned', 'status_closed', 'status_cancelled', 'absence_status_pending', 'absence_status_approved', 'absence_status_rejected'] as $phrase) {
        expect((require base_path('resources/lang/dv/substitutions.php'))[$phrase] ?? '')->toMatch('/\p{Thaana}/u', $phrase);
    }
});

it('lets a teacher take an open lesson, and says so in Dhivehi on the list', function () {
    ['request' => $request, 'cover' => $cover] = ts1Plant();

    $this->actingAs($cover->user)->post(route('substitutions.requests.take', $request))
        ->assertRedirect(route('substitutions.requests.index'))
        ->assertSessionHas('success', ts1Say('substitutions.flash_taken'));

    expect($request->fresh()->status)->toBe('assigned')
        ->and($request->fresh()->assignment->substitute_teacher_id)->toBe($cover->id);

    $page = $this->actingAs($cover->user)->get(route('substitutions.requests.index'))->assertOk()->getContent();
    expect($page)->toContain(e(ts1Say('substitutions.status_assigned')));

    // Taken already: refused in Dhivehi, and said on the list.
    $this->actingAs($cover->user)->post(route('substitutions.requests.take', $request))
        ->assertSessionHas('error', ts1Say('substitutions.error_cannot_take'));
    $page = $this->actingAs($cover->user)->get(route('substitutions.requests.index'))->getContent();
    expect(substr_count($page, e(ts1Say('substitutions.error_cannot_take'))))->toBe(1);
});

it('lets the office change an open request and assign a substitute from its page', function () {
    ['request' => $request, 'cover' => $cover, 'office' => $office] = ts1Plant();
    $later = now()->addDays(3)->toDateString();

    $this->actingAs($office)->put(route('substitutions.requests.update', $request), [
        'date' => $later, 'absent_teacher_id' => $request->absent_teacher_id, 'subject_id' => $request->subject_id,
        'classroom_id' => $request->classroom_id, 'period_id' => $request->period_id, 'notes' => 'ޕޭޖު 12',
    ])->assertRedirect(route('substitutions.requests.show', $request))
        ->assertSessionHas('success', ts1Say('substitutions.flash_updated'));
    expect($request->fresh()->date->toDateString())->toBe($later);

    $page = $this->actingAs($office)->get(route('substitutions.requests.show', $request))->assertOk()->getContent();
    expect($page)->toContain('data-testid="assign-form"')
        ->toContain(e(ts1Say('substitutions.flash_updated')))
        ->not->toContain('value="'.$request->absent_teacher_id.'">');

    $this->actingAs($office)->post(route('substitutions.requests.assign', $request), ['substitute_teacher_id' => $cover->id])
        ->assertRedirect(route('substitutions.requests.show', $request))
        ->assertSessionHas('success', ts1Say('substitutions.flash_assigned'));
    expect($request->fresh()->status)->toBe('assigned');

    // No longer open: its edit page is refused in Dhivehi.
    $this->actingAs($office)->get(route('substitutions.requests.edit', $request))->assertForbidden()
        ->assertSee(ts1Say('substitutions.error_not_open_edit'));
});

it('offers New request and the absences to everyone the pages let in', function () {
    ['cover' => $cover, 'office' => $office] = ts1Plant();

    foreach ([$cover->user, $office] as $user) {
        $page = $this->actingAs($user)->get(route('substitutions.requests.index'))->assertOk()->getContent();
        expect($page)->toContain('href="'.route('substitutions.requests.create').'"')
            ->toContain('href="'.route('absences.index').'"')
            ->toContain('dir="rtl"');
    }

    $absences = $this->actingAs($office)->get(route('absences.index'))->assertOk()->getContent();
    expect($absences)->toContain(e(ts1Say('substitutions.absences_title')))
        ->toContain('href="'.route('absences.create').'"');
});

it('records a teacher\'s absence and says it in Dhivehi', function () {
    ['absent' => $absent, 'office' => $office] = ts1Plant();

    $this->actingAs($office)->post(route('absences.store'), [
        'teacher_id' => $absent->id, 'from_date' => '2026-11-02', 'to_date' => '2026-11-03', 'reason' => 'ބަލި',
    ])->assertRedirect(route('absences.index'))->assertSessionHas('success', ts1Say('substitutions.flash_absence_recorded'));

    $page = $this->actingAs($office)->get(route('absences.index'))->getContent();
    expect(substr_count($page, e(ts1Say('substitutions.flash_absence_recorded'))))->toBe(1)
        ->and($page)->toContain(e(ts1Say('substitutions.absence_status_pending')))
        ->toContain('ނޮވެންބަރު');
    expect(TeacherAbsence::query()->count())->toBe(1);
});

it('names a request\'s date in Arabic on its page', function () {
    app()->setLocale('ar');
    ['request' => $request, 'office' => $office] = ts1Plant();

    $page = $this->actingAs($office)->get(route('substitutions.requests.show', $request))->assertOk()->getContent();
    expect($page)->toContain(e(__('substitutions.show_title', [], 'ar')))
        ->toContain(e(__('substitutions.status_open', [], 'ar')))
        ->toContain($request->date->locale('ar')->translatedFormat('j M Y'));
});
