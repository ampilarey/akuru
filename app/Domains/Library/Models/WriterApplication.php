<?php

namespace App\Domains\Library\Models;

use Illuminate\Database\Eloquent\Model;

class WriterApplication extends Model
{
    protected $fillable = [
        'user_id',
        'display_name',
        'bio',
        'qualifications',
        'expertise',
        'motivation',
        'previous_publications',
        'photo_media_file_id',
        'id_document_media_file_id',
        'agreement_accepted_at',
        'status',
        'decided_by',
        'decided_at',
        'decision_note',
    ];

    protected function casts(): array
    {
        return [
            'agreement_accepted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }
}
