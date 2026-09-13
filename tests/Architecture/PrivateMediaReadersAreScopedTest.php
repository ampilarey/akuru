<?php

/**
 * Every path that can hand a caller the bytes of a private file.
 *
 * `ReadPrivateMediaAction` is `MediaFile::find($id)` with no scope — by design,
 * because scoping is the caller's job and only the caller knows what the file
 * is *for*. That makes its call sites the whole of the access-control surface
 * for private media, and the list is short enough to be read.
 *
 * It is pinned because of what an unscoped one costs. `ServeCatalogMediaAction`
 * granted any holder of `courses.manage` — which includes `course_creator` —
 * every private file in the application by id: children's Qur'an recitations,
 * children's pronunciation attempts, students' submissions and work
 * photographs, and the Library's paid PDF originals. A `courses.manage`-only
 * account fetched an unrelated audio file and got 200.
 *
 * **The structural lesson, not just the fix.** Six of these seven never had that
 * problem, and not because their permissions are stricter — because they
 * resolve the media id **from the record that owns it**. "Give me the photo of
 * found-item 12" cannot be pivoted into "give me file 12"; only "give me file
 * 12" can. `ServeCatalogMediaAction` is the one that legitimately takes an id
 * straight from the route, which is exactly why it needs an allow-list and why
 * it is the one that went wrong.
 *
 * So a new caller is not assumed to be a bug — it is assumed to need reading.
 * Add it here with the sentence that says how it scopes.
 *
 * Filesystem only: no database, no HTTP, no fixture.
 */
it('pins every caller that can read a private file', function () {
    $allowed = [
        // Resolves the media id from the found-item record.
        'app/Domains/Academics/Actions/ReadListedFoundItemPhotoAction.php',
        // Resolves from the student-work record, and filters to the students
        // the viewer is allowed to see.
        'app/Domains/Academics/Actions/ReadStudentWorkPhotoAction.php',
        // Resolves from the teaching material, and requires a register
        // permission plus a class relationship.
        'app/Domains/Academics/Actions/ServeMaterialFileAction.php',
        // The found-item controller, which reads through the action above.
        'app/Domains/Academics/Http/Controllers/FoundItemController.php',
        // **The one that takes an id straight from the route**, and therefore
        // the one carrying an explicit allow-list: catalog media, or a
        // submission attachment a reviewer is opening.
        'app/Domains/Courses/Actions/ServeCatalogMediaAction.php',
        // Resolves from the recitation submission. Its `courses.manage`/teacher
        // grant is deliberate and documented — whoever may mark a recitation
        // may hear it — and it reaches recitations only, never arbitrary files.
        'app/Domains/Courses/Components/Quran/Actions/ServeRecitationAudioAction.php',
        // Server-side only: reads the attempt's own audio to score it. The
        // bytes never leave the process.
        'app/Domains/Pronunciation/Actions/PredictIsolatedSoundAction.php',
    ];

    $callers = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('app'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $path = str_replace(base_path().'/', '', $file->getPathname());

        if (str_ends_with($path, 'Actions/ReadPrivateMediaAction.php')) {
            continue;
        }

        $source = stripPhpComments((string) file_get_contents($file->getPathname()));

        if (str_contains($source, 'ReadPrivateMediaAction')) {
            $callers[] = $path;
        }
    }

    sort($callers);

    expect($callers)->toBe($allowed,
        "Something new can read a private file:\n  "
        .implode("\n  ", array_diff($callers, $allowed))
        ."\n\nPrivate media is children's recitations and pronunciation attempts, students' "
        ."submissions and work photographs, class materials, and the Library's paid PDF "
        ."originals.\n\nPrefer resolving the media id from the record that owns it — \"the photo "
        .'of found-item 12\" cannot be pivoted into "file 12". If the id really does come '
        .'from the request, it needs an explicit allow-list, as ServeCatalogMediaAction has. '
        .'Then add the file here with the sentence that says how it scopes.'
    );
});
