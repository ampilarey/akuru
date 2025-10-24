<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SystemMetric extends Model
{
    protected $fillable = [
        'metric_name',
        'metric_category',
        'metric_value',
        'metric_unit',
        'metadata',
        'recorded_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'recorded_at' => 'datetime',
        'metric_value' => 'decimal:4',
    ];

    // Scope for specific metric name
    public function scopeMetricName($query, string $name)
    {
        return $query->where('metric_name', $name);
    }

    // Scope for specific category
    public function scopeCategory($query, string $category)
    {
        return $query->where('metric_category', $category);
    }

    // Scope for date range
    public function scopeDateRange($query, Carbon $start, Carbon $end)
    {
        return $query->whereBetween('recorded_at', [$start, $end]);
    }

    // Scope for recent metrics
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('recorded_at', '>=', now()->subHours($hours));
    }

    // Record a metric
    public static function recordMetric(
        string $name,
        string $category,
        float $value,
        string $unit = null,
        array $metadata = []
    ) {
        return static::create([
            'metric_name' => $name,
            'metric_category' => $category,
            'metric_value' => $value,
            'metric_unit' => $unit,
            'metadata' => $metadata,
            'recorded_at' => now(),
        ]);
    }

    // Get metric statistics
    public static function getMetricStats(string $name, int $hours = 24)
    {
        $metrics = static::metricName($name)->recent($hours)->get();

        if ($metrics->isEmpty()) {
            return null;
        }

        return [
            'current' => $metrics->last()->metric_value,
            'average' => $metrics->avg('metric_value'),
            'min' => $metrics->min('metric_value'),
            'max' => $metrics->max('metric_value'),
            'trend' => static::calculateTrend($metrics),
            'unit' => $metrics->first()->metric_unit,
        ];
    }

    // Calculate trend (positive, negative, or stable)
    private static function calculateTrend($metrics)
    {
        if ($metrics->count() < 2) {
            return 'stable';
        }

        $firstHalf = $metrics->take(ceil($metrics->count() / 2));
        $secondHalf = $metrics->skip(floor($metrics->count() / 2));

        $firstAvg = $firstHalf->avg('metric_value');
        $secondAvg = $secondHalf->avg('metric_value');

        $change = (($secondAvg - $firstAvg) / $firstAvg) * 100;

        if (abs($change) < 5) {
            return 'stable';
        }

        return $change > 0 ? 'increasing' : 'decreasing';
    }

    // Get system health overview
    public static function getSystemHealth()
    {
        $metrics = [
            'cpu_usage' => static::getMetricStats('cpu_usage', 1),
            'memory_usage' => static::getMetricStats('memory_usage', 1),
            'disk_usage' => static::getMetricStats('disk_usage', 1),
            'active_users' => static::getMetricStats('active_users', 1),
            'response_time' => static::getMetricStats('response_time', 1),
        ];

        // Determine overall health status
        $healthStatus = 'healthy';
        foreach ($metrics as $metric) {
            if ($metric && $metric['current'] > 90) {
                $healthStatus = 'critical';
                break;
            } elseif ($metric && $metric['current'] > 80) {
                $healthStatus = 'warning';
            }
        }

        return [
            'status' => $healthStatus,
            'metrics' => $metrics,
            'last_updated' => static::latest('recorded_at')->first()?->recorded_at,
        ];
    }

    // Get performance metrics
    public static function getPerformanceMetrics(int $days = 7)
    {
        $start = now()->subDays($days);
        $end = now();

        return [
            'response_time' => static::metricName('response_time')
                ->dateRange($start, $end)
                ->avg('metric_value'),
            'throughput' => static::metricName('requests_per_second')
                ->dateRange($start, $end)
                ->avg('metric_value'),
            'error_rate' => static::metricName('error_rate')
                ->dateRange($start, $end)
                ->avg('metric_value'),
            'uptime' => static::calculateUptime($start, $end),
        ];
    }

    // Calculate uptime percentage
    private static function calculateUptime(Carbon $start, Carbon $end)
    {
        $totalMinutes = $start->diffInMinutes($end);
        $downMinutes = static::metricName('system_down')
            ->dateRange($start, $end)
            ->sum('metric_value');

        return $totalMinutes > 0 ? (($totalMinutes - $downMinutes) / $totalMinutes) * 100 : 100;
    }

    // Get usage analytics
    public static function getUsageAnalytics(int $days = 30)
    {
        $start = now()->subDays($days);
        $end = now();

        return [
            'daily_active_users' => static::metricName('daily_active_users')
                ->dateRange($start, $end)
                ->orderBy('recorded_at')
                ->pluck('metric_value', 'recorded_at'),
            'page_views' => static::metricName('page_views')
                ->dateRange($start, $end)
                ->orderBy('recorded_at')
                ->pluck('metric_value', 'recorded_at'),
            'api_calls' => static::metricName('api_calls')
                ->dateRange($start, $end)
                ->orderBy('recorded_at')
                ->pluck('metric_value', 'recorded_at'),
        ];
    }
}