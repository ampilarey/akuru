<?php

namespace App\Domains\Hifz\Models;

use App\Domains\Hifz\Models\QuranAyah;
use App\Domains\Hifz\Models\QuranPage;
use App\Domains\Hifz\Models\QuranWord;
use App\Domains\Identity\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
