<?php

use Illuminate\Support\Facades\File;

it('morph-map aliases are unique, resolve to existing classes, and cover every domain model', function () {
    $map = config('morph-map');

    expect($map)->toBeArray()->not->toBeEmpty();

    $aliases = array_keys($map);
    expect($aliases)->toEqual(array_unique($aliases));

    $classes = array_values($map);
    expect($classes)->toEqual(array_unique($classes));

    foreach ($map as $alias => $class) {
        expect($alias)->toBeString()->not->toBeEmpty()
            ->and(class_exists($class))->toBeTrue("Alias [{$alias}] points to missing class [{$class}]");
    }

    // Reuse established domain-models aliases — do not mint parallel names.
    $domainModels = config('domain-models');
    foreach ($domainModels as $alias => $class) {
        expect($map)->toHaveKey($alias)
            ->and($map[$alias])->toBe($class);
    }

    // Notification classes are not Eloquent models — must not appear in the morph map.
    $notificationClasses = [
        \App\Domains\Notifications\Notifications\OtpEmailNotification::class,
        \App\Domains\Notifications\Notifications\NewAdmissionApplication::class,
        \App\Domains\Notifications\Notifications\NewContactMessage::class,
    ];
    foreach ($notificationClasses as $notificationClass) {
        expect(in_array($notificationClass, $classes, true))->toBeFalse(
            "Notification class [{$notificationClass}] must not be in config/morph-map.php"
        );
    }

    $domainModelClasses = collect(File::allFiles(base_path('app/Domains')))
        ->filter(fn ($file) => str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'Models'.DIRECTORY_SEPARATOR)
            && $file->getExtension() === 'php')
        ->map(function ($file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());

            // File is relative to app/Domains; rebuild FQCN.
            return 'App\\Domains\\'.str_replace(['/', '.php'], ['\\', ''], $relative);
        })
        ->sort()
        ->values()
        ->all();

    $mapped = $classes;
    sort($mapped);

    expect($mapped)->toEqual($domainModelClasses);
});

it('legacy morph rewrites cover every App\\Models basename and point at registered aliases', function () {
    $map = config('morph-map');
    $legacy = \App\Support\MorphMap::legacyMorphRewrites();

    expect($legacy)->not->toBeEmpty();

    foreach ($legacy as $old => $alias) {
        expect($old)->toStartWith('App\\Models\\')
            ->and($map)->toHaveKey($alias);
    }

    foreach ($map as $alias => $class) {
        $base = class_basename($class);
        expect($legacy)->toHaveKey('App\\Models\\'.$base)
            ->and($legacy['App\\Models\\'.$base])->toBe($alias);
    }
});

it('legacy notification rewrites are FQCN to FQCN and stay out of the morph map', function () {
    $rewrites = \App\Support\MorphMap::legacyNotificationRewrites();
    $map = config('morph-map');

    expect($rewrites)->toHaveCount(3);

    foreach ($rewrites as $old => $new) {
        expect($old)->toStartWith('App\\Notifications\\')
            ->and($new)->toStartWith('App\\Domains\\Notifications\\Notifications\\')
            ->and(class_exists($new))->toBeTrue()
            ->and(in_array($new, $map, true))->toBeFalse()
            ->and(in_array($old, $map, true))->toBeFalse();
    }
});
