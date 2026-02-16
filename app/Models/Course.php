<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Course extends Model
{
    use HasFactory;
    protected $fillable = [
        'course_category_id',
        'title',
        'slug',
        'short_desc',
        'body',
        'cover_image',
        'language',
        'level',
        'schedule',
        'fee',
        'registration_fee_amount',
        'registration_fee_currency',
        'requires_admin_approval',
        'status',
        'seats',
        'meta',
        'duration_weeks',
        'prerequisites',
        'learning_objectives',
        'instructor_notes',
        'is_featured',
        'sort_order',
        'enrollment_deadline',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'schedule' => 'array',
        'fee' => 'decimal:2',
        'registration_fee_amount' => 'decimal:2',
        'requires_admin_approval' => 'boolean',
        'meta' => 'array',
        'prerequisites' => 'array',
        'learning_objectives' => 'array',
        'is_featured' => 'boolean',
        'enrollment_deadline' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, 'course_category_id');
    }

    public function admissionApplications(): HasMany
    {
        return $this->hasMany(AdmissionApplication::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function hasRegistrationFee(): bool
    {
        return (float) ($this->registration_fee_amount ?? 0) > 0;
    }

    public function getRegistrationFeeAmount(): float
    {
        return (float) ($this->registration_fee_amount ?? 0);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeByLanguage($query, $language)
    {
        return $query->where('language', $language)->orWhere('language', 'mixed');
    }

    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level)->orWhere('level', 'all');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'open')
                    ->where(function($q) {
                        $q->whereNull('enrollment_deadline')
                          ->orWhere('enrollment_deadline', '>=', now());
                    });
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'upcoming')
                    ->where('start_date', '>', now());
    }

    public function getAvailableSeatsAttribute()
    {
        if (!$this->seats) return null;
        
        $enrolled = $this->admissionApplications()
                         ->whereIn('status', ['pending', 'approved'])
                         ->count();
        
        return max(0, $this->seats - $enrolled);
    }

    public function getIsEnrollmentOpenAttribute()
    {
        if ($this->status !== 'open') return false;
        
        if (!$this->enrollment_deadline) return true;
        
        return $this->enrollment_deadline >= now();
    }

    public function getFormattedFeeAttribute()
    {
        if (!$this->fee) return 'Free';
        
        return 'MVR ' . number_format($this->fee, 2);
    }

    public function getDurationTextAttribute()
    {
        if (!$this->duration_weeks) return 'Ongoing';
        
        if ($this->duration_weeks == 1) return '1 week';
        if ($this->duration_weeks < 4) return $this->duration_weeks . ' weeks';
        
        $months = round($this->duration_weeks / 4);
        return $months . ' month' . ($months > 1 ? 's' : '');
    }

    protected function slug(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => \Str::slug($value),
        );
    }
}
