<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class UserActivity extends Model
{
    protected $fillable = [
        'user_id',
        'activity_type',
        'activity_name',
        'description',
        'ip_address',
        'user_agent',
        'metadata',
        'performed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'performed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scope for specific activity types
    public function scopeActivityType($query, string $type)
    {
        return $query->where('activity_type', $type);
    }

    // Scope for recent activities
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('performed_at', '>=', now()->subDays($days));
    }

    // Scope for today's activities
    public function scopeToday($query)
    {
        return $query->whereDate('performed_at', today());
    }

    // Record an activity
    public static function recordActivity(
        int $userId,
        string $activityType,
        string $activityName,
        string $description = null,
        array $metadata = [],
        string $ipAddress = null,
        string $userAgent = null
    ) {
        return static::create([
            'user_id' => $userId,
            'activity_type' => $activityType,
            'activity_name' => $activityName,
            'description' => $description,
            'ip_address' => $ipAddress ?? request()->ip(),
            'user_agent' => $userAgent ?? request()->userAgent(),
            'metadata' => $metadata,
            'performed_at' => now(),
        ]);
    }

    // Get recent activities for a user
    public static function getUserRecentActivities(int $userId, int $limit = 10)
    {
        return static::where('user_id', $userId)
                    ->recent()
                    ->orderBy('performed_at', 'desc')
                    ->limit($limit)
                    ->get();
    }

    // Get activity summary for dashboard
    public static function getActivitySummary(int $userId, int $days = 7)
    {
        $activities = static::where('user_id', $userId)
                          ->where('performed_at', '>=', now()->subDays($days))
                          ->get();

        return [
            'total_activities' => $activities->count(),
            'activities_by_type' => $activities->groupBy('activity_type')->map->count(),
            'recent_activities' => static::getUserRecentActivities($userId, 5),
            'most_active_day' => $activities->groupBy(function ($activity) {
                return $activity->performed_at->format('Y-m-d');
            })->map->count()->sortDesc()->keys()->first(),
        ];
    }

    // Get login activities
    public static function getLoginActivities(int $userId, int $days = 30)
    {
        return static::where('user_id', $userId)
                    ->activityType('login')
                    ->where('performed_at', '>=', now()->subDays($days))
                    ->orderBy('performed_at', 'desc')
                    ->get();
    }
}