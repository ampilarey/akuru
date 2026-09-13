<?php

use App\Domains\Courses\Actions\ResolveQuestionMediaAction;

/**
 * Every attachment kind SPEC §20 gives a question is drawn by the assessment
 * player.
 *
 * The sibling of `AnswerControlsExistTest`, and the same family of defect:
 * §20's attachments were uploaded through the media system, frozen into §21's
 * snapshot, and rendered by **no React file at all**. `audio` and `image` are
 * two of §20's twelve question types; both showed the student the question text
 * and nothing else.
 *
 * As with the missing `arrange` control, nothing in the suite could have caught
 * it: the screen guards assert no 5xx, and a page that silently omits the one
 * file the question is about renders perfectly.
 *
 * Filesystem only: no database, no HTTP, no fixture.
 */
it('draws every §20 attachment kind in the assessment player', function () {
    $path = 'resources/js/Pages/Courses/Learn/Assessment.jsx';
    $source = (string) file_get_contents(base_path($path));

    $missing = [];

    foreach (ResolveQuestionMediaAction::KINDS as $kind) {
        if (! str_contains($source, "item.kind === '{$kind->value}'")) {
            $missing[] = $kind->value;
        }
    }

    expect($missing)->toBeEmpty(
        "A question carries these attachment kinds and the player draws none of them:\n  "
        .implode("\n  ", $missing)
        ."\nAdd a branch to QuestionMedia, or take the kind out of ResolveQuestionMediaAction::KINDS."
    );

    // A reference is not an upload and needs its own branch.
    expect($source)->toContain('item.embed_url');
});
