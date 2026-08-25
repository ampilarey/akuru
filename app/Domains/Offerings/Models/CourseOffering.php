<?php

namespace App\Domains\Offerings\Models;

use App\Domains\Offerings\Enums\DeliveryMode;
use App\Domains\Offerings\Enums\OfferingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseOffering extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'course_id',
        'title',
        'title_dv',
        'title_ar',
        'slug',
        'delivery_mode',
        'status',
        'pin_mode',
        'pinned_revision_json',
        'pinned_at',
        'pinned_by',
        'seat_limit',
        'academic_year_id',
        'term_id',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'delivery_mode' => DeliveryMode::class,
            'status' => OfferingStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'pinned_at' => 'datetime',
            'pinned_revision_json' => 'array',
            'seat_limit' => 'integer',
        ];
    }
}
