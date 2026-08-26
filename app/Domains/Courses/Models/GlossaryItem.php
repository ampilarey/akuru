<?php

namespace App\Domains\Courses\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GlossaryItem extends Model
{
    protected $fillable = [
        'subject_id',
        'category_id',
        'term',
        'term_dv',
        'term_ar',
        'transliteration',
        'meaning_primary',
        'meaning_secondary',
        'meaning_dv',
        'meaning_ar',
        'description',
        'description_dv',
        'description_ar',
        'example_text',
        'example_translation',
        'example_text_dv',
        'example_text_ar',
        'tags',
        'level_id',
        'created_by',
        'audio_media_id',
        'image_media_id',
        'example_audio_media_id',
        'diagram_media_id',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(CourseSubject::class, 'subject_id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(CourseLevel::class, 'level_id');
    }

    public function lessons(): BelongsToMany
    {
        return $this->belongsToMany(Lesson::class, 'lesson_glossary_items')
            ->withPivot(['position', 'is_required'])
            ->withTimestamps();
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(?int $position = null, ?bool $isRequired = null): array
    {
        return [
            'id' => $this->id,
            'subject_id' => $this->subject_id,
            'category_id' => $this->category_id,
            'term' => $this->term,
            'term_dv' => $this->term_dv,
            'term_ar' => $this->term_ar,
            'transliteration' => $this->transliteration,
            'meaning_primary' => $this->meaning_primary,
            'meaning_secondary' => $this->meaning_secondary,
            'meaning_dv' => $this->meaning_dv,
            'meaning_ar' => $this->meaning_ar,
            'description' => $this->description,
            'description_dv' => $this->description_dv,
            'description_ar' => $this->description_ar,
            'example_text' => $this->example_text,
            'example_translation' => $this->example_translation,
            'example_text_dv' => $this->example_text_dv,
            'example_text_ar' => $this->example_text_ar,
            'tags' => $this->tags ?? [],
            'level_id' => $this->level_id,
            'audio_media_id' => $this->audio_media_id,
            'image_media_id' => $this->image_media_id,
            'example_audio_media_id' => $this->example_audio_media_id,
            'diagram_media_id' => $this->diagram_media_id,
            'position' => $position,
            'is_required' => $isRequired,
        ];
    }
}
