<?php

namespace Tests\Architecture\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

final class ViolationScanner
{
    /** @var list<string> */
    private const DOMAINS = [
        'Identity', 'People', 'Academics', 'ExamsGrades', 'Hifz', 'Admissions',
        'Finance', 'HR', 'Commerce', 'Library', 'Courses', 'Offerings', 'Progress',
        'Pronunciation', 'Media', 'Notifications', 'Portal', 'Website', 'Settings', 'PrayerTimes',
    ];

    /** @var list<string> */
    private const ALLOWED_CROSS_LAYERS = ['Contracts', 'DTOs', 'Events', 'Actions'];

    /**
     * @return list<string> Relative paths of files importing another domain's Models.
     */
    public static function crossDomainModelViolators(): array
    {
        $violators = [];

        foreach (self::domainPhpFiles() as $file) {
            $home = self::homeDomain($file);
            if ($home === null) {
                continue;
            }

            $contents = file_get_contents($file);
            $relative = self::relativePath($file);

            if (preg_match_all('/use App\\\\Domains\\\\([^\\\\]+)\\\\([^\\\\]+)\\\\([^;]+);/', $contents, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $other = $match[1] === 'Academics' && $match[2] === 'Legacy' ? 'Academics' : $match[1];
                    if ($other !== $home && $match[2] === 'Models') {
                        $violators[$relative] = true;
                    }
                }
            }

            foreach (self::DOMAINS as $other) {
                if ($other === $home) {
                    continue;
                }
                if (preg_match('/App\\\\Domains\\\\'.$other.'\\\\Models\\\\/', $contents)) {
                    $violators[$relative] = true;
                }
            }
        }

        $paths = array_keys($violators);
        sort($paths);

        return $paths;
    }

    /**
     * @return list<string> "relative/path.php -> Fully\Qualified\Class"
     */
    public static function crossDomainNonContractViolators(): array
    {
        $violators = [];

        foreach (self::domainPhpFiles() as $file) {
            $home = self::homeDomain($file);
            if ($home === null) {
                continue;
            }

            $contents = file_get_contents($file);
            $relative = self::relativePath($file);

            if (preg_match_all('/use App\\\\Domains\\\\([^\\\\]+)\\\\([^\\\\]+)\\\\([^;]+);/', $contents, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $other = $match[1] === 'Academics' && $match[2] === 'Legacy' ? 'Academics' : $match[1];
                    if ($other === $home) {
                        continue;
                    }
                    if (! in_array($match[2], self::ALLOWED_CROSS_LAYERS, true)) {
                        $fqcn = 'App\\Domains\\'.$match[1].'\\'.$match[2].'\\'.$match[3];
                        $violators[$relative.' -> '.$fqcn] = true;
                    }
                }
            }
        }

        $entries = array_keys($violators);
        sort($entries);

        return $entries;
    }

    /**
     * @return list<string>
     */
    public static function controllersUsingDbFacade(): array
    {
        $violators = [];

        foreach (self::controllerFiles() as $file) {
            if (str_contains(file_get_contents($file), 'DB::')) {
                $violators[] = self::relativePath($file);
            }
        }

        sort($violators);

        return $violators;
    }

    /**
     * @return list<string>
     */
    public static function hifzReferencedOutsideHifz(): array
    {
        $violators = [];

        foreach (self::domainPhpFiles() as $file) {
            $home = self::homeDomain($file);
            if ($home === null || $home === 'Hifz') {
                continue;
            }

            if (preg_match('/App\\\\Domains\\\\Hifz\\\\/', file_get_contents($file))) {
                $violators[] = self::relativePath($file);
            }
        }

        sort($violators);

        return $violators;
    }

    /**
     * @param  list<string>  $current
     * @param  list<string>  $baseline
     * @return array{added: list<string>, removed: list<string>}
     */
    public static function diffBaseline(array $current, array $baseline): array
    {
        return [
            'added' => array_values(array_diff($current, $baseline)),
            'removed' => array_values(array_diff($baseline, $current)),
        ];
    }

    /**
     * @return list<string>
     */
    private static function domainPhpFiles(): array
    {
        $paths = array_merge(
            self::phpFilesUnder(base_path('app/Domains')),
            self::phpFilesUnder(base_path('app/Support')),
        );
        sort($paths);

        return $paths;
    }

    /**
     * @return list<string>
     */
    private static function controllerFiles(): array
    {
        $paths = self::phpFilesUnder(base_path('app/Domains'));
        $paths = array_values(array_filter($paths, fn (string $path) => str_contains($path, '/Http/Controllers/')));

        return $paths;
    }

    /**
     * @return list<string>
     */
    private static function phpFilesUnder(string $root): array
    {
        if (! is_dir($root)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        $regex = new RegexIterator($iterator, '/^.+\.php$/i', RegexIterator::GET_MATCH);
        $paths = [];

        /** @var array{0: string} $match */
        foreach ($regex as $match) {
            $paths[] = $match[0];
        }

        return $paths;
    }

    private static function relativePath(string $absolute): string
    {
        return str_replace('\\', '/', str_replace(base_path().'/', '', $absolute));
    }

    private static function homeDomain(string $file): ?string
    {
        $relative = self::relativePath($file);
        if (! str_starts_with($relative, 'app/Domains/')) {
            return str_starts_with($relative, 'app/Support/') ? 'Support' : null;
        }

        $parts = explode('/', $relative);
        $domain = $parts[2] ?? null;
        if ($domain === 'Academics' && ($parts[3] ?? null) === 'Legacy') {
            return 'Academics';
        }

        return $domain;
    }
}
