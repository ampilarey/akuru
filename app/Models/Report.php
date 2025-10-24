<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Report extends Model
{
    protected $fillable = [
        'name',
        'type',
        'category',
        'description',
        'parameters',
        'data',
        'status',
        'format',
        'file_path',
        'file_size',
        'created_by',
        'generated_at',
        'expires_at',
    ];

    protected $casts = [
        'parameters' => 'array',
        'data' => 'array',
        'generated_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scope for specific type
    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // Scope for specific category
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    // Scope for specific status
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    // Scope for user's reports
    public function scopeForUser($query, int $userId)
    {
        return $query->where('created_by', $userId);
    }

    // Scope for expired reports
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    // Mark as generating
    public function markAsGenerating()
    {
        $this->update(['status' => 'generating']);
    }

    // Mark as completed
    public function markAsCompleted(string $filePath = null, int $fileSize = null)
    {
        $this->update([
            'status' => 'completed',
            'generated_at' => now(),
            'file_path' => $filePath,
            'file_size' => $fileSize,
        ]);
    }

    // Mark as failed
    public function markAsFailed()
    {
        $this->update(['status' => 'failed']);
    }

    // Check if report is expired
    public function isExpired()
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    // Get file size in human readable format
    public function getFileSizeHumanAttribute()
    {
        if (!$this->file_size) {
            return null;
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    // Get report age
    public function getAgeAttribute()
    {
        return $this->created_at->diffForHumans();
    }

    // Create a new report
    public static function createReport(
        int $userId,
        string $name,
        string $type,
        string $category,
        array $parameters = [],
        string $format = 'json',
        string $description = null
    ) {
        return static::create([
            'name' => $name,
            'type' => $type,
            'category' => $category,
            'description' => $description,
            'parameters' => $parameters,
            'format' => $format,
            'created_by' => $userId,
            'expires_at' => now()->addDays(30), // Default 30 days expiry
        ]);
    }

    // Get available report types
    public static function getAvailableTypes()
    {
        return [
            'user_analytics' => 'User Analytics',
            'course_performance' => 'Course Performance',
            'financial' => 'Financial Reports',
            'system_health' => 'System Health',
            'academic_progress' => 'Academic Progress',
            'attendance' => 'Attendance Reports',
            'enrollment' => 'Enrollment Reports',
            'notification_analytics' => 'Notification Analytics',
        ];
    }

    // Get available categories
    public static function getAvailableCategories()
    {
        return [
            'analytics' => 'Analytics',
            'performance' => 'Performance',
            'financial' => 'Financial',
            'academic' => 'Academic',
            'system' => 'System',
            'security' => 'Security',
        ];
    }

    // Get available formats
    public static function getAvailableFormats()
    {
        return [
            'json' => 'JSON',
            'csv' => 'CSV',
            'pdf' => 'PDF',
            'excel' => 'Excel',
        ];
    }

    // Clean up expired reports
    public static function cleanupExpired()
    {
        $expiredReports = static::expired()->get();
        
        foreach ($expiredReports as $report) {
            // Delete file if exists
            if ($report->file_path && file_exists($report->file_path)) {
                unlink($report->file_path);
            }
            
            // Delete report record
            $report->delete();
        }
        
        return $expiredReports->count();
    }
}