<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;

/** NAME_TAGS_PLAN §4.8 (NT4): a reprint — why and by whom. Append-only; it charges nothing. */
class PrintJobReprint extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['print_job_id', 'reason', 'by_user_id', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
