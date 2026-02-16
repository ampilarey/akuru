<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'student_id',
        'course_id',
        'amount',
        'currency',
        'status',
        'provider',
        'merchant_reference',
        'provider_reference',
        'redirect_url',
        'callback_payload',
        'confirmed_at',
        'uuid',
        'payable_type',
        'payable_id',
        'amount_mvr',
        'amount_laar',
        'local_id',
        'bml_transaction_id',
        'payment_url',
        'bml_status_raw',
        'redirect_return_payload',
        'webhook_payload',
        'paid_at',
        'failed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_mvr' => 'decimal:2',
            'callback_payload' => 'array',
            'bml_status_raw' => 'array',
            'redirect_return_payload' => 'array',
            'webhook_payload' => 'array',
            'confirmed_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            if (empty($payment->uuid)) {
                $payment->uuid = (string) Str::uuid();
            }
            if (empty($payment->local_id)) {
                $payment->local_id = $payment->merchant_reference ?? 'AKURU-' . strtoupper(Str::uuid()->toString());
            }
            if (empty($payment->merchant_reference) && ! empty($payment->local_id)) {
                $payment->merchant_reference = $payment->local_id;
            }
            if (isset($payment->amount) && ! isset($payment->amount_mvr)) {
                $payment->amount_mvr = $payment->amount;
            }
            if (isset($payment->amount_mvr) && ! isset($payment->amount_laar)) {
                $payment->amount_laar = (int) round((float) $payment->amount_mvr * 100);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(RegistrationStudent::class, 'student_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PaymentItem::class);
    }

    public function enrollments(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            CourseEnrollment::class,
            PaymentItem::class,
            'payment_id',
            'id',
            'id',
            'enrollment_id'
        );
    }

    public function isConfirmed(): bool
    {
        return in_array($this->status, ['confirmed', 'paid'], true);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid' || $this->status === 'confirmed';
    }

    public function isPendingWebhook(): bool
    {
        return in_array($this->status, ['pending_redirect', 'pending_webhook'], true);
    }

    /** Resolve payment by bml_transaction_id or local_id (for webhook idempotency). */
    public static function findByBmlReference(string $bmlTransactionId, ?string $localId = null): ?self
    {
        $q = static::query();
        $q->where(function ($q) use ($bmlTransactionId, $localId) {
            $q->where('bml_transaction_id', $bmlTransactionId);
            if ($localId !== null && $localId !== '') {
                $q->orWhere('local_id', $localId);
            }
        });
        return $q->first();
    }
}
