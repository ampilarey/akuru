<?php

/**
 * SPEC §41 "Domain Boundary Enforcement" names four things the architecture
 * suite must fail CI on:
 *
 *   > The architecture test must fail CI if:
 *   >  - One domain imports another domain's internal Eloquent models.
 *   >  - Controllers contain business logic that should be in actions/services.
 *   >  - A domain uses another domain's internal classes instead of public
 *   >    actions/contracts/events.
 *   >  - **External SDK classes are used directly inside domain business logic
 *   >    instead of wrapped interfaces.**
 *
 * Three were enforced. `BaselineArchitectureTest` rule 1 covers the first,
 * rule 2 the third, rule 4 (the DB facade in controllers) stands in for the
 * second. **The fourth had no test at all.**
 *
 * The code complied anyway, which is the point worth being careful about: at
 * the time this was written every SDK call and every outbound HTTP call
 * already sat in a `Services/` wrapper implementing a bound interface —
 * `WebPImageService` (ImageProcessorInterface), `BigBlueButtonVideoConferencing`
 * (VideoConferencingInterface), `BmlPaymentProvider` (PaymentProviderInterface),
 * `SmsGatewayService` (SmsSenderInterface). Nothing held it there. That is the
 * recurring shape of this codebase's defects — correct code, unenforced — and
 * an architecture rule nobody checks is a comment.
 *
 * **The SDK list is derived, not typed out.** Package namespaces come from
 * `vendor/composer/installed.json`, filtered to the root `composer.json`
 * requires, so `composer require some/payment-sdk` is covered the day it lands
 * rather than the day somebody remembers this file. Hand-maintained
 * inventories in this repo have drifted every single time.
 *
 * **The `Http` facade is included deliberately.** Most of this project's
 * integrations — BML, Dhiraagu, BigBlueButton — have no SDK package at all;
 * they are raw HTTP against a vendor API. A rule that watched only composer
 * packages would have watched nothing that actually matters here.
 *
 * **Excluded, with reasons**, because they are framework and infrastructure
 * rather than swappable integrations:
 *
 *  - `Illuminate\*` (laravel/framework) — the framework itself.
 *  - `Inertia\*` — the view layer; `Inertia::render` is how every controller
 *    returns a page, and there is no "other Inertia" to swap to.
 *  - `Spatie\Permission\*` — authorization infrastructure. `HasRoles` belongs
 *    on the User model and `role:` belongs on routes; neither is an
 *    integration hidden behind a contract.
 *  - `Laravel\Tinker\*`, `Laravel\Breeze\*` — dev tooling and scaffolding.
 *
 * The allowed home is a `Services/` directory under `app/Domains/*` or
 * `app/Support/` — the wrapper layer, where CLAUDE.md rule 4 puts payments,
 * SMS, storage, video and AI. Everything else (Actions, Listeners, Models,
 * Http, Console, Jobs) is business logic and must go through the contract.
 *
 * Filesystem and composer metadata only: no database, no HTTP, no fixture.
 */
it('keeps third-party SDKs and outbound HTTP behind a wrapper service', function () {
    $baseline = require __DIR__.'/Baselines/sdk_calls_outside_wrappers.php';

    // Framework and infrastructure, not swappable integrations. See the
    // docblock above for why each one is here.
    $notIntegrations = [
        'laravel/framework',
        'inertiajs/inertia-laravel',
        'spatie/laravel-permission',
        'laravel/tinker',
        'laravel/breeze',
        'mcamara/laravel-localization',
    ];

    $namespaces = sdkNamespacesFromComposer($notIntegrations);

    // Every integration this project actually has is raw HTTP against a vendor
    // API, so the facade matters more here than any package namespace.
    $patterns = ['Http::' => 'Http::'];
    foreach ($namespaces as $namespace) {
        $patterns[$namespace] = $namespace;
    }

    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('app'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $path = str_replace(base_path().'/', '', $file->getPathname());

        // The wrapper layer: this is where an SDK is supposed to live.
        if (str_contains($path, '/Services/')) {
            continue;
        }

        $source = stripPhpComments((string) file_get_contents($file->getPathname()));

        $hits = [];
        foreach ($patterns as $label => $needle) {
            if (str_contains($source, $needle)) {
                $hits[] = $label;
            }
        }

        if ($hits !== []) {
            $offenders[$path] = implode(', ', $hits);
        }
    }

    ksort($offenders);

    $new = array_values(array_diff(array_keys($offenders), array_keys($baseline)));

    expect($new)->toBeEmpty(
        "These files reach a third-party integration from outside a wrapper service:\n  "
        .implode("\n  ", array_map(fn ($p) => $p.' ('.$offenders[$p].')', $new))
        ."\n\nSPEC §41: \"External SDK classes are used directly inside domain business "
        .'logic instead of wrapped interfaces" must fail CI. CLAUDE.md rule 4 says the '
        .'same thing: payments, SMS, storage, video and AI always sit behind a '
        ."domain-owned interface with a container binding.\n\nPut the call in the owning "
        .'domain\'s Services/ wrapper and resolve its interface here — or, if it genuinely '
        .'belongs where it is, add it to tests/Architecture/Baselines/'
        .'sdk_calls_outside_wrappers.php with the reason.'
    );

    // The baseline may only shrink. A stale entry is a note saying a file
    // still reaches past the wrapper when it no longer does, and the next
    // reader believes the note.
    $stale = array_values(array_diff(array_keys($baseline), array_keys($offenders)));

    expect($stale)->toBeEmpty(
        "These baseline entries no longer reach a third-party integration and should be deleted:\n  "
        .implode("\n  ", $stale)
        ."\n\nThe baseline may only shrink."
    );
});

/**
 * Namespaces of the third-party packages this project requires directly.
 *
 * Read from composer's own metadata rather than typed into a list, so a newly
 * required SDK is covered without anyone remembering to come back here.
 *
 * @param  list<string>  $excluded
 * @return list<string>
 */
function sdkNamespacesFromComposer(array $excluded): array
{
    $required = json_decode((string) file_get_contents(base_path('composer.json')), true)['require'] ?? [];
    $installed = json_decode((string) file_get_contents(base_path('vendor/composer/installed.json')), true);
    $packages = $installed['packages'] ?? $installed;

    $namespaces = [];

    foreach ($packages as $package) {
        $name = $package['name'] ?? '';
        if (! array_key_exists($name, $required) || in_array($name, $excluded, true)) {
            continue;
        }

        foreach (array_keys(($package['autoload'] ?? [])['psr-4'] ?? []) as $prefix) {
            // As it appears in an import: `use GuzzleHttp\Psr7\Request;`
            $namespaces[] = rtrim((string) $prefix, '\\').'\\';
        }
    }

    sort($namespaces);

    return array_values(array_unique($namespaces));
}
