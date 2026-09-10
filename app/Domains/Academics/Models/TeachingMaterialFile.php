<?php

namespace App\Domains\Academics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file attached to a teaching material.
 *
 * `media_file_id` is a handle Academics hands back to Media, never a relation
 * it resolves itself (rule 3) — which is why the name, mime and size are copied
 * here at upload time. Listing a material must not read another domain's table.
 */
class TeachingMaterialFile extends Model
{
    protected $fillable = [
        'teaching_material_id',
        'media_file_id',
        'original_name',
        'mime',
        'size',
        'uploaded_by',
    ];

    protected $casts = [
        'media_file_id' => 'integer',
        'size' => 'integer',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(TeachingMaterial::class, 'teaching_material_id');
    }
}
