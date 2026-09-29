<?php

namespace App\Domains\Library\Enums;

/**
 * How readers get an item (RESEARCH_ARTICLES_PLAN D1, the owner 2026-09-29:
 * "both, but the author has option to set").
 *
 * - **reader**: the protected reader only — pages served one at a time with
 *   the reader's name on each; the file never leaves the server.
 * - **download**: the PDF itself, as a file to keep.
 * - **both**: either.
 *
 * Only research and articles may choose. A book stays in the protected
 * reader: that reader is the book's copy protection (LIBRARY_PLAN §36).
 */
enum LibraryDelivery: string
{
    case Reader = 'reader';
    case Download = 'download';
    case Both = 'both';

    /** The content types whose author chooses; every other type is the reader. */
    public const CHOOSERS = ['research', 'article'];

    public static function offeredFor(?string $contentType): bool
    {
        return in_array($contentType, self::CHOOSERS, true);
    }

    /**
     * The default when nobody has chosen: open access reads and downloads,
     * anything that is sold or granted reads only.
     */
    public static function defaultFor(?string $contentType, ?string $accessType): self
    {
        if (! self::offeredFor($contentType)) {
            return self::Reader;
        }

        return in_array($accessType, ['free_public', 'free_login'], true) ? self::Both : self::Reader;
    }

    public function allowsDownload(): bool
    {
        return $this !== self::Reader;
    }

    public function allowsReader(): bool
    {
        return $this !== self::Download;
    }
}
