<?php

use App\Domains\Identity\Models\User;
use App\Http\Middleware\EnsureQuranModuleEnabled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

/**
 * SPEC §52.27 "Feature Flag":
 *
 *   > The Qur'an/Hifz module should be feature-flagged.
 *   > `QURAN_HIFZ_MODULE_ENABLED=false`
 *   > **The main platform must work even if the Qur'an/Hifz module is
 *   > disabled.**
 *
 * repeated as §52.29's last acceptance criterion.
 *
 * **There was no flag.** `config/quran.php` held `halaqa_dual_write` and
 * `translation_source`; `QURAN_HIFZ_MODULE_ENABLED` appeared nowhere in the
 * codebase. So the claim could not be tested, because the module could not be
 * disabled.
 *
 * The striking part is that **§51's module *is* flagged** —
 * `AI_PRONUNCIATION_ENABLED` is threaded through the Pronunciation domain and
 * read by the practice screen. §52 asks for the same thing one section later
 * and got nothing.
 *
 * Most of §52 is in good order, and the audit checked it rather than assuming:
 * §52.2's **Critical Haraka Rule** is implemented exactly as written —
 * `PredictIsolatedSoundAction` keeps `is_letter_match` and `is_haraka_match`
 * apart and returns `wrong_haraka` as a verdict distinct from `wrong_letter`,
 * which is §52.2's worked example. `quran_mistake_marks`,
 * `quran_memorization_progress` and `quran_revision_schedules` all exist, the
 * student screen plays both the submission audio and the teacher's correction
 * audio, and `RecitationSubmissionStatus::NeedsRepeat` carries §52.29's
 * "students can see corrections and resubmit".
 */
uses(RefreshDatabase::class);

it('serves the module when the flag is on', function () {
    config(['quran.module_enabled' => true]);

    $this->actingAs(actingPeopleAdmin(['courses.manage']))
        ->withoutLocalizationMiddleware()
        ->get('/catalog/quran')
        ->assertOk();
});

it('hides the module when the flag is off', function () {
    config(['quran.module_enabled' => false]);

    // 404 rather than 403: a disabled module is not a permission problem, and
    // "forbidden" invites somebody to go looking for the permission that would
    // let them in.
    $this->actingAs(actingPeopleAdmin(['courses.manage']))
        ->withoutLocalizationMiddleware()
        ->get('/catalog/quran')
        ->assertNotFound();
});

it('leaves the rest of the platform working with the module off', function () {
    // This is §52.29's actual claim, and the only reason the flag is worth
    // having. Screens from four unrelated domains.
    config(['quran.module_enabled' => false]);

    $admin = actingPeopleAdmin(['courses.manage']);
    $admin->assignRole(\Spatie\Permission\Models\Role::findOrCreate('admin', 'web'));
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $admin = $admin->fresh();

    foreach (['/catalog/courses', '/catalog/reports', '/catalog/reviews', '/people/students'] as $uri) {
        $this->actingAs($admin)
            ->withoutLocalizationMiddleware()
            ->get($uri)
            ->assertOk();
    }
});

it('lets a student use the rest of the platform with the module off', function () {
    config(['quran.module_enabled' => false]);

    $user = User::factory()->create();
    makeStudent(['user_id' => $user->id, 'first_name' => 'Flag', 'last_name' => 'Pupil']);

    $this->actingAs($user)
        ->withoutLocalizationMiddleware()
        ->get('/learn')
        ->assertOk();
});

it('covers every route the module owns, by namespace rather than by a list', function () {
    // The module's 57 routes are declared inline throughout the routes file
    // rather than in one group, so a hand-kept list of route names would go
    // stale the moment somebody adds one — the exact defect this session has
    // fixed repeatedly. The middleware decides by controller namespace, and
    // this test is what stops a controller drifting outside it unnoticed.
    $uncovered = [];

    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();
        if (! preg_match('/quran|recitation|hifz/i', $uri)) {
            continue;
        }
        $action = $route->getActionName();
        if (! str_contains($action, '@')) {
            continue;
        }
        if (in_array($action, EnsureQuranModuleEnabled::EXAMINED_NOT_MODULE, true)) {
            continue;
        }
        if (! EnsureQuranModuleEnabled::coversAction($action)) {
            $uncovered[] = $uri.'  →  '.$action;
        }
    }

    sort($uncovered);

    expect($uncovered)->toBeEmpty(
        "These Qur'an/Hifz routes are not covered by the §52.27 feature flag:\n  "
        .implode("\n  ", $uncovered)
        ."\n\nDecide each one and write the decision down: move the controller under one of "
        .'EnsureQuranModuleEnabled::NAMESPACES, add it to EXTRA_CONTROLLERS or EXTRA_ACTIONS, '
        .'or add it to EXAMINED_NOT_MODULE with the reason it is not module surface.'
    );
});

it('takes only the Hifz tab of a student record, not the record', function () {
    // The tab now lives on `Hifz\QuranProgressController@student`, covered by
    // namespace; the record itself is the React `people.students.show` screen,
    // which the flag must leave alone.
    config(['quran.module_enabled' => false]);

    $admin = actingPeopleAdmin(['courses.manage']);
    $admin->assignRole(\Spatie\Permission\Models\Role::findOrCreate('admin', 'web'));
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $student = makeStudent(['first_name' => 'Tab', 'last_name' => 'Pupil']);

    $this->actingAs($admin->fresh())
        ->withoutLocalizationMiddleware()
        ->get('/students/'.$student->id.'/quran-progress')
        ->assertNotFound();

    $this->actingAs($admin->fresh())
        ->withoutLocalizationMiddleware()
        ->get('/people/students/'.$student->id)
        ->assertOk();
});

it('keeps ordinary Quran-subject courses listed with the module off', function () {
    // §52.27's "the main platform must work": the legacy e-learning page lists
    // *courses* whose subject happens to be Qur'an, and courses are the main
    // platform. Switching the recitation module off must not empty a
    // catalogue.
    expect(EnsureQuranModuleEnabled::coversAction(
        \App\Domains\Academics\Legacy\Http\Controllers\ELearningController::class.'@quranLessons'
    ))->toBeFalse();
});

it('does not switch off the shared Quran dataset readers', function () {
    // Rule 11: there is one Qur'an dataset, and screens outside this module
    // read it. The flag turns off the *module*, not the data — so a matcher
    // like "controller name contains Quran" would have been wrong, and the
    // explicit EXTRA_CONTROLLERS list exists to keep that distinction.
    expect(EnsureQuranModuleEnabled::coversAction(
        'App\\Domains\\Courses\\Http\\Controllers\\CatalogQuestionController@index'
    ))->toBeFalse();

    expect(EnsureQuranModuleEnabled::coversAction(
        'App\\Domains\\Courses\\Components\\Quran\\Http\\Controllers\\LearnQuranController@index'
    ))->toBeTrue();
});
