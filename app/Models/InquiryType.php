<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InquiryType extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'email_to',
        'auto_response_template',
        'requires_phone',
        'requires_subject',
        'custom_fields',
        'is_active',
        'sort_order',
        'response_time_hours',
        'meta',
    ];

    protected $casts = [
        'custom_fields' => 'array',
        'requires_phone' => 'boolean',
        'requires_subject' => 'boolean',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    // Relationships
    public function inquiries(): HasMany
    {
        return $this->hasMany(ContactInquiry::class);
    }

    public function recentInquiries(): HasMany
    {
        return $this->hasMany(ContactInquiry::class)->recent();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // Accessors
    public function getSlugAttribute()
    {
        return \Str::slug($this->name);
    }

    public function getInquiryCountAttribute()
    {
        return $this->inquiries()->count();
    }

    public function getRecentInquiryCountAttribute()
    {
        return $this->inquiries()->where('created_at', '>=', now()->subDays(30))->count();
    }

    public function getAverageResponseTimeAttribute()
    {
        $respondedInquiries = $this->inquiries()
                                 ->whereNotNull('responded_at')
                                 ->get();
        
        if ($respondedInquiries->isEmpty()) {
            return null;
        }
        
        $totalHours = $respondedInquiries->sum(function($inquiry) {
            return $inquiry->created_at->diffInHours($inquiry->responded_at);
        });
        
        return round($totalHours / $respondedInquiries->count(), 1);
    }

    public function getCustomFieldsArrayAttribute()
    {
        if (!$this->custom_fields) {
            return [];
        }
        
        return is_array($this->custom_fields) ? $this->custom_fields : json_decode($this->custom_fields, true);
    }

    // Methods
    public function getActiveTypes()
    {
        return static::active()
                    ->ordered()
                    ->get();
    }

    public function getInquiryStats()
    {
        return [
            'total' => $this->inquiries()->count(),
            'new' => $this->inquiries()->new()->count(),
            'in_progress' => $this->inquiries()->inProgress()->count(),
            'resolved' => $this->inquiries()->resolved()->count(),
            'closed' => $this->inquiries()->closed()->count(),
            'recent' => $this->recent_inquiry_count,
            'average_response_time' => $this->average_response_time,
        ];
    }

    public function getFormFields()
    {
        $fields = [
            'name' => [
                'type' => 'text',
                'label' => 'Full Name',
                'required' => true,
                'placeholder' => 'Enter your full name',
            ],
            'email' => [
                'type' => 'email',
                'label' => 'Email Address',
                'required' => true,
                'placeholder' => 'Enter your email address',
            ],
        ];

        if ($this->requires_phone) {
            $fields['phone'] = [
                'type' => 'tel',
                'label' => 'Phone Number',
                'required' => true,
                'placeholder' => 'Enter your phone number',
            ];
        }

        if ($this->requires_subject) {
            $fields['subject'] = [
                'type' => 'text',
                'label' => 'Subject',
                'required' => true,
                'placeholder' => 'Enter a brief subject',
            ];
        }

        $fields['message'] = [
            'type' => 'textarea',
            'label' => 'Message',
            'required' => true,
            'placeholder' => 'Enter your message',
            'rows' => 5,
        ];

        // Add custom fields
        if ($this->custom_fields) {
            foreach ($this->custom_fields as $field) {
                $fields[$field['name']] = [
                    'type' => $field['type'] ?? 'text',
                    'label' => $field['label'],
                    'required' => $field['required'] ?? false,
                    'placeholder' => $field['placeholder'] ?? '',
                    'options' => $field['options'] ?? null,
                ];
            }
        }

        return $fields;
    }

    public function getAutoResponseMessage($inquiry = null)
    {
        if (!$this->auto_response_template) {
            return null;
        }

        $message = $this->auto_response_template;
        
        if ($inquiry) {
            $message = str_replace('{{name}}', $inquiry->name, $message);
            $message = str_replace('{{email}}', $inquiry->email, $message);
            $message = str_replace('{{subject}}', $inquiry->subject, $message);
            $message = str_replace('{{inquiry_type}}', $this->name, $message);
        }

        return $message;
    }
}