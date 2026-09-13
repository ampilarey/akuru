<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\ContentBlockType;
use App\Domains\Media\Actions\StorePrivateMediaAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §22 "Glossary Media":
 *
 *   > Glossary items may have: Audio · Image · Example audio · Diagram
 *   > All media must use the centralized media system.
 *
 * All four columns exist. The model is fillable for them,
 * `SaveGlossaryItemAction` writes them, `GlossaryController` validates them
 * properly against `media_files`, and `GlossaryItem::toPayload()` sends them to
 * the lesson player.
 *
 * **Nothing ever uploaded one.** The admin form has no file input of any kind,
 * so there was no way to obtain a `media_files` id to put in those columns —
 * the validation guarded a door nobody could reach. And the player's term panel
 * draws term, transliteration, meaning, description and example, and no media
 * at all.
 *
 * For an Arabic vocabulary bank that is the wrong half to be missing: the
 * pronunciation recording is the part a term most needs, and §22 lists it
 * first.
 *
 * Each slot is **typed**, which is what makes this simpler than §20's free-form
 * question attachment: the audio slot takes audio and the image and diagram
 * slots take images. §30's per-kind mime lists and size caps already live on
 * `ContentBlockType`, so this maps slots onto those rather than starting a
 * third table of "which mimes count as audio" (rule 11).
 */
class StoreGlossaryMediaAction
{
    /**
     * Slot column => the §30 media kind it accepts.
     *
     * A diagram is an image; §22 lists it separately because it means something
     * different to a reader, not because it is a different file.
     *
     * @var array<string, string>
     */
    public const SLOTS = [
        'audio_media_id' => 'audio',
        'image_media_id' => 'image',
        'example_audio_media_id' => 'audio',
        'diagram_media_id' => 'image',
    ];

    /**
     * The request field each slot is uploaded through.
     */
    public static function fileField(string $slot): string
    {
        return str_replace('_media_id', '_file', $slot);
    }

    /**
     * Store whatever files arrived and return the slot => media id pairs.
     *
     * Slots with no file are absent from the result rather than null, so a save
     * that mentions no media leaves the existing media alone — the same rule
     * §20's question attachments needed, and for the same reason: an edit that
     * does not mention a file must not delete it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, int>
     */
    public function execute(array $data, ?int $uploadedBy = null): array
    {
        $stored = [];

        foreach (self::SLOTS as $slot => $kind) {
            $file = $data[self::fileField($slot)] ?? null;
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $blockType = ContentBlockType::from($kind);
            $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType());

            if (! in_array($mime, $blockType->allowedMimes(), true)) {
                throw ValidationException::withMessages([
                    self::fileField($slot) => 'That slot takes '.$kind.'. '.$mime.' is not '.$kind.'.',
                ]);
            }

            $stored[$slot] = app(StorePrivateMediaAction::class)->execute(
                $file,
                $uploadedBy,
                $blockType->allowedMimes(),
                $blockType->maxBytes(),
            )['id'];
        }

        return $stored;
    }

    /**
     * Slots the caller asked to clear.
     *
     * Removing a recording has to be possible without replacing it, and an
     * absent field already means "leave it alone" — so clearing needs a signal
     * of its own rather than an empty upload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, null>
     */
    public function cleared(array $data): array
    {
        $cleared = [];
        $requested = $data['clear_media'] ?? [];
        $requested = is_array($requested) ? $requested : [$requested];

        foreach ($requested as $slot) {
            if (array_key_exists((string) $slot, self::SLOTS)) {
                $cleared[(string) $slot] = null;
            }
        }

        return $cleared;
    }
}
