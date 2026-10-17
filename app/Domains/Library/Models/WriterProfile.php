<?php

namespace App\Domains\Library\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WriterProfile extends Model
{
    protected $fillable = [
        'user_id',
        // BLOG_PLAN BL1: `writer`, or `student` (a student's blog).
        'kind',
        'display_name',
        'blog_title',
        'slug',
        'bio',
        'qualifications',
        'expertise',
        'photo_media_file_id',
        // A student's parent chose these at consent (BL1).
        'show_photo',
        'show_school',
        'guardian_user_id',
        'guardian_consented_at',
        'featured_item_ids',
        'social_links',
        // BL3a: the blog's page design, live and waiting.
        'theme_key',
        'accent_color',
        'heading_font',
        'tagline',
        'languages',
        'sections',
        'pending_page',
        'page_submitted_at',
        'page_decision_note',
        'status',
        'approved_at',
        'approved_by',
        'default_commission',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'default_commission' => 'decimal:2',
            'featured_item_ids' => 'array',
            'social_links' => 'array',
            'show_photo' => 'boolean',
            'show_school' => 'boolean',
            'guardian_consented_at' => 'datetime',
            'languages' => 'array',
            'sections' => 'array',
            'pending_page' => 'array',
            'page_submitted_at' => 'datetime',
        ];
    }

    public function isStudent(): bool
    {
        return $this->kind === 'student';
    }

    public function items(): HasMany
    {
        return $this->hasMany(LibraryItem::class, 'writer_id');
    }
}
