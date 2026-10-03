<?php

namespace App\Domains\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Instructor extends Model
{
    protected $fillable = [
        'user_id',
        'name', 'slug', 'bio', 'photo', 'email', 'phone',
        'qualification', 'specialization', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'user_id' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(config('domain-models.course'));
    }

    /**
     * The staff login this profile belongs to (C16 slice N6): what lets the
     * review queue say which courses are "mine".
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('domain-models.user'));
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
