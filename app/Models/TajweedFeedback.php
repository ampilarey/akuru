<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TajweedFeedback extends Model
{
    use HasFactory;

    protected $fillable = [
        'recitation_practice_id',
        'rule_name',
        'rule_name_arabic',
        'comment',
        'comment_arabic',
        'comment_dhivehi',
        'severity',
        'ayah_number',
        'word_position',
    ];

    /**
     * Get the recitation practice this feedback belongs to
     */
    public function recitationPractice()
    {
        return $this->belongsTo(RecitationPractice::class);
    }

    /**
     * Scope for different severity levels
     */
    public function scopeInfo($query)
    {
        return $query->where('severity', 'info');
    }

    public function scopeWarning($query)
    {
        return $query->where('severity', 'warning');
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    /**
     * Get the severity color for UI
     */
    public function getSeverityColorAttribute()
    {
        return match($this->severity) {
            'info' => 'blue',
            'warning' => 'yellow',
            'critical' => 'red',
            default => 'gray',
        };
    }

    /**
     * Get the severity icon for UI
     */
    public function getSeverityIconAttribute()
    {
        return match($this->severity) {
            'info' => 'info-circle',
            'warning' => 'exclamation-triangle',
            'critical' => 'times-circle',
            default => 'question-circle',
        };
    }
}