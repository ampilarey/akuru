<?php

namespace App\Domains\Settings\Services;

use App\Domains\Settings\Contracts\SettingsRepositoryInterface;
use App\Domains\Settings\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * The database-backed implementation of SPEC §42's "Settings
 * repository/interface".
 *
 * This is the only class outside the Settings domain's own Actions that knows
 * the table's name and shape. Ten call sites in Academics, ExamsGrades,
 * Finance and HR used to hold that knowledge themselves through
 * `DB::table('settings')`.
 *
 * `Setting::get()` already existed and handles the model's own casting, so the
 * single read goes through it rather than re-deriving the rules. The bulk read
 * uses one query, because every caller wants a handful of related keys at once
 * and a per-key round trip would make this slower than the direct reads it
 * replaces.
 */
class DatabaseSettingsRepository implements SettingsRepositoryInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }

    /**
     * @param  array<string, mixed>  $keysWithDefaults
     * @return array<string, mixed>
     */
    public function many(array $keysWithDefaults): array
    {
        if ($keysWithDefaults === []) {
            return [];
        }

        $stored = DB::table('settings')
            ->whereIn('key', array_keys($keysWithDefaults))
            ->pluck('value', 'key');

        $out = [];
        foreach ($keysWithDefaults as $key => $default) {
            // Faithful to what the ten call sites this replaces already did:
            // an absent key takes the default, and a stored empty string is
            // passed through as an empty string. Folding "empty means unset"
            // in here would be a quiet behaviour change across four domains,
            // which is not something a refactor gets to decide.
            //
            // The typed getters below are stricter, because they are new and
            // have no existing callers to surprise.
            $out[$key] = array_key_exists($key, $stored->all()) ? $stored[$key] : $default;
        }

        return $out;
    }

    public function getString(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key, null);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, null);

        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
