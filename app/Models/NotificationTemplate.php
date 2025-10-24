<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationTemplate extends Model
{
    protected $fillable = [
        'name',
        'type',
        'category',
        'subject',
        'body',
        'variables',
        'is_active',
        'is_system',
        'language',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    // Scope for active templates
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
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

    // Scope for specific language
    public function scopeLanguage($query, string $language)
    {
        return $query->where('language', $language);
    }

    // Get template by name and type
    public static function getTemplate(string $name, string $type = 'email', string $language = 'en')
    {
        return static::where('name', $name)
                    ->type($type)
                    ->language($language)
                    ->active()
                    ->first();
    }

    // Process template with variables
    public function processTemplate(array $variables = [])
    {
        $subject = $this->subject;
        $body = $this->body;

        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $subject = str_replace($placeholder, $value, $subject);
            $body = str_replace($placeholder, $value, $body);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    // Validate template variables
    public function validateVariables(array $variables)
    {
        $requiredVariables = $this->variables ?? [];
        $missingVariables = [];

        foreach ($requiredVariables as $variable) {
            if (!isset($variables[$variable])) {
                $missingVariables[] = $variable;
            }
        }

        return empty($missingVariables) ? true : $missingVariables;
    }
}