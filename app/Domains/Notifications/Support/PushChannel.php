<?php

namespace App\Domains\Notifications\Support;

/**
 * Whether push can deliver anything on this host (config/push.php).
 *
 * `fcm` needs both the project id and a readable key file; without them the
 * channel is off rather than half on, the way `LiveSms` fails closed.
 */
final class PushChannel
{
    public static function driver(): string
    {
        $driver = strtolower(trim((string) config('push.driver', 'null')));

        if ($driver === 'fcm' && ! self::fcmConfigured()) {
            return 'null';
        }

        return in_array($driver, ['log', 'fcm'], true) ? $driver : 'null';
    }

    public static function enabled(): bool
    {
        return self::driver() !== 'null';
    }

    public static function fcmConfigured(): bool
    {
        $project = trim((string) config('push.fcm.project_id', ''));
        $path = trim((string) config('push.fcm.credentials', ''));

        return $project !== '' && $path !== '' && is_readable($path);
    }
}
