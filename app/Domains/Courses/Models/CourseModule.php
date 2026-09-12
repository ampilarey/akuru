<?php

namespace App\Domains\Courses\Models;

use App\Domains\Courses\Enums\ModuleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseModule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'course_id',
        'title',
        'title_dv',
        'title_ar',
        'description',
        'position',
        'status',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // SPEC §12 Status. The column existed as a bare string that
            // nothing ever wrote past the create default, so every module was
            // permanently draft.
            'status' => ModuleStatus::class,
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('position');
    }
}
