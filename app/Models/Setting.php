<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group', 'label'];

    /** Get a setting value by key, with optional default. */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::all()->keyBy('key');

        if (! $all->has($key)) {
            return $default;
        }

        $setting = $all->get($key);

        return match ($setting->type) {
            'json'    => json_decode($setting->value, true),
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            default   => $setting->value,
        };
    }

    /** Get all settings keyed by key. */
    public static function allKeyed(): \Illuminate\Support\Collection
    {
        return static::all()->pluck('value', 'key');
    }

    /** Set or update a setting value. */
    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? json_encode($value) : (string) $value]
        );
    }
}
