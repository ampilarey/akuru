<?php

use App\Domains\Courses\Enums\ActivitySubmissionKind;

/**
 * SPEC §36 asks the teacher to "open student submissions", "play audio/voice
 * submissions" and "view uploaded files".
 *
 * The third in this family, after `AnswerControlsExistTest` and
 * `QuestionMediaRendersTest`, and the same defect each time: a value the
 * database accepts, that every reader ignores. `submission_kind` was validated
 * and stored for a teacher-marked activity while the player rendered a
 * `<textarea>` whatever it said, and the reviewer's three §36 lines were all
 * answered by `JSON.stringify(row.answers)`.
 *
 * Nothing in the suite could have caught that: a page which silently shows a
 * text box where a recording was asked for renders perfectly and returns 200.
 *
 * Filesystem only: no database, no HTTP, no fixture.
 */
it('gives every uploadable submission kind a control the student can use', function () {
    $source = (string) file_get_contents(base_path('resources/js/Pages/Courses/Learn/Activity.jsx'));

    $uploadable = array_values(array_filter(
        ActivitySubmissionKind::cases(),
        static fn (ActivitySubmissionKind $kind): bool => $kind->acceptsUploads(),
    ));

    expect($uploadable)->not->toBeEmpty(
        'Every submission kind now refuses uploads, so §36 has nothing to open. '
        .'Either restore a kind that accepts files or delete this guard with the feature.'
    );

    expect(str_contains($source, 'type="file"'))->toBeTrue(
        'The activity player has no file input, so a `file` or `audio` activity is a '
        .'text box wearing a different label — which is exactly the state §36 was in '
        .'before this guard existed.'
    );

    // The kind has to reach the page, or the player cannot tell the three apart.
    expect($source)->toContain('activity.submission');

    // A refusal the student cannot see is indistinguishable from a broken
    // upload. The browser walk for this slice hit exactly that: a rejected MIME
    // left the page reading "Nothing uploaded yet" with no reason given.
    expect($source)->toContain('errors?.file');
});

it('lets the reviewer play and open what the student handed in', function () {
    $source = (string) file_get_contents(base_path('resources/js/Pages/Courses/Catalog/Reviews.jsx'));

    // §36 "Play audio/voice submissions".
    expect($source)->toContain('<audio');
    // §36 "View uploaded files" — served through the catalog media route, which
    // is the only path a reviewer is authorised on.
    expect($source)->toContain('/catalog/media/');
    // §36 "Open student submissions". A JSON dump is not an opened submission;
    // it stays as the fallback beneath, never as the whole answer.
    expect($source)->toContain('answers.attachments');
});
