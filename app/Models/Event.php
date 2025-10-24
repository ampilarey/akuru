<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;

class Event extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'short_description',
        'cover_image',
        'location',
        'address',
        'latitude',
        'longitude',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'type',
        'status',
        'registration_type',
        'max_attendees',
        'current_attendees',
        'registration_fee',
        'registration_deadline',
        'registration_start',
        'registration_instructions',
        'requirements',
        'speakers',
        'schedule',
        'contact_info',
        'is_featured',
        'is_public',
        'send_reminders',
        'reminder_days',
        'cancellation_policy',
        'refund_policy',
        'meta',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'registration_deadline' => 'datetime',
        'registration_start' => 'datetime',
        'requirements' => 'array',
        'speakers' => 'array',
        'schedule' => 'array',
        'contact_info' => 'array',
        'is_featured' => 'boolean',
        'is_public' => 'boolean',
        'send_reminders' => 'boolean',
        'meta' => 'array',
    ];

    // Relationships
    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function confirmedRegistrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class)->where('status', 'confirmed');
    }

    public function pendingRegistrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class)->where('status', 'pending');
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>', now());
    }

    public function scopePast($query)
    {
        return $query->where('end_date', '<', now());
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByLocation($query, $location)
    {
        return $query->where('location', 'like', "%{$location}%");
    }

    public function scopeRequiresRegistration($query)
    {
        return $query->whereIn('registration_type', ['required', 'optional']);
    }

    public function scopeRegistrationOpen($query)
    {
        return $query->where(function($q) {
            $q->whereNull('registration_start')
              ->orWhere('registration_start', '<=', now());
        })->where(function($q) {
            $q->whereNull('registration_deadline')
              ->orWhere('registration_deadline', '>', now());
        });
    }

    // Accessors
    protected function slug(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => \Str::slug($value),
        );
    }

    public function getIsUpcomingAttribute()
    {
        return $this->start_date > now();
    }

    public function getIsPastAttribute()
    {
        return $this->end_date < now();
    }

    public function getIsOngoingAttribute()
    {
        return $this->start_date <= now() && $this->end_date >= now();
    }

    public function getIsRegistrationOpenAttribute()
    {
        if ($this->registration_type === 'none') return false;
        
        $now = now();
        
        // Check if registration has started
        if ($this->registration_start && $this->registration_start > $now) {
            return false;
        }
        
        // Check if registration deadline has passed
        if ($this->registration_deadline && $this->registration_deadline < $now) {
            return false;
        }
        
        // Check if event is full
        if ($this->max_attendees && $this->current_attendees >= $this->max_attendees) {
            return false;
        }
        
        return true;
    }

    public function getAvailableSpotsAttribute()
    {
        if (!$this->max_attendees) return null;
        
        return max(0, $this->max_attendees - $this->current_attendees);
    }

    public function getIsFullAttribute()
    {
        if (!$this->max_attendees) return false;
        
        return $this->current_attendees >= $this->max_attendees;
    }

    public function getFormattedFeeAttribute()
    {
        if (!$this->registration_fee) return 'Free';
        
        return 'MVR ' . number_format($this->registration_fee, 2);
    }

    public function getDurationAttribute()
    {
        $start = Carbon::parse($this->start_date);
        $end = Carbon::parse($this->end_date);
        
        if ($start->isSameDay($end)) {
            return $start->format('M j, Y') . ' (' . $start->format('g:i A') . ' - ' . $end->format('g:i A') . ')';
        }
        
        return $start->format('M j, Y') . ' - ' . $end->format('M j, Y');
    }

    public function getShortDurationAttribute()
    {
        $start = Carbon::parse($this->start_date);
        $end = Carbon::parse($this->end_date);
        
        if ($start->isSameDay($end)) {
            return $start->format('M j, Y');
        }
        
        return $start->format('M j') . ' - ' . $end->format('M j, Y');
    }

    public function getStatusBadgeColorAttribute()
    {
        return match($this->status) {
            'published' => 'green',
            'draft' => 'gray',
            'cancelled' => 'red',
            'completed' => 'blue',
            default => 'gray',
        };
    }

    public function getTypeIconAttribute()
    {
        return match($this->type) {
            'conference' => '🎤',
            'workshop' => '🔧',
            'seminar' => '📚',
            'competition' => '🏆',
            'celebration' => '🎉',
            'meeting' => '👥',
            default => '📅',
        };
    }

    // Methods
    public function canRegister()
    {
        return $this->is_registration_open && $this->is_public && $this->status === 'published';
    }

    public function updateAttendeeCount()
    {
        $this->current_attendees = $this->confirmedRegistrations()->count();
        $this->save();
    }

    public function getRegistrationStats()
    {
        $total = $this->registrations()->count();
        $confirmed = $this->confirmedRegistrations()->count();
        $pending = $this->pendingRegistrations()->count();
        $cancelled = $this->registrations()->where('status', 'cancelled')->count();
        
        return [
            'total' => $total,
            'confirmed' => $confirmed,
            'pending' => $pending,
            'cancelled' => $cancelled,
            'attendance_rate' => $total > 0 ? round(($confirmed / $total) * 100, 1) : 0,
        ];
    }

    public function getUpcomingEvents($limit = 5)
    {
        return static::published()
                    ->public()
                    ->upcoming()
                    ->orderBy('start_date')
                    ->limit($limit)
                    ->get();
    }

    public function getFeaturedEvents($limit = 3)
    {
        return static::published()
                    ->public()
                    ->featured()
                    ->upcoming()
                    ->orderBy('start_date')
                    ->limit($limit)
                    ->get();
    }
}