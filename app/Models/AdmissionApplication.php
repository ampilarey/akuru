<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionApplication extends Model
{
    protected static function booted(): void
    {
        static::creating(function (AdmissionApplication $model) {
            if (empty($model->application_number)) {
                $year = now()->year;
                $prefix = 'AKU';
                $sequence = str_pad(static::whereYear('created_at', $year)->count() + 1, 4, '0', STR_PAD_LEFT);
                $model->application_number = "{$prefix}{$year}{$sequence}";
            }
            // Public form uses full_name; sync to student_name for legacy admin compatibility
            if (empty($model->student_name) && !empty($model->full_name)) {
                $model->student_name = $model->full_name;
            }
        });
    }

    protected $fillable = [
        'course_id',
        'application_number',
        'full_name',
        'date_of_birth',
        'gender',
        'phone',
        'email',
        'address',
        'guardian_name',
        'guardian_phone',
        'guardian_email',
        'guardian_relationship',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'previous_education',
        'previous_islamic_education',
        'quran_knowledge_level',
        'arabic_knowledge_level',
        'learning_goals',
        'special_needs',
        'medical_conditions',
        'allergies',
        'message',
        'source',
        'locale',
        'ip',
        'user_agent',
        'status',
        'priority',
        'application_fee_paid',
        'application_fee_amount',
        'application_fee_payment_method',
        'application_fee_payment_reference',
        'application_fee_payment_date',
        'documents_submitted',
        'documents_verified',
        'interview_scheduled',
        'interview_date',
        'interview_notes',
        'interview_score',
        'recommendation_notes',
        'admission_decision',
        'admission_decision_date',
        'admission_decision_notes',
        'enrollment_date',
        'assigned_to',
        'admin_notes',
        'custom_fields',
        'meta',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'application_fee_paid' => 'boolean',
        'documents_submitted' => 'array',
        'documents_verified' => 'boolean',
        'interview_scheduled' => 'boolean',
        'interview_date' => 'datetime',
        'admission_decision_date' => 'date',
        'enrollment_date' => 'date',
        'custom_fields' => 'array',
        'meta' => 'array',
    ];

    // Relationships
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    // Scopes
    public function scopeNew($query)
    {
        return $query->where('status', 'new');
    }

    public function scopeUnderReview($query)
    {
        return $query->where('status', 'under_review');
    }

    public function scopeInterviewScheduled($query)
    {
        return $query->where('status', 'interview_scheduled');
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeEnrolled($query)
    {
        return $query->where('status', 'enrolled');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeByCourse($query, $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to');
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    // Accessors
    public function getStatusBadgeColorAttribute()
    {
        return match($this->status) {
            'new' => 'blue',
            'under_review' => 'yellow',
            'interview_scheduled' => 'purple',
            'accepted' => 'green',
            'rejected' => 'red',
            'enrolled' => 'green',
            'withdrawn' => 'gray',
            default => 'gray',
        };
    }

    public function getPriorityBadgeColorAttribute()
    {
        return match($this->priority) {
            'low' => 'green',
            'medium' => 'blue',
            'high' => 'orange',
            'urgent' => 'red',
            default => 'blue',
        };
    }

    public function getAgeAttribute()
    {
        return $this->date_of_birth ? $this->date_of_birth->age : null;
    }

    public function getFormattedDateOfBirthAttribute()
    {
        return $this->date_of_birth ? $this->date_of_birth->format('M j, Y') : null;
    }

    public function getFormattedCreatedAtAttribute()
    {
        return $this->created_at->format('M j, Y g:i A');
    }

    public function getShortCreatedAtAttribute()
    {
        return $this->created_at->diffForHumans();
    }

    public function getIsOverdueAttribute()
    {
        $overdueDays = match($this->status) {
            'new' => 7,
            'under_review' => 14,
            'interview_scheduled' => 3,
            default => 0,
        };
        
        if ($overdueDays === 0) return false;
        
        return $this->created_at->addDays($overdueDays)->isPast();
    }

    public function getDaysSinceCreatedAttribute()
    {
        return $this->created_at->diffInDays(now());
    }

    public function getIsAssignedAttribute()
    {
        return !is_null($this->assigned_to);
    }

    public function getIsEnrolledAttribute()
    {
        return $this->status === 'enrolled';
    }

    public function getIsAcceptedAttribute()
    {
        return in_array($this->status, ['accepted', 'enrolled']);
    }

    public function getIsRejectedAttribute()
    {
        return $this->status === 'rejected';
    }

    // Methods
    public function generateApplicationNumber()
    {
        if (!$this->application_number) {
            $year = now()->year;
            $prefix = 'AKU';
            $sequence = str_pad(static::whereYear('created_at', $year)->count() + 1, 4, '0', STR_PAD_LEFT);
            $this->application_number = "{$prefix}{$year}{$sequence}";
            $this->save();
        }
        
        return $this->application_number;
    }

    public function markAsUnderReview()
    {
        $this->update(['status' => 'under_review']);
    }

    public function scheduleInterview($date, $notes = null)
    {
        $this->update([
            'status' => 'interview_scheduled',
            'interview_scheduled' => true,
            'interview_date' => $date,
            'interview_notes' => $notes,
        ]);
    }

    public function accept($notes = null)
    {
        $this->update([
            'status' => 'accepted',
            'admission_decision' => 'accepted',
            'admission_decision_date' => now(),
            'admission_decision_notes' => $notes,
        ]);
    }

    public function reject($notes = null)
    {
        $this->update([
            'status' => 'rejected',
            'admission_decision' => 'rejected',
            'admission_decision_date' => now(),
            'admission_decision_notes' => $notes,
        ]);
    }

    public function enroll($enrollmentDate = null)
    {
        $this->update([
            'status' => 'enrolled',
            'enrollment_date' => $enrollmentDate ?? now(),
        ]);
    }

    public function withdraw($notes = null)
    {
        $this->update([
            'status' => 'withdrawn',
            'admin_notes' => $this->admin_notes . "\n\nWithdrawn: " . $notes,
        ]);
    }

    public function assignTo($userId)
    {
        $this->update(['assigned_to' => $userId]);
    }

    public function getApplicationStats()
    {
        return [
            'total' => static::count(),
            'new' => static::new()->count(),
            'under_review' => static::underReview()->count(),
            'interview_scheduled' => static::interviewScheduled()->count(),
            'accepted' => static::accepted()->count(),
            'rejected' => static::rejected()->count(),
            'enrolled' => static::enrolled()->count(),
            'overdue' => static::whereIn('status', ['new', 'under_review', 'interview_scheduled'])
                        ->whereRaw('created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)')
                        ->count(),
        ];
    }

    public function getRecentApplications($limit = 10)
    {
        return static::recent()
                    ->with(['course', 'assignedUser'])
                    ->limit($limit)
                    ->get();
    }

    public function getOverdueApplications()
    {
        return static::whereIn('status', ['new', 'under_review', 'interview_scheduled'])
                    ->whereRaw('created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)')
                    ->with(['course', 'assignedUser'])
                    ->get();
    }
}
