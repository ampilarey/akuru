<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class DashboardAnalytics extends Model
{
    protected $fillable = [
        'user_id',
        'metric_type',
        'metric_name',
        'metric_value',
        'metadata',
        'recorded_date',
    ];

    protected $casts = [
        'metadata' => 'array',
        'recorded_date' => 'date',
        'metric_value' => 'decimal:4',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scope for specific metric types
    public function scopeMetricType($query, string $type)
    {
        return $query->where('metric_type', $type);
    }

    // Scope for date range
    public function scopeDateRange($query, Carbon $start, Carbon $end)
    {
        return $query->whereBetween('recorded_date', [$start, $end]);
    }

    // Scope for recent data
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('recorded_date', '>=', now()->subDays($days));
    }

    // Get metrics for a specific user
    public static function getUserMetrics(int $userId, string $metricType = null, int $days = 30)
    {
        $query = static::where('user_id', $userId)
                      ->recent($days);
        
        if ($metricType) {
            $query->metricType($metricType);
        }
        
        return $query->get();
    }

    // Record a metric
    public static function recordMetric(int $userId, string $metricType, string $metricName, float $value = 1, array $metadata = [])
    {
        return static::updateOrCreate(
            [
                'user_id' => $userId,
                'metric_type' => $metricType,
                'recorded_date' => now()->toDateString(),
            ],
            [
                'metric_name' => $metricName,
                'metric_value' => $value,
                'metadata' => $metadata,
            ]
        );
    }

    // Get dashboard summary for a user
    public static function getDashboardSummary(int $userId, int $days = 30)
    {
        $metrics = static::getUserMetrics($userId, null, $days);
        
        $summary = [];
        foreach ($metrics->groupBy('metric_type') as $type => $typeMetrics) {
            $summary[$type] = [
                'total' => $typeMetrics->sum('metric_value'),
                'average' => $typeMetrics->avg('metric_value'),
                'count' => $typeMetrics->count(),
                'latest' => $typeMetrics->sortByDesc('recorded_date')->first(),
            ];
        }
        
        return $summary;
    }
}