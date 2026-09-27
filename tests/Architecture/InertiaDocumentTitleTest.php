<?php

/**
 * STATUS §5jc. Every Inertia screen renders inside `AppShell` and hands it a
 * `title`, which the shell showed as the heading and nowhere else — so the
 * browser tab of every Inertia page read the bare app name, and a person
 * with several tabs open could not tell them apart. The shell now writes the
 * title into the document through Inertia's `<Head>`, and `app.jsx` appends
 * the app name the Blade root put in `<title inertia>`.
 *
 * Filesystem only: the title is client-rendered, so a PHP request cannot see
 * it; the walk does. This pins that the two lines stay.
 */
it('gives every Inertia page a document title through the shell', function () {
    $shell = (string) file_get_contents(resource_path('js/Layouts/AppShell.jsx'));
    $app = (string) file_get_contents(resource_path('js/app.jsx'));
    $root = (string) file_get_contents(resource_path('views/app.blade.php'));

    expect($shell)->toContain('<Head title={title}')
        ->and(preg_match('/import \{[^}]*\bHead\b[^}]*\} from \'@inertiajs\/react\'/', $shell))->toBe(1, 'AppShell imports Head from Inertia')
        ->and($app)->toContain('title: (title) =>')
        ->and($root)->toContain('<title inertia>');
});
