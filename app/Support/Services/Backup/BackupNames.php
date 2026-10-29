<?php

namespace App\Support\Services\Backup;

use Carbon\CarbonImmutable;

/**
 * BK1: a backup's name says when it was made (`akuru-20261029-030000.akb`),
 * and retention reads nothing else: the newest daily copies, the newest of
 * each recent week, the newest of each recent month.
 */
final class BackupNames
{
    public const EXTENSION = '.akb';

    public static function make(CarbonImmutable $at): string
    {
        return 'akuru-'.$at->format('Ymd-His').self::EXTENSION;
    }

    public static function madeAt(string $name): ?CarbonImmutable
    {
        if (preg_match('/^akuru-(\d{8}-\d{6})\.akb$/', basename($name), $m) !== 1) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Ymd-His', $m[1], 'Indian/Maldives') ?: null;
    }

    /**
     * Which names to keep: the newest `$daily`, then the newest of each of the
     * `$weekly` most recent weeks and `$monthly` most recent months. A name
     * that is not a backup's is never touched.
     *
     * @param  list<string>  $names
     * @return list<string> the names to delete
     */
    public static function toDelete(array $names, int $daily, int $weekly, int $monthly): array
    {
        $dated = [];
        foreach ($names as $name) {
            if (($at = self::madeAt($name)) !== null) {
                $dated[$name] = $at;
            }
        }
        arsort($dated);
        $keep = array_slice(array_keys($dated), 0, max(1, $daily));
        foreach ([[$weekly, 'o-W'], [$monthly, 'Y-m']] as [$count, $format]) {
            $seen = [];
            foreach ($dated as $name => $at) {
                $period = $at->format($format);
                if (! isset($seen[$period]) && count($seen) < $count) {
                    $seen[$period] = $name;
                }
            }
            $keep = array_merge($keep, array_values($seen));
        }

        return array_values(array_diff(array_keys($dated), $keep));
    }
}
