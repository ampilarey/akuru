<?php

/**
 * Nothing writes `$request->all()` to the database.
 *
 * ## What the gap actually is
 *
 * `$request->all()` is every key the caller sent, and a model's `$fillable`
 * decides which of them land. A controller that validates carefully and then
 * writes `all()` has **two lists that are meant to agree and nothing making
 * them**: the validation says what the screen intends, `$fillable` says what
 * the table accepts, and the difference between them is writable by anybody
 * who can reach the route.
 *
 * `QuranProgressController` was the live example, and it is a fair illustration
 * of how small this usually is rather than how dramatic: every field the form
 * uses *was* validated, so what slipped through was the three `$fillable`
 * columns the validation never mentions — `date_completed`,
 * `last_revision_date` and `revision_count` — writable by staff who could
 * already write the row. Worth closing because the next model to gain a
 * column gains it here too, silently, and nothing would say so.
 *
 * ## Why `validated()` is fine and `all()` is not
 *
 * `$request->validated()` returns exactly the keys the rules named, so the two
 * lists cannot drift. `ProfileController` does this and is not flagged.
 *
 * ## The trap this caught
 *
 * Rewriting `create($request->all())` to `create($data)` is **not** always
 * behaviour-preserving. `QuranProgressController::updateProgress` merged the
 * student and teacher ids into the request *after* validating, so they were in
 * `all()` and absent from the validated array — the naive rewrite would have
 * written two nulls. Anything merged after validation has to be put on the
 * validated array explicitly.
 */
it('never writes whatever the caller happened to send', function () {
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('app'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $source = stripPhpComments(file_get_contents($file->getPathname()));
        $path = str_replace(base_path().'/', '', $file->getPathname());

        // `->create($request->all())`, `->update($request->all())`,
        // `->fill($request->all())`, `->forceFill($request->all())`.
        $pattern = '/->(create|update|fill|forceFill|insert|updateOrCreate|firstOrCreate)\s*\(\s*\$request->all\(\)/';

        if (! preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        foreach ($matches[0] as $match) {
            $offenders[] = $path.':'.(substr_count(substr($source, 0, $match[1]), "\n") + 1);
        }
    }

    sort($offenders);

    expect($offenders)->toBeEmpty(
        "These write whatever the caller sent:\n  "
        .implode("\n  ", $offenders)
        ."\n\nWrite the validated array instead — `\$data = \$request->validate([...])` "
        .'then `create($data)` — so what the screen intends and what the table accepts '
        ."cannot drift apart.\n\nCheck for a `\$request->merge()` after the validation "
        .'first: those keys are in all() and not in the validated array, and moving them '
        .'is part of the change, not an afterthought.'
    );
});
