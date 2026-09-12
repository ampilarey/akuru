<?php

namespace App\Domains\Courses\Components\Quran\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuranMushaf extends Model
{
    protected $fillable = [
        'name', 'description', 'source_type', 'source_file_path', 'source_hash',
        'page_count', 'is_active', 'locked', 'approved_by', 'approved_at',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'locked' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function pages(): HasMany
    {
        return $this->hasMany(QuranPage::class);
    }

    public function ayahs(): HasMany
    {
        return $this->hasMany(QuranAyah::class);
    }

    public function words(): HasMany
    {
        return $this->hasMany(QuranWord::class);
    }

    /*
     * F5: `approver()` was a `belongsTo(Identity\Models\User)`. Moving this
     * model into the engine would have made that a fresh cross-domain model
     * import (rule 3) rather than an inherited one, and the only reader was the
     * Blade index's eager load. `approved_by` still records who approved.
     */
}
