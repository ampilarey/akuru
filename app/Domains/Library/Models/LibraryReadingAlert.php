<?php

namespace App\Domains\Library\Models;

use App\Domains\Library\Enums\LibraryReadingSignal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryReadingAlert extends Model
{
    protected $fillable = [
        'user_id', 'library_item_id', 'signal', 'observed', 'threshold',
        'detail', 'reviewed_by', 'reviewed_at', 'outcome', 'academic_year_id',
    ];

    protected function casts(): array
    {
        return [
            'signal' => LibraryReadingSignal::class,
            'observed' => 'integer',
            'threshold' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(LibraryItem::class, 'library_item_id');
    }

    public function isOpen(): bool
    {
        return $this->reviewed_at === null;
    }
}
