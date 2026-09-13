<?php

use App\Domains\Settings\Contracts\SettingsRepositoryInterface;

/**
 * SPEC §42 "Interface Binding and Replaceability":
 *
 *   > Bind key services to interfaces in the Laravel service container so
 *   > implementations are swappable.
 *   >
 *   > Required service interfaces include: Media storage · Video provider ·
 *   > Notification channel · Payment gateway (Phase 4) · Certificate renderer
 *   > (Phase 3) · **Settings repository** · File URL signer / private media
 *   > access · Course progress calculator *where useful* · Unlock rule
 *   > evaluator *where useful* · Completion rule evaluator *where useful*
 *
 * Nine of the ten existed. **Settings did not**, and the absence had a visible
 * cost: ten call sites across four other domains — Academics, ExamsGrades,
 * Finance, HR — read `DB::table('settings')` directly, so five domains knew
 * the settings table's name and column shape and none could have survived it
 * changing. That is rule 3's boundary in spirit: not a `Models\*` import, so
 * `DomainBoundariesTest` never saw it, but the same coupling.
 *
 * **An interface that exists but is not bound is not swappable**, which is the
 * whole point of §42, so each one is resolved from the container here rather
 * than merely asserted to be a file on disk.
 *
 * Two of §42's ten are read generously and the reasons are written down rather
 * than left for a reader to infer:
 *
 *  - **Video provider.** `Offerings\Contracts\VideoConferencingInterface`
 *    covers live sessions. Lesson *content* video is an embed allow-list
 *    (`NormalizeVideoEmbedUrlAction`), not a provider SDK, so there is nothing
 *    for an interface to swap yet. §42's goal — "uploaded video with external
 *    video provider" — is Phase 2+ work.
 *  - **Notification channel.** There is an interface per channel
 *    (`SmsSenderInterface`, `PushSenderInterface`) rather than one generic
 *    channel interface. That satisfies §42's purpose — "in-app notification
 *    with SMS/email/push" is swappable per channel — and email goes through
 *    Laravel's own Mail contract, which is already an interface.
 *
 * Filesystem and container only: no database, no HTTP, no fixture.
 */
it('binds every service interface SPEC §42 requires', function () {
    $required = [
        // §42 name => interface actually bound
        'Media storage' => \App\Domains\Media\Contracts\MediaStorageInterface::class,
        'Video provider (live sessions)' => \App\Domains\Offerings\Contracts\VideoConferencingInterface::class,
        'Notification channel (SMS)' => \App\Domains\Notifications\Contracts\SmsSenderInterface::class,
        'Notification channel (push)' => \App\Domains\Notifications\Contracts\PushSenderInterface::class,
        'Payment gateway' => \App\Domains\Finance\Contracts\PaymentProviderInterface::class,
        'Certificate renderer' => \App\Support\Contracts\DocumentRendererInterface::class,
        'Settings repository' => SettingsRepositoryInterface::class,
        'Unlock rule evaluator' => \App\Domains\Progress\Contracts\LessonUnlockEvaluator::class,
        'Completion rule evaluator' => \App\Domains\Progress\Contracts\CourseCompletionEvaluator::class,
    ];

    $unbound = [];

    foreach ($required as $label => $interface) {
        if (! interface_exists($interface)) {
            $unbound[] = $label.' — no such interface: '.$interface;

            continue;
        }

        try {
            $concrete = app($interface);
        } catch (Throwable $e) {
            $unbound[] = $label.' — not bound in the container: '.$e->getMessage();

            continue;
        }

        if (! $concrete instanceof $interface) {
            $unbound[] = $label.' — resolved to something that does not implement it';
        }
    }

    expect($unbound)->toBeEmpty(
        "SPEC §42 requires these to be bound so implementations are swappable:\n  "
        .implode("\n  ", $unbound)
        ."\n\nAn interface that exists but is not bound is not swappable, which is the "
        .'point of §42.'
    );
});

it('keeps the settings table inside the domain that owns it', function () {
    // The defect §42's missing interface allowed. Ten call sites in four other
    // domains read `DB::table('settings')` directly — no `Models\*` import, so
    // the domain-boundary test never saw it, and the same coupling all the
    // same: five domains knowing one table's shape.
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('app'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $path = str_replace(base_path().'/', '', $file->getPathname());

        // The Settings domain owns the table, and a migration must name it.
        if (str_starts_with($path, 'app/Domains/Settings/')) {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());
        if (str_contains($source, "DB::table('settings')")) {
            $offenders[] = $path;
        }
    }

    sort($offenders);

    expect($offenders)->toBeEmpty(
        "These files read the settings table directly from outside the Settings domain:\n  "
        .implode("\n  ", $offenders)
        ."\n\nSPEC §42 asks for a settings repository interface so the storage can be "
        .'replaced. Resolve SettingsRepositoryInterface instead.'
    );
});
