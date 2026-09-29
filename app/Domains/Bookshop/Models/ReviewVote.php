<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;

/** A customer found a review helpful (STATUS §5lg). One per review per person. */
class ReviewVote extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['product_review_id', 'user_id'];
}
