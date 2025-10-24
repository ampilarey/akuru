<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class EventRegistration extends Model
{
    protected $fillable = [
        'event_id',
        'name',
        'email',
        'phone',
        'organization',
        'notes',
        'status',
        'registration_source',
        'additional_info',
        'dietary_requirements',
        'dietary_notes',
        'transportation_needed',
        'transportation_notes',
        'accommodation_needed',
        'accommodation_notes',
        'amount_paid',
        'payment_method',
        'payment_reference',
        'payment_date',
        'cancellation_reason',
        'cancelled_at',
        'confirmed_at',
        'checked_in_at',
        'qr_code',
        'meta',
    ];

    protected $casts = [
        'additional_info' => 'array',
        'dietary_requirements' => 'boolean',
        'transportation_needed' => 'boolean',
        'accommodation_needed' => 'boolean',
        'amount_paid' => 'decimal:2',
        'payment_date' => 'datetime',
        'cancelled_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'meta' => 'array',
    ];

    // Relationships
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    // Scopes
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeAttended($query)
    {
        return $query->where('status', 'attended');
    }

    public function scopeBySource($query, $source)
    {
        return $query->where('registration_source', $source);
    }

    // Accessors
    public function getStatusBadgeColorAttribute()
    {
        return match($this->status) {
            'confirmed' => 'green',
            'pending' => 'yellow',
            'cancelled' => 'red',
            'attended' => 'blue',
            'no_show' => 'gray',
            default => 'gray',
        };
    }

    public function getFormattedAmountPaidAttribute()
    {
        if (!$this->amount_paid) return 'Free';
        
        return 'MVR ' . number_format($this->amount_paid, 2);
    }

    public function getIsPaidAttribute()
    {
        return $this->amount_paid > 0;
    }

    public function getIsCheckedInAttribute()
    {
        return !is_null($this->checked_in_at);
    }

    public function getHasSpecialRequirementsAttribute()
    {
        return $this->dietary_requirements || 
               $this->transportation_needed || 
               $this->accommodation_needed;
    }

    // Methods
    public function confirm()
    {
        $this->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
        
        // Update event attendee count
        $this->event->updateAttendeeCount();
    }

    public function cancel($reason = null)
    {
        $this->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
            'cancelled_at' => now(),
        ]);
        
        // Update event attendee count
        $this->event->updateAttendeeCount();
    }

    public function checkIn()
    {
        $this->update([
            'status' => 'attended',
            'checked_in_at' => now(),
        ]);
    }

    public function markAsNoShow()
    {
        $this->update([
            'status' => 'no_show',
        ]);
    }

    public function generateQrCode()
    {
        // Generate a unique QR code for this registration
        $this->qr_code = 'EVT-' . $this->event_id . '-' . $this->id . '-' . strtoupper(substr(md5($this->email . $this->created_at), 0, 8));
        $this->save();
        
        return $this->qr_code;
    }

    public function getQrCodeUrl()
    {
        if (!$this->qr_code) {
            $this->generateQrCode();
        }
        
        return route('public.events.qr', $this->qr_code);
    }

    public function getRegistrationSummary()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'organization' => $this->organization,
            'status' => $this->status,
            'amount_paid' => $this->formatted_amount_paid,
            'registration_date' => $this->created_at->format('M j, Y g:i A'),
            'event_title' => $this->event->title,
            'event_date' => $this->event->short_duration,
            'special_requirements' => $this->has_special_requirements,
        ];
    }
}