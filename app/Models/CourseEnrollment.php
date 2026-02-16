<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseEnrollment extends Model
{
    protected $fillable = [
        'student_id',
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

    public function student(): BelongsTo
    {
        return $this->belongsTo(RegistrationStudent::class, 'student_id');
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
