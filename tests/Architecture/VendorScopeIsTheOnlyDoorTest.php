<?php

use App\Domains\Bookshop\DTOs\VendorScope;

/**
 * BOOKSHOP_PLAN §10 "Security" (audit finding 12): vendors reach only their
 * own vendor's rows, through one definition — `VendorScope`, resolved from
 * the membership row by `ResolveVendorScopeAction`. A design intention with
 * nothing enforcing it is how the announcements route ended up open to
 * pupils (WriteRoutesAreGuardedTest's story), so this pins it:
 *
 *   1. every public method of a vendor-portal Action takes a `VendorScope`
 *      as its first argument — there is no other way in;
 *   2. every such Action that queries the database names the scope's
 *      vendor id, so a query cannot quietly forget the filter;
 *   3. the portal's controllers import no model at all and open every
 *      public method with `authorizeVendor()`.
 *
 * Filesystem and reflection only. Behaviour (vendor A cannot read or edit
 * vendor B's product) is pinned by `VendorPortalTest`.
 */
function vendorPortalActionClasses(): array
{
    $classes = [];
    foreach (glob(app_path('Domains/Bookshop/Actions/Vendor/*.php')) ?: [] as $file) {
        $classes[$file] = 'App\\Domains\\Bookshop\\Actions\\Vendor\\'.basename($file, '.php');
    }

    return $classes;
}

it('lets a vendor-portal Action in only through a VendorScope', function () {
    $classes = vendorPortalActionClasses();
    expect($classes)->not->toBeEmpty();

    $offenders = [];
    foreach ($classes as $class) {
        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $class || $method->isConstructor()) {
                continue;
            }
            $first = $method->getParameters()[0] ?? null;
            $type = $first?->getType();
            if (! $type instanceof ReflectionNamedType || $type->getName() !== VendorScope::class) {
                $offenders[] = $class.'::'.$method->getName();
            }
        }
    }

    expect($offenders)->toBeEmpty(
        "These vendor-portal Action methods do not take a VendorScope first:\n  ".implode("\n  ", $offenders)
    );
});

it('filters every vendor-portal query by the scope\'s vendor', function () {
    $offenders = [];
    foreach (vendorPortalActionClasses() as $file => $class) {
        $source = (string) file_get_contents($file);
        if (str_contains($source, '::query()') && ! str_contains($source, '$scope->vendorId')) {
            $offenders[] = $class;
        }
    }

    expect($offenders)->toBeEmpty(
        "These vendor-portal Actions query without naming \$scope->vendorId:\n  ".implode("\n  ", $offenders)
    );
});

it('keeps models out of the portal controllers and opens every method with the gate', function () {
    $controllers = [
        App\Domains\Bookshop\Http\Controllers\VendorPortalController::class,
        App\Domains\Bookshop\Http\Controllers\VendorProductController::class,
    ];

    $offenders = [];
    foreach ($controllers as $class) {
        $reflection = new ReflectionClass($class);
        $source = (string) file_get_contents($reflection->getFileName());
        if (preg_match('/^use App\\\\Domains\\\\[A-Za-z]+\\\\Models\\\\/m', $source)) {
            $offenders[] = $class.' imports a model';
        }

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $class) {
                continue;
            }
            $body = implode('', array_slice(
                file($reflection->getFileName()),
                $method->getStartLine() - 1,
                $method->getEndLine() - $method->getStartLine() + 1,
            ));
            if (! str_contains($body, '$this->authorizeVendor(')) {
                $offenders[] = $class.'::'.$method->getName().' does not call authorizeVendor()';
            }
        }
    }

    expect($offenders)->toBeEmpty(implode("\n", $offenders));
});
