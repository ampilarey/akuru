<?php

namespace App\Domains\Settings\Models;

use Illuminate\Database\Eloquent\Model;

/** BK1: one backup run — what it made, where it went, or why it failed. */
class BackupRun extends Model
{
    protected $fillable = ['name', 'status', 'started_at', 'finished_at', 'size_bytes', 'database_bytes', 'files_bytes', 'tables', 'offsite', 'offsite_error', 'removed', 'error'];

    protected $casts = ['started_at' => 'datetime', 'finished_at' => 'datetime', 'offsite' => 'boolean'];
}
