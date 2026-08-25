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
            default => [],
        };
    }

    public function isMedia(): bool
    {
        return in_array($this, [self::Image, self::Audio, self::Video, self::Pdf], true);
    }
}
