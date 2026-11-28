<?php

namespace App\Domains\Settings\Support;

use App\Domains\Settings\Actions\SetSettingAction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * ST4 (admin audit 2026-11-27, STATUS §5uw): the office's own values for
 * the business settings in `config/office-knobs.php`.
 *
 * `apply()` runs once at boot and lays each stored value over its config
 * key, so every existing reader of `config('lending.max_days')` sees what
 * the office set and none has to change. What the deploy carried is kept
 * as the default. A stored value outside its knob's bounds is ignored, not
 * trusted: the table can be edited by hand.
 */
final class OfficeKnobs
{
    private const CACHE = 'office_knobs.v1';

    /** @var array<string, mixed> the deploy's own values, by config key, as they were before the office's */
    private static array $defaults = [];

    public static function apply(): void
    {
        try {
            $stored = Cache::remember(self::CACHE, 300, fn () => DB::table('settings')->where('key', 'like', 'knob.%')->pluck('value', 'key')->all());
        } catch (Throwable) {
            // No database yet (a fresh install, a build step): the deploy's values stand.
            return;
        }
        foreach (self::all() as $product => $knobs) {
            foreach ($knobs as $name => $knob) {
                self::$defaults[$knob['config']] ??= config($knob['config']);
                $key = self::key($product, $name);
                if (array_key_exists($key, $stored) && ($value = self::read($knob, $stored[$key])) !== null) {
                    config([$knob['config'] => $value]);
                }
            }
        }
    }

    /** @return list<string> the products that have a page */
    public static function products(): array
    {
        return array_keys((array) config('office-knobs'));
    }

    /**
     * One product's settings, grouped, each with what is in force and the deploy's default.
     *
     * @return list<array{group: string, knobs: list<array{name: string, type: string, value: mixed, default: mixed, min: mixed, max: mixed}>}>
     */
    public static function groups(string $product): array
    {
        $out = [];
        foreach ((array) config("office-knobs.$product.groups") as $group => $knobs) {
            $rows = [];
            foreach ($knobs as $name => $knob) {
                $rows[] = [
                    'name' => $name, 'type' => $knob['type'],
                    'value' => config($knob['config']),
                    'default' => self::$defaults[$knob['config']] ?? config($knob['config']),
                    'min' => $knob['min'] ?? null, 'max' => $knob['max'] ?? null,
                ];
            }
            $out[] = ['group' => $group, 'knobs' => $rows];
        }

        return $out;
    }

    /**
     * Save one group's values. Each is checked against its knob's bounds
     * here, whoever sent it.
     *
     * @param  array<string, mixed>  $data
     */
    public static function save(string $product, string $group, array $data): void
    {
        $knobs = (array) config("office-knobs.$product.groups.$group");
        if ($knobs === []) {
            throw ValidationException::withMessages(['group' => __('knobs.error_unknown')]);
        }
        $next = [];
        $errors = [];
        foreach ($knobs as $name => $knob) {
            if ($knob['type'] !== 'bool' && ! array_key_exists($name, $data)) {
                continue;
            }
            $value = self::read($knob, $data[$name] ?? false);
            if ($value === null) {
                $errors[$name] = __('knobs.error_range', ['label' => __("knobs.{$product}_{$name}"), 'min' => $knob['min'] ?? '', 'max' => $knob['max'] ?? '']);

                continue;
            }
            $next[$name] = $value;
        }
        foreach ($knobs as $name => $knob) {
            // One knob may not pass another of its group: a default loan longer than the longest.
            $ceiling = $knob['at_most'] ?? null;
            if ($ceiling !== null && isset($next[$name]) && $next[$name] > ($next[$ceiling] ?? config($knobs[$ceiling]['config']))) {
                $errors[$name] = __('knobs.error_at_most', ['label' => __("knobs.{$product}_{$name}"), 'other' => __("knobs.{$product}_{$ceiling}")]);
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        foreach ($next as $name => $value) {
            $knob = $knobs[$name];
            app(SetSettingAction::class)->execute(self::key($product, $name), is_bool($value) ? ($value ? '1' : '0') : (string) $value, $knob['type'] === 'bool' ? 'boolean' : 'string', 'knobs', "$product: $name");
            self::$defaults[$knob['config']] ??= config($knob['config']);
            config([$knob['config'] => $value]);
        }
        Cache::forget(self::CACHE);
        // A queue worker booted before this save still holds the old values:
        // ask the workers to finish their job and start again.
        \Illuminate\Support\Facades\Artisan::call('queue:restart');
    }

    /** @return array<string, array<string, array<string, mixed>>> product => knob => definition */
    private static function all(): array
    {
        $out = [];
        foreach ((array) config('office-knobs') as $product => $definition) {
            foreach ((array) ($definition['groups'] ?? []) as $knobs) {
                foreach ($knobs as $name => $knob) {
                    $out[$product][$name] = $knob;
                }
            }
        }

        return $out;
    }

    private static function key(string $product, string $name): string
    {
        return "knob.$product.$name";
    }

    /** A raw value as its knob's type, or null when it is not one or is out of bounds. */
    private static function read(array $knob, mixed $raw): int|float|bool|string|null
    {
        switch ($knob['type']) {
            case 'bool':
                return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
            case 'text':
                $text = trim((string) $raw);

                return mb_strlen($text) <= (int) ($knob['max'] ?? 255) ? $text : null;
            case 'int':
            case 'decimal':
                if (! is_numeric($raw) || ($knob['type'] === 'int' && (string) (int) $raw !== trim((string) $raw))) {
                    return null;
                }
                $number = $knob['type'] === 'int' ? (int) $raw : round((float) $raw, 2);
                if ((isset($knob['min']) && $number < $knob['min']) || (isset($knob['max']) && $number > $knob['max'])) {
                    return null;
                }

                return $number;
        }

        return null;
    }
}
