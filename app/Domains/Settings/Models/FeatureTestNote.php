<?php

namespace App\Domains\Settings\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One entry in the feature-testing log: a feature marked working, broken or
 * blocked, with the tester's comment. Append-only — a later entry is the
 * feature's new state and the earlier ones stay as its history.
 */
class FeatureTestNote extends Model
{
    public const STATUSES = ['works', 'broken', 'blocked'];

    protected $fillable = ['item_key', 'status', 'comment', 'user_id'];
}
