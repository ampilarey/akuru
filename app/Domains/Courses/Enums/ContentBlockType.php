<?php

namespace App\Domains\Courses\Enums;

enum ContentBlockType: string
{
    case Text = 'text';
    case RichText = 'rich_text';
    case Instruction = 'instruction';
    case Image = 'image';
    case Audio = 'audio';
    case Video = 'video';
    case Pdf = 'pdf';
    case Glossary = 'glossary';
    case Term = 'term';
    case Dialogue = 'dialogue';
    case Flashcard = 'flashcard';
    case Download = 'download';
    case QuizEmbed = 'quiz_embed';
    case AssignmentEmbed = 'assignment_embed';

    /**
     * @return list<string>
     */
    public function allowedMimes(): array
    {
        return match ($this) {
            self::Image => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            self::Audio => ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 'audio/ogg', 'audio/webm', 'audio/mp4'],
            self::Video => ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'],
            self::Pdf => ['application/pdf'],
            self::Download => [
                'application/pdf',
                'application/zip',
                'application/x-zip-compressed',
                'text/plain',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
            default => [],
        };
    }

    /**
     * SPEC §30 "Upload Validation" sets a limit per kind of media:
     *
     *   > Images: max 5MB · Audio: max 20MB · Video: max 200MB · PDFs: max 25MB
     *
     * Every media block shared one blanket 50MB cap instead, which was wrong
     * in both directions: an image could be ten times its allowance, and a
     * video was held to a quarter of its own — so the one type §30 gives room
     * to was the one type that could not use it, and an ordinary lesson video
     * was rejected with a size error that named no limit the spec recognises.
     *
     * Download has no §30 row of its own. It carries documents and archives,
     * so it keeps the PDF allowance rather than inventing a number.
     */
    public function maxBytes(): ?int
    {
        return match ($this) {
            self::Image => 5 * 1024 * 1024,
            self::Audio => 20 * 1024 * 1024,
            self::Video => 200 * 1024 * 1024,
            self::Pdf, self::Download => 25 * 1024 * 1024,
            default => null,
        };
    }

    /**
     * The most any media block may weigh — §30's video allowance.
     *
     * Request rules run before the block type is known, so this is the outer
     * bound they can enforce. The type's own `maxBytes()` is the limit a
     * caller actually hits, applied once the type is resolved.
     */
    public static function largestMaxBytes(): int
    {
        return max(array_map(
            static fn (self $type): int => $type->maxBytes() ?? 0,
            self::cases(),
        ));
    }

    public function isMedia(): bool
    {
        return in_array($this, [self::Image, self::Audio, self::Video, self::Pdf, self::Download], true);
    }
}
