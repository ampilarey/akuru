<?php

/**
 * MF3 (STATUS §5sx): the substitution pages' buttons were `bg-brandBlue-500`
 * with white text, and `brandBlue` was never a colour in tailwind.config.js —
 * so Tailwind generated nothing and *New request* was white on white, nearly
 * invisible on a phone. A class naming a brand colour that does not exist
 * fails silently in the browser; it fails here instead.
 *
 * Filesystem only.
 */
it('uses only brand colours the Tailwind config defines', function () {
    $config = (string) file_get_contents(base_path('tailwind.config.js'));
    preg_match_all('/^\s+(brand[A-Z][A-Za-z]+)\s*:/m', $config, $defined);
    $defined = array_unique($defined[1]);
    expect($defined)->toContain('brandMaroon');

    $unknown = [];
    foreach (['resources/views', 'resources/js'] as $root) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($root), FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (! $file->isFile() || ! preg_match('/\.(blade\.php|jsx|js)$/', $file->getFilename())) {
                continue;
            }
            preg_match_all('/\b(brand[A-Z][A-Za-z]+)-\d{2,3}\b/', (string) file_get_contents($file->getPathname()), $used);
            foreach (array_diff(array_unique($used[1]), $defined) as $name) {
                $unknown[] = str_replace(base_path().'/', '', $file->getPathname()).': '.$name;
            }
        }
    }

    expect($unknown)->toBeEmpty("Brand colours the Tailwind config does not define (they render as nothing):\n  ".implode("\n  ", $unknown));
});
