<?php

namespace App\Domains\Courses\Models;

use App\Domains\Finance\Models\Payment;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class CourseEnrollment extends Model
{
    protected $fillable = [
        'student_id',
        'unified_student_id',
        'course_id',
        'term_id',
        'status',
        'enrolled_at',
        'created_by_user_id',
        'payment_status',
        'payment_id',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CourseEnrollment $enrollment): void {
            if ($enrollment->unified_student_id || ! $enrollment->student_id) {
                return;
            }

            $unifiedId = DB::table('students')
                ->where('legacy_registration_student_id', $enrollment->student_id)
                ->value('id');

            if ($unifiedId !== null) {
                $enrollment->unified_student_id = $unifiedId;
            }
        });
    }

    /** Canonical student (Deploy 2). */
    public function student(): BelongsTo
    {
        return $this->belongsTo(config('domain-models.student'), 'unified_student_id');
    }

    /** @deprecated Dual-write FK to registration_students. */
    public function legacyStudent(): BelongsTo
    {
        return $this->belongsTo(config('domain-models.registration_student'), 'student_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function paymentItem(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PaymentItem::class, 'enrollment_id');
    }

    public function requiresPayment(): bool
    {
        return $this->payment_status !== 'not_required';
    }

    public function isPaymentConfirmed(): bool
    {
        return $this->payment_status === 'confirmed';
    }
}
