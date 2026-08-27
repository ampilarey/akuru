<?php

namespace App\Domains\Library\Enums;

/**
 * LIBRARY_PLAN §6 — one per item. L1 enforces the two free types; paid,
 * course and manual exist in the schema but gate to "locked" until L3.
 */
enum LibraryAccessType: string
{
    case FreePublic = 'free_public';
    case FreeLogin = 'free_login';
    case Paid = 'paid';
    case Course = 'course';
    case Manual = 'manual';
}
