<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'created_by',
        'title',
        'title_arabic',
        'title_dhivehi',
        'content',
        'content_arabic',
        'content_dhivehi',
        'type',
        'priority',
        'target_audience',
        'target_classes',
        'publish_date',
        'expiry_date',
        'is_published',
        'attachment',
    ];

    protected $casts = [
        'target_audience' => 'array',
        'target_classes' => 'array',
        'publish_date' => 'date',
        'expiry_date' => 'date',
        'is_published' => 'boolean',
    ];

    // Relationships
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
