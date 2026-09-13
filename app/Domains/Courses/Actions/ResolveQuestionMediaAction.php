<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\ContentBlockType;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §20 "Question Attachments":
 *
 *   > Questions may have: Audio · Image · PDF · Video reference
 *   > All attachments must go through the centralized media system.
 *
 * The upload half of that worked: the question bank has a file input, and
 * `SaveQuestionAction` funnels it through `StorePrivateMediaAction`, so the
 * bytes really do land in the central media system. §21's snapshot copied the
 * `attachments` array faithfully into every attempt.
 *
 * Nothing then rendered it. `attachments` appears in exactly three PHP files
 * and **zero** React ones, so the two question types §20 names for media —
 * `audio` and `image` — showed the student the question text and nothing else.
 * An audio question with no audio is not a hard question; it is an unanswerable
 * one, and it looked like an ordinary page.
 *
 * The serving half was closed too: `ServeCatalogMediaAction` let a student read
 * media only when it appeared in a *lesson* content block, so even a player
 * that rendered the tag would have drawn a 403.
 *
 * This Action is the single place that says what an attachment is and how it
 * should be shown. It answers in `ContentBlockType` terms deliberately — §30's
 * mime lists and size caps already live there, and inventing a second table of
 * "which mimes count as audio" is exactly the drift rule 11 forbids.
 */
class ResolveQuestionMediaAction
{
    /**
     * The four kinds §20 names, in the order it names them.
     *
     * @var list<ContentBlockType>
     */
    public const KINDS = [
        ContentBlockType::Audio,
        ContentBlockType::Image,
        ContentBlockType::Pdf,
        ContentBlockType::Video,
    ];

    /**
     * Which of §20's four kinds a sniffed mime belongs to, or null if §20 does
     * not give questions a place for it.
     */
    public function kindForMime(string $mime): ?ContentBlockType
    {
        foreach (self::KINDS as $kind) {
            if (in_array($mime, $kind->allowedMimes(), true)) {
                return $kind;
            }
        }

        return null;
    }

    /**
     * The same question as `kindForMime`, asked where a refusal is the point.
     */
    public function assertSupportedMime(string $mime, string $errorKey = 'file'): ContentBlockType
    {
        $kind = $this->kindForMime($mime);
        if ($kind === null) {
            throw ValidationException::withMessages([
                $errorKey => 'A question attachment must be audio, an image, a PDF, or a video. '.$mime.' is none of those.',
            ]);
        }

        return $kind;
    }

    /**
     * Renderable descriptors for one question's attachments.
     *
     * The **URL is not built here**, and that is deliberate: the same media id
     * is served at `/catalog/media/{id}` to an author and `/learn/media/{id}`
     * to a student, and the two pages already pass that prefix in as a prop
     * (`mediaShowUrl`). Freezing a URL into a snapshot would pick one audience
     * forever.
     *
     * A legacy row from the Blade quiz migration carries `{path, kind}` — a
     * filesystem path that never entered the media system at all. It is
     * returned with a null `media_id` rather than dropped, so the gap shows on
     * screen instead of the question quietly losing its picture.
     *
     * @return list<array{kind: string, media_id: ?int, mime: ?string, name: ?string, embed_url: ?string}>
     */
    public function execute(mixed $attachments): array
    {
        if (! is_array($attachments)) {
            return [];
        }

        $resolved = [];

        foreach ($attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            $embedUrl = trim((string) ($attachment['embed_url'] ?? ''));
            if ($embedUrl !== '') {
                $resolved[] = [
                    'kind' => ContentBlockType::Video->value,
                    'media_id' => null,
                    'mime' => null,
                    'name' => $this->name($attachment),
                    'embed_url' => $embedUrl,
                ];

                continue;
            }

            $mime = trim((string) ($attachment['mime'] ?? ''));
            $kind = $mime !== '' ? $this->kindForMime($mime) : null;
            $stored = trim((string) ($attachment['kind'] ?? ''));

            $resolved[] = [
                // A stored `kind` is the legacy shape's only clue; a sniffed
                // mime is better evidence, so it wins where both exist.
                'kind' => $kind?->value ?? (ContentBlockType::tryFrom($stored)?->value ?? $stored),
                'media_id' => isset($attachment['media_id']) ? (int) $attachment['media_id'] : null,
                'mime' => $mime !== '' ? $mime : null,
                'name' => $this->name($attachment),
                'embed_url' => null,
            ];
        }

        return $resolved;
    }

    /**
     * Every media id one question's attachments point at.
     *
     * `ServeCatalogMediaAction` asks this to decide whether a student sitting an
     * assessment may read a file, so it must agree with `execute()` about what
     * counts as an attachment — which is why both live here.
     *
     * @return list<int>
     */
    public function mediaIds(mixed $attachments): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (array $media): int => (int) ($media['media_id'] ?? 0),
            $this->execute($attachments),
        ))));
    }

    private function name(mixed $attachment): ?string
    {
        $name = trim((string) ($attachment['original_name'] ?? $attachment['title'] ?? ''));

        return $name !== '' ? $name : null;
    }
}
