<?php

/**
 * SPEC §7 "Supported Languages and Direction":
 *
 *   > **The platform UI language and course content language are separate
 *   > concepts.**
 *
 * `Courses/Player/Show` is rendered by **two** controllers — the author
 * preview (`LessonPlayerController`) and the student player
 * (`LearnLessonController`) — and both had the same defect: neither sent the
 * course's language, so §15.3's default `language: auto` resolved to no `lang`
 * attribute and every block inherited the page's, which is the UI's.
 *
 * Two renderers of one page is exactly how a fix ships half-applied. Fixing
 * only the preview would have left §7's actual example — a student reading the
 * UI in Dhivehi opening an Arabic course — still wrong, and a test over one
 * controller would have passed.
 *
 * Filesystem only: no database, no HTTP, no fixture.
 */
it('sends the course language from every controller that renders the player', function () {
    $page = 'Courses/Player/Show';
    $missing = [];

    foreach (glob(base_path('app/Domains/*/Http/Controllers/*.php')) as $file) {
        $source = (string) file_get_contents($file);
        if (! str_contains($source, $page)) {
            continue;
        }
        if (! str_contains($source, 'courseLanguage')) {
            $missing[] = str_replace(base_path().'/', '', $file);
        }
    }

    expect($missing)->toBeEmpty(
        "These controllers render the lesson player without sending the course language:\n  "
        .implode("\n  ", $missing)
        ."\n\nSPEC §7: the platform UI language and the course content language are "
        ."separate concepts. Without `courseLanguage`, a block left at §15.3's default "
        ."`auto` inherits the page's `lang` — the UI's — so an Arabic course read in a "
        ."Dhivehi UI is marked up as Dhivehi.\n\nPass "
        .'`app(ResolveCourseContentLanguageAction::class)->forLesson($lesson)`.'
    );

    // Guards the guard: if the page is ever renamed, the loop above matches
    // nothing and passes vacuously.
    expect($missing)->toBeArray();
    expect(glob(base_path('resources/js/Pages/'.$page.'.jsx')))->not->toBeEmpty(
        'The lesson player page has moved; this guard now checks nothing.'
    );
});
