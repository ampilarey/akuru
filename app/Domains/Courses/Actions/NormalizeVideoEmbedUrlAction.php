<?php

namespace App\Domains\Courses\Actions;

use Illuminate\Validation\ValidationException;

/**
 * The one allowlist for an externally referenced video.
 *
 * This was a private method inside `ValidateContentBlockDataAction`, where only
 * §15's video content block could reach it. SPEC §20 gives a question the same
 * four attachment kinds — "Audio, Image, PDF, Video reference" — and the word
 * there is *reference*, not upload.
 *
 * A second copy of the allowlist would be a second answer to "which hosts may
 * we frame", and the two would drift the first time one of them was widened.
 * CLAUDE.md rule 11 asks for one source of truth; this is it, and
 * `ValidateContentBlockDataAction` now delegates here rather than keeping its
 * own.
 *
 * `$errorKey` exists because the two callers validate different form fields:
 * a block reports on `data`, a question attachment on `video_url`, and a
 * message filed under the wrong key renders nowhere.
 */
class NormalizeVideoEmbedUrlAction
{
    /**
     * @var list<string>
     */
    public const ALLOWED_HOSTS = [
        'youtube.com',
        'www.youtube.com',
        'm.youtube.com',
        'youtu.be',
        'vimeo.com',
        'www.vimeo.com',
        'player.vimeo.com',
    ];

    public function execute(string $url, string $errorKey = 'data'): string
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https') {
            throw ValidationException::withMessages([
                $errorKey => 'Video embeds must use https.',
            ]);
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! in_array($host, self::ALLOWED_HOSTS, true)) {
            throw ValidationException::withMessages([
                $errorKey => 'Only YouTube or Vimeo embeds are allowed.',
            ]);
        }

        if ($host === 'youtu.be') {
            $id = trim((string) ($parts['path'] ?? ''), '/');

            return $id !== '' ? 'https://www.youtube.com/embed/'.$id : $url;
        }

        if (str_contains($host, 'youtube.com')) {
            parse_str($parts['query'] ?? '', $query);
            if (! empty($query['v'])) {
                return 'https://www.youtube.com/embed/'.$query['v'];
            }
            if (str_starts_with((string) ($parts['path'] ?? ''), '/embed/')) {
                return 'https://www.youtube.com'.($parts['path'] ?? '');
            }
        }

        if (in_array($host, ['vimeo.com', 'www.vimeo.com'], true)) {
            $id = trim((string) ($parts['path'] ?? ''), '/');
            if (ctype_digit($id)) {
                return 'https://player.vimeo.com/video/'.$id;
            }
        }

        return $url;
    }
}
