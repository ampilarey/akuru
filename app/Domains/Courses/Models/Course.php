<?php

namespace App\Domains\Courses\Models;

use App\Domains\Courses\Enums\CourseWorkflowStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    // SPEC §29. Without this, `admin.courses.destroy` hard-deleted the row and
    // `course_enrollments.course_id` / `payment_items.course_id` cascaded the
    // roster and its payment line items away with it.
    use HasFactory, SoftDeletes;

    protected static function newFactory(): \Database\Factories\CourseFactory
    {
        return \Database\Factories\CourseFactory::new();
    }

    protected $fillable = [
        'course_category_id',
        'subject_id',
        'title',
        'title_dv',
        'title_ar',
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
        'workflow_status',
        'course_type',
        'created_by',
        'seats',
        'whatsapp_number',
        'syllabus_media_file_id',
        'meta',
        'unlock_rules',
        'duration_weeks',
        'prerequisites',
        'learning_objectives',
        'learning_outcomes',
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
        'unlock_rules' => 'array',
        'prerequisites' => 'array',
        'learning_objectives' => 'array',
        'learning_outcomes' => 'array',
        'is_featured' => 'boolean',
        'enrollment_deadline' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'workflow_status' => \App\Domains\Courses\Enums\CourseWorkflowStatus::class,
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(CourseSubject::class, 'subject_id');
    }

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
        return $this->hasMany(config('domain-models.admission_application'));
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function instructors(): BelongsToMany
    {
        return $this->belongsToMany(config('domain-models.instructor'));
    }

    public function hasRegistrationFee(): bool
    {
        return $this->getRegistrationFeeAmount() > 0;
    }

    public function getRegistrationFeeAmount(): float
    {
        $amount = (float) ($this->registration_fee_amount ?? 0);
        if ($amount <= 0 && (float) ($this->fee ?? 0) > 0) {
            return (float) $this->fee;
        }

        return $amount;
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
            ->where(function ($q) {
                $q->whereNull('enrollment_deadline')
                    ->orWhere('enrollment_deadline', '>=', now());
            });
    }

    /**
     * Whether the public site may show this course at all.
     *
     * A method rather than an enum comparison at each call site, so callers in
     * other domains do not have to import `Courses\Enums` to ask (rule 3).
     */
    public function isPublished(): bool
    {
        return $this->workflow_status === CourseWorkflowStatus::Published;
    }

    /**
     * What the public site may list.
     *
     * `status` (open/upcoming/closed) is about *enrolment*; `workflow_status`
     * (draft/in_review/published/archived) is about *publication*. This scope
     * filtered on the first and never on the second, so a course being written
     * was on the public site the moment somebody set its status to "open" —
     * 11 of the 12 courses in the seeded dataset were drafts, and all 11 were
     * listed. `PublicSite\CourseController::show()` had no gate at all, so any
     * course was also readable at its own URL whatever state it was in.
     *
     * Publication is now the first condition, because it is the one that says
     * whether anybody outside the institute was meant to see this at all.
     */
    public function scopeOpenForPublicListing($query)
    {
        $today = now()->timezone(config('app.timezone'))->toDateString();

        return $query->where('workflow_status', CourseWorkflowStatus::Published->value)
            ->where(function ($q) use ($today) {
                $q->where('status', 'upcoming')
                    ->orWhere(function ($q) use ($today) {
                        $q->where('status', 'open')
                            ->where(function ($q) use ($today) {
                                $q->whereNull('enrollment_deadline')
                                    ->orWhereDate('enrollment_deadline', '>=', $today);
                            });
                    });
            });
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'upcoming')
            ->where('start_date', '>', now());
    }

    public function getAvailableSeatsAttribute(): ?int
    {
        if (! $this->seats) {
            return null;
        }

        $enrolled = $this->enrollments()
            ->whereIn('status', ['pending', 'active'])
            ->count();

        return max(0, $this->seats - $enrolled);
    }

    public function isFull(): bool
    {
        $available = $this->available_seats;

        return $available !== null && $available <= 0;
    }

    public function getIsEnrollmentOpenAttribute()
    {
        if ($this->status !== 'open') {
            return false;
        }

        if (! $this->enrollment_deadline) {
            return true;
        }

        return $this->enrollment_deadline >= now();
    }

    public function getFormattedFeeAttribute()
    {
        if (! $this->fee) {
            return 'Free';
        }

        return 'MVR '.number_format($this->fee, 2);
    }

    public function getDurationTextAttribute()
    {
        if (! $this->duration_weeks) {
            return 'Ongoing';
        }

        if ($this->duration_weeks == 1) {
            return '1 week';
        }
        if ($this->duration_weeks < 4) {
            return $this->duration_weeks.' weeks';
        }

        $months = round($this->duration_weeks / 4);

        return $months.' month'.($months > 1 ? 's' : '');
    }

    protected function slug(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => \Str::slug($value),
        );
    }
}
