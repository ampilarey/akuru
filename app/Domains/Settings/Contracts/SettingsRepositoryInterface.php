<?php

namespace App\Domains\Settings\Contracts;

/**
 * SPEC §42 "Interface Binding and Replaceability" lists ten interfaces as
 * required, and this was one of the two with no implementation:
 *
 *   > Bind key services to interfaces in the Laravel service container so
 *   > implementations are swappable.
 *   >
 *   > Required service interfaces include: … **Settings repository/interface**
 *
 * Nine of the ten existed. Settings did not — and the absence had a visible
 * cost: **ten call sites across four other domains** (Academics, ExamsGrades,
 * Finance, HR) read `DB::table('settings')` directly, reaching into a table
 * the Settings domain owns.
 *
 * That is rule 3's boundary in spirit if not in letter. It is not a
 * `Models\*` import, so the domain-boundary test never saw it, but the effect
 * is the same: five domains know the settings table's name, its column names
 * and its storage shape, so none of them could survive that shape changing —
 * which is exactly the replaceability §42 is asking for.
 *
 * The interface is deliberately narrow. Settings are key/value with typed
 * reads; anything richer belongs in the Settings domain's own Actions rather
 * than in a contract five domains depend on.
 */
interface SettingsRepositoryInterface
{
    /**
     * One setting, or the default when the key is absent or empty.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Several at once, keyed by setting key, defaults filled in.
     *
     * The bulk read exists because every caller found so far wants a handful
     * of related keys and a per-key round trip would be the obvious way to
     * make this slower than what it replaces.
     *
     * @param  array<string, mixed>  $keysWithDefaults
     * @return array<string, mixed>
     */
    public function many(array $keysWithDefaults): array;

    public function getString(string $key, string $default = ''): string;

    public function getInt(string $key, int $default = 0): int;

    public function getBool(string $key, bool $default = false): bool;
}
