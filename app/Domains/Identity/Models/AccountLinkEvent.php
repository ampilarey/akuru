<?php

namespace App\Domains\Identity\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only. Nothing updates or deletes these rows: the whole point is
 * answering "who linked what, when, and who tried and failed".
 */
class AccountLinkEvent extends Model
{
    public const LINKED = 'linked';

    public const UNLINKED = 'unlinked';

    public const SWITCHED = 'switched';

    public const FAILED = 'link_failed';

    protected $fillable = ['user_id', 'target_user_id', 'action', 'ip'];
}
