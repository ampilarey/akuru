<?php

namespace App\Domains\People\Models;

use App\Domains\People\Enums\StudentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentStatusHistory extends Model
{
    protected $table = 'student_status_history';

    protected $fillable = [
        'student_id',
        'from_status',
        'to_status',
        'reason',
        'effective_date',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => StudentStatus::class,
            'to_status' => StudentStatus::class,
            'effective_date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
