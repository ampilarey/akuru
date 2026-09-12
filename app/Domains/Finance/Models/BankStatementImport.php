<?php

namespace App\Domains\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatementImport extends Model
{
    protected $fillable = [
        'original_filename', 'source_hash', 'format', 'account_label',
        'period_start', 'period_end', 'line_count', 'academic_year_id', 'imported_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'line_count' => 'integer',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class);
    }
}
