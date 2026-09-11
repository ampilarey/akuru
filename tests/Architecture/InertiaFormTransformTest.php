<?php

/**
 * No JSX chains a request method onto `form.transform(...)`.
 *
 * In `@inertiajs/react` v3, `transform()` stores the callback on a ref and
 * **returns undefined**:
 *
 *     const transformFunction = useCallback((callback) => {
 *         transformRef.current = callback;
 *     }, []);
 *
 * So `form.transform(fn).post(url)` throws
 * `TypeError: Cannot read properties of undefined (reading 'post')`, the submit
 * handler dies, and **the button silently does nothing**. No 5xx, no flash, no
 * console output a user would ever see — the page just sits there.
 *
 * Eight screens shipped that way: the Qur'an halaqa sheet's three-lane save,
 * a parent ticking homework done, student custom-field values, creating a
 * custom field, adding a content block, creating and updating a library item,
 * and creating an appraisal.
 *
 * **Nothing in the suite could have caught them.** The screen guards load pages
 * and assert no 5xx; a page whose button is dead still renders perfectly.
 * Feature tests POST to the route directly, so they exercise the controller and
 * never touch the JSX. The defect lives exactly in the gap between the two,
 * which is why it needs a guard of its own — and why "walked in a browser"
 * means *completing the task*, not loading the screen.
 *
 * The correct idiom is two statements, and the codebase already had it in
 * `Academics/Attendance/Daily.jsx` and `People/Sensitive/Index.jsx`:
 *
 *     form.transform((data) => ({ ...data, student_id: studentId }));
 *     form.post('/people/sensitive', { preserveScroll: true });
 */
it('never chains a request onto form.transform()', function () {
    $root = base_path('resources/js');
    $offenders = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

    foreach ($files as $file) {
        if (! $file->isFile() || ! in_array($file->getExtension(), ['jsx', 'js', 'tsx'], true)) {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());

        // `.transform(` … `)` followed by `.post(`/`.put(`/`.patch(`/`.delete(`.
        // The body may span lines, so match non-greedily up to the chained call.
        if (preg_match('/\.transform\((?:[^;]|\R)*?\)\s*\R?\s*\.(post|put|patch|delete)\(/', $source, $m) === 1) {
            $offenders[] = str_replace(base_path().'/', '', $file->getPathname()).' → .transform(…).'.$m[1].'()';
        }
    }

    sort($offenders);

    expect($offenders)->toBeEmpty(
        count($offenders)." file(s) chain a request onto form.transform(), which returns\n"
        ."undefined in @inertiajs/react v3. The handler throws and the button does\n"
        ."nothing at all — no error a user can see:\n  "
        .implode("\n  ", $offenders)
        ."\nSplit it into two statements:\n"
        ."    form.transform((data) => ({ ...data, extra }));\n"
        ."    form.post(url, options);\n"
    );
});
