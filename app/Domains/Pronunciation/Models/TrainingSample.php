<?php

namespace App\Domains\Pronunciation\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingSample extends Model
{
    protected $fillable = [
        'arabic_pronunciation_attempt_id',
        'audio_media_file_id',
        'verified_letter_id',
        'verified_haraka_id',
        'original_predicted_letter_id',
        'original_predicted_haraka_id',
        'reviewed_by_user_id',
        'approved_by_user_id',
        'status',
        'rejection_reason',
        'notes',
    ];
}
