<?php

namespace App\Domains\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group', 'label'];

    /**
     * The container key under which the table is remembered for the rest of
     * the request. `get()` used to run `select * from settings` on every
     * call — twelve times on the Library settings screen, six on the
     * Bookstore office (ADMIN_PANEL.md §7 P4, STATUS §5nn). The container is
     * the right scope: it lives for one request and one test, and a write
     * forgets it, so a value saved is the value read.
     */
    private const MEMO = 'settings.memo';

    /** Get a setting value by key, with optional default. */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::memo();

        if (! $all->has($key)) {
            return $default;
        }

        $setting = $all->get($key);

        return match ($setting->type) {
            'json' => json_decode($setting->value, true),
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            default => $setting->value,
        };
    }

    /** Get all settings keyed by key. */
    public static function allKeyed(): Collection
    {
        return static::memo()->map(fn (self $setting) => $setting->value);
    }

    /** Set or update a setting value. */
    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? json_encode($value) : (string) $value]
        );
        static::forgetMemo();
    }

    /** Forget what this request has read, so the next read sees a write. */
    public static function forgetMemo(): void
    {
        app()->forgetInstance(self::MEMO);
    }

    /** @return Collection<string, self> */
    private static function memo(): Collection
    {
        if (! app()->bound(self::MEMO)) {
            app()->instance(self::MEMO, static::all()->keyBy('key'));
        }

        return app(self::MEMO);
    }
}
