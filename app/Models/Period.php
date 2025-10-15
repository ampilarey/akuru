<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Period extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'name',
        'name_arabic',
        'name_dhivehi',
        'start_time',
        'end_time',
        'order',
        'is_break',
        'is_active',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'is_break' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the school this period belongs to
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the timetables for this period
     */
    public function timetables()
    {
        return $this->hasMany(Timetable::class);
    }

    /**
     * Scope for active periods
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for non-break periods
     */
    public function scopeNotBreak($query)
    {
        return $query->where('is_break', false);
    }

    /**
     * Scope for break periods
     */
    public function scopeBreak($query)
    {
        return $query->where('is_break', true);
    }

    /**
     * Scope ordered by order field
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /**
     * Get the duration of the period in minutes
     */
    public function getDurationAttribute()
    {
        $start = \Carbon\Carbon::parse($this->start_time);
        $end = \Carbon\Carbon::parse($this->end_time);
        return $start->diffInMinutes($end);
    }

    /**
     * Get the formatted time range
     */
    public function getTimeRangeAttribute()
    {
        return $this->start_time->format('H:i') . ' - ' . $this->end_time->format('H:i');
    }
}