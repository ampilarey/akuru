<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * PW2 (STATUS §5va): which version is running, and when it arrived — so
 * the owner can see that a pull took. The commit is read from `.git`'s own
 * files (no shell), the time is when that commit was put in place on this
 * server. Without a `.git` (a zip deploy) the front end's build stands in.
 */
final class AppVersion
{
    /** @var array{id: string, at: ?Carbon}|null */
    private static ?array $resolved = null;

    /** @return array{id: string, at: ?Carbon} */
    public static function current(?string $root = null): array
    {
        if ($root === null && self::$resolved !== null) {
            return self::$resolved;
        }
        $base = $root ?? base_path();
        [$commit, $file] = self::commit($base.'/.git');
        if ($commit === null) {
            $file = $base.'/public/build/manifest.json';
            $commit = is_file($file) ? (string) hash_file('sha256', $file) : '';
        }
        $version = [
            'id' => $commit === '' ? '' : substr($commit, 0, 7),
            'at' => $file !== null && is_file($file) ? Carbon::createFromTimestamp((int) filemtime($file), config('app.timezone')) : null,
        ];
        if ($root === null) {
            self::$resolved = $version;
        }

        return $version;
    }

    /** One line for a page, in the page's language; empty when nothing is known. */
    public static function label(): string
    {
        $version = self::current();
        if ($version['id'] === '') {
            return '';
        }

        // Left-to-right isolates: in Dhivehi and Arabic the day would
        // otherwise be carried to the far side of "Nov 2026, 14:38".
        $ltr = fn (string $text): string => "\u{2066}{$text}\u{2069}";

        return $version['at'] === null
            ? (string) __('common.app_version_bare', ['id' => $ltr($version['id'])])
            : (string) __('common.app_version', ['id' => $ltr($version['id']), 'at' => $ltr($version['at']->format('j M Y, H:i'))]);
    }

    /**
     * The checked-out commit and the file whose time says when it landed.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private static function commit(string $git): array
    {
        $head = is_file($git.'/HEAD') ? trim((string) file_get_contents($git.'/HEAD')) : '';
        if ($head === '') {
            return [null, null];
        }
        if (! str_starts_with($head, 'ref: ')) {
            return [self::sha($head), $git.'/HEAD'];
        }
        $ref = substr($head, 5);
        if (preg_match('#^refs/[A-Za-z0-9._/-]+$#', $ref) !== 1 || str_contains($ref, '..')) {
            return [null, null];
        }
        if (is_file($git.'/'.$ref)) {
            return [self::sha(trim((string) file_get_contents($git.'/'.$ref))), $git.'/'.$ref];
        }
        // A ref git has packed away.
        if (is_file($git.'/packed-refs')) {
            foreach (file($git.'/packed-refs', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                if (str_ends_with($line, ' '.$ref)) {
                    return [self::sha(substr($line, 0, 40)), $git.'/packed-refs'];
                }
            }
        }

        return [null, null];
    }

    private static function sha(string $value): ?string
    {
        return preg_match('/^[0-9a-f]{40}$/', $value) === 1 ? $value : null;
    }
}
