<?php

namespace App\Domains\Library\Models;

use Illuminate\Database\Eloquent\Model;

class LibraryReviewAssignment extends Model
{
    protected $fillable = [
        'library_item_id',
        'reviewer_user_id',
        'assigned_by',
        'status',
        'recommendation',
    ];
}
