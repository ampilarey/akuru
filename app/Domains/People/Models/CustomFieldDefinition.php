<?php

namespace App\Domains\People\Models;

use App\Domains\People\Enums\CustomFieldEntityType;
use App\Domains\People\Enums\CustomFieldType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomFieldDefinition extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'entity_type',
        'key',
        'label_en',
        'label_dv',
        'label_ar',
        'field_type',
        'options',
        'required',
        'show_in_profile',
        'show_in_admission_form',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'entity_type' => CustomFieldEntityType::class,
            'field_type' => CustomFieldType::class,
            'options' => 'array',
            'required' => 'boolean',
            'show_in_profile' => 'boolean',
            'show_in_admission_form' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'definition_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeForEntity(Builder $query, CustomFieldEntityType|string $entityType): Builder
    {
        $value = $entityType instanceof CustomFieldEntityType ? $entityType->value : $entityType;

        return $query->where('entity_type', $value);
    }

    public function scopeForAdmissionForm(Builder $query): Builder
    {
        return $query->active()
            ->where('show_in_admission_form', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function scopeForProfile(Builder $query): Builder
    {
        return $query->active()
            ->where('show_in_profile', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function localizedLabel(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return match ($locale) {
            'dv' => $this->label_dv ?: $this->label_en,
            'ar' => $this->label_ar ?: $this->label_en,
            default => $this->label_en,
        } ?: $this->key;
    }

    /**
     * @return list<string>
     */
    public function optionValues(): array
    {
        return collect($this->options ?? [])
            ->map(fn ($option) => is_array($option) ? (string) ($option['value'] ?? '') : (string) $option)
            ->filter(fn (string $value) => $value !== '')
            ->values()
            ->all();
    }
}
