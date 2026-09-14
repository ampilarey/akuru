<?php

/**
 * A form that refuses has to say so.
 *
 * Found by driving the real forms rather than reading them
 * (`scripts/smoke/create-sweep.mjs`): typing a non-numeric value into the CPD
 * screen's **Hours** box and pressing Save produced **nothing at all**. The row
 * was not created, what had been typed stayed in the boxes, and the page said
 * not one word. From the user's side that is indistinguishable from a save that
 * worked and a table that has not refreshed.
 *
 * `Cpd.jsx` never mentioned `errors`. Neither did 27 other pages that submit a
 * form — and the same shape had already been fixed twice this week from the
 * other direction: `AdminEnrollmentController::suspend()` and `reinstate()` had
 * been returning `back()->with('error', ...)` to a Blade screen that rendered
 * only the success banner, so a refused suspension had been silent since it
 * shipped.
 *
 * This is the pilot-rehearsal lesson in its purest form. The Pest tests for
 * these screens assert the 422 correctly. A 422 nobody can see is not a
 * validated form; it is a button that does nothing.
 *
 * ## What counts
 *
 * A page **submits** if it calls `form.post(` / `.put(` / `.patch(` /
 * `.delete(`. It **shows errors** if the word `errors` appears in it at all —
 * deliberately the loosest possible test, because the house styles differ
 * (`<Field error={form.errors.x}>` in the Academics screens, an inline
 * `{form.errors.name && <span>}` elsewhere) and this gate is about whether the
 * page can speak, not about how.
 *
 * Comments are stripped first. `tests/Support/SourceReadingHelpers.php` records
 * why: a check that cannot tell documentation from instruction punishes writing
 * the explanation down, and this repo has now learned that four times.
 *
 * 92 pages submit a form. The baseline lists the ones that cannot report a
 * refusal, and it may only shrink.
 */
it('makes a page that submits a form able to show what came back', function () {
    $baseline = require __DIR__.'/Baselines/pages_that_swallow_errors.php';

    $pages = inertiaPagesThatSubmit();

    $silent = array_keys(array_filter($pages, fn (bool $showsErrors) => ! $showsErrors));
    sort($silent);

    $new = array_values(array_diff($silent, $baseline));
    sort($new);

    expect($new)->toBeEmpty(
        "These pages submit a form and never mention `errors`, so a refusal is invisible:\n  "
        .implode("\n  ", $new)
        ."\n\nA 422 the user cannot see is not a validated form — it is a button that does "
        ."nothing. Render the errors the way the page's neighbours do: `<Field "
        ."error={form.errors.x}>`, or an inline `{form.errors.x && <span ...>}`.\n\n"
        .'If a page genuinely cannot fail, add it to '
        .'tests/Architecture/Baselines/pages_that_swallow_errors.php with the reason.'
    );

    // A page that has learned to speak leaves the list, so the count is real.
    $fixed = [];
    foreach ($baseline as $page) {
        if (! array_key_exists($page, $pages)) {
            $fixed[] = $page.' — no longer submits a form (or no longer exists)';

            continue;
        }

        if ($pages[$page]) {
            $fixed[] = $page.' — now shows its errors';
        }
    }
    sort($fixed);

    expect($fixed)->toBeEmpty(
        "These baseline entries are stale — delete them:\n  "
        .implode("\n  ", $fixed)
        ."\n\nThat is the direction the list is meant to move."
    );
});

/**
 * …and the thing it shows has to exist.
 *
 * Clearing the baseline was largely mechanical, and the mechanism put
 * `<FormErrors errors={form.errors} />` into three **filter** forms — plain GET
 * forms with no `useForm` anywhere in the component. `ReferenceError: form is
 * not defined`, which in React blanks the whole page.
 *
 * Nothing caught it. The build does no scope analysis, the PHP suite never
 * loads a page, and the assertion above is satisfied by the word `errors`
 * appearing — a page can be broken and still pass a gate that only reads for a
 * string. It was found by loading the screens in a browser.
 *
 * So this reads the reference the other way: every `<FormErrors errors={X}>`
 * must name a `useForm` declared in the same component. Components are
 * delimited by top-level `function` declarations, which is how every page here
 * is written.
 */
it('points FormErrors at a form that exists in the same component', function () {
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('js/Pages'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'jsx') {
            continue;
        }

        $lines = explode("\n", stripJsComments(file_get_contents($file->getPathname())));
        $path = str_replace(base_path().'/', '', $file->getPathname());

        $bounds = [];
        foreach ($lines as $index => $line) {
            if (preg_match('/^(export default )?function \w+/', $line)) {
                $bounds[] = $index;
            }
        }
        $bounds[] = count($lines);

        for ($i = 0; $i < count($bounds) - 1; $i++) {
            $block = implode("\n", array_slice($lines, $bounds[$i], $bounds[$i + 1] - $bounds[$i]));

            preg_match_all('/const\s+(\w+)\s*=\s*useForm/', $block, $declared);
            preg_match_all('/<FormErrors errors=\{(\w+)\.errors\}/', $block, $used);

            foreach (array_unique($used[1]) as $variable) {
                if (! in_array($variable, $declared[1], true)) {
                    $offenders[] = $path.' — `'.$variable.'` is not a useForm in this component';
                }
            }
        }
    }

    sort($offenders);

    expect($offenders)->toBeEmpty(
        "These <FormErrors> point at something that does not exist where they stand:\n  "
        .implode("\n  ", $offenders)
        ."\n\nThat is a ReferenceError, which blanks the page. A filter form that "
        .'submits with `router.get` has no errors to show — delete the element rather '
        .'than pointing it at a form from another component.'
    );
});

/**
 * Every Inertia page that posts a form, and whether it mentions `errors`.
 *
 * @return array<string, bool>
 */
function inertiaPagesThatSubmit(): array
{
    $pages = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('js/Pages'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'jsx') {
            continue;
        }

        $source = stripJsComments(file_get_contents($file->getPathname()));

        if (! preg_match('/\b\w*[Ff]orm\.(post|put|patch|delete)\s*\(/', $source)) {
            continue;
        }

        $path = str_replace(base_path().'/', '', $file->getPathname());
        $pages[$path] = str_contains($source, 'errors');
    }

    ksort($pages);

    return $pages;
}
