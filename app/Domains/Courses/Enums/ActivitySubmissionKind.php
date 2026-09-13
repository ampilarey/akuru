<?php

namespace App\Domains\Courses\Enums;

/**
 * SPEC §36 "Teacher / Instructor / Reviewer Dashboard" lists thirteen abilities.
 * Three of them are about what the student handed in:
 *
 *   > Open student submissions · Play audio/voice submissions · View uploaded files
 *
 * All three rested on a submission kind the student side could not produce.
 * `SaveActivityAction` already validated and stored `submission_kind` for a
 * teacher-marked activity, accepting `written` or `file` — and nothing ever
 * read it. The player rendered a `<textarea>` for every teacher-marked
 * activity regardless, there was no upload endpoint for an attempt, and the
 * review screen answered all three of §36's lines with
 * `JSON.stringify(row.answers)`.
 *
 * So an author could set `file`, the value would round-trip through the
 * database intact, and the student would still be shown a text box. This enum
 * is the read side that was missing, plus `audio` — which §36 names
 * explicitly and the old two-value list had no room for.
 *
 * Rule 11: the MIME allowlists and size caps are `ContentBlockType`'s, not a
 * second copy. §30's limits are decided in one place.
 */
enum ActivitySubmissionKind: string
{
    case Written = 'written';
    case File = 'file';
    case Audio = 'audio';

    public static function fromValue(mixed $value): self
    {
        return self::tryFrom(is_string($value) ? $value : '') ?? self::Written;
    }

    /**
     * The media kinds a student may hand in under this submission kind.
     *
     * `audio` is deliberately narrow — §36 asks the teacher to *play* it, and a
     * PDF uploaded into an audio slot is a submission the teacher cannot mark
     * the way the activity intended.
     *
     * @return list<ContentBlockType>
     */
    public function mediaTypes(): array
    {
        return match ($this) {
            self::Written => [],
            self::Audio => [ContentBlockType::Audio],
            self::File => [
                ContentBlockType::Image,
                ContentBlockType::Pdf,
                ContentBlockType::Download,
                ContentBlockType::Audio,
            ],
        };
    }

    public function acceptsUploads(): bool
    {
        return $this->mediaTypes() !== [];
    }

    /**
     * @return list<string>
     */
    public function allowedMimes(): array
    {
        $mimes = [];
        foreach ($this->mediaTypes() as $type) {
            $mimes = array_merge($mimes, $type->allowedMimes());
        }

        return array_values(array_unique($mimes));
    }

    /**
     * The largest file this kind accepts — the most generous of the kinds it
     * allows. `StorePrivateMediaAction` still rejects a MIME outside the list,
     * so a 25MB image cannot ride in on the PDF allowance.
     */
    public function maxBytes(): ?int
    {
        $caps = array_filter(array_map(
            static fn (ContentBlockType $type): ?int => $type->maxBytes(),
            $this->mediaTypes(),
        ));

        return $caps === [] ? null : max($caps);
    }

    public function label(): string
    {
        return match ($this) {
            self::Written => 'Written response',
            self::File => 'File upload',
            self::Audio => 'Audio recording',
        };
    }
}
