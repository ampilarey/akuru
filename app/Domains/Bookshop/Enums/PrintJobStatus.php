<?php

namespace App\Domains\Bookshop\Enums;

/**
 * NAME_TAGS_PLAN §6.1: a print job's way. `draft` while the customer types;
 * `proofed` once the sheets are rendered and shown; `approved` — locked,
 * priced, ready for the cart; then the order's steps (NT4): `ordered`,
 * `printing`, `finished`; `cancelled` before printing. Only a draft or a
 * proofed job may change; an approved one is copied, never edited.
 */
enum PrintJobStatus: string
{
    case Draft = 'draft';
    case Proofed = 'proofed';
    case Approved = 'approved';
    case Ordered = 'ordered';
    case Printing = 'printing';
    case Finished = 'finished';
    case Cancelled = 'cancelled';

    public function isOpen(): bool
    {
        return $this === self::Draft || $this === self::Proofed;
    }
}
