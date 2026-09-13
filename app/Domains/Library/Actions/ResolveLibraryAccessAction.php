<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryAccessGrant;
use App\Domains\Library\Models\LibraryItem;

/**
 * L3: the ONE access decision (LIBRARY_PLAN §6 + §35.4). free_public and
 * free_login answer from the access type alone; every other type answers
 * from an ACTIVE access grant — which only webhook confirmation, course
 * links, or an admin ever create (§43.4/§43.5).
 *
 * **Preview (§9.4)** is decided here too, and deliberately so. The columns
 * `preview_enabled` and `preview_pages` shipped with the L1 foundation
 * migration in August and **nothing had ever read them** — an admin could
 * turn a preview on and no reader would see one, which is the shape this
 * codebase keeps producing: a control that controls nothing. §37 lists "free
 * preview" in the MVP's public surface.
 *
 * `preview_pages` is returned as an allowance, **not** as `can_read`. That
 * separation is the whole safety of it: a previewer is never granted the item,
 * only a first-N-pages window that `PresentLibraryReaderAction` clamps
 * server-side. Anything that forgets the allowance therefore fails closed —
 * it shows nothing, rather than the whole book.
 *
 * Preview needs a signed-in reader. §9.2 requires a per-reader watermark and a
 * logged reading event on every delivered page, and neither means anything
 * without a person attached — an anonymous preview would be the one page in
 * the system delivered with nobody's name on it.
 */
class ResolveLibraryAccessAction
{
    /**
     * @return array{can_read: bool, requires_login: bool, locked: bool, preview_pages: int}
     */
    public function execute(LibraryItem $item, ?int $userId): array
    {
        $access = $item->access_type?->value;

        $canRead = match ($access) {
            'free_public' => true,
            'free_login' => $userId !== null,
            default => $userId !== null && LibraryAccessGrant::query()
                ->where('user_id', $userId)
                ->where('library_item_id', $item->id)
                ->where('status', 'active')
                ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
                ->exists(),
        };

        // Only ever an allowance for somebody who cannot already read it, and
        // only for a signed-in reader. `max(0, ...)` because a null or negative
        // `preview_pages` must mean "no preview", never "unbounded".
        $previewPages = 0;
        if (! $canRead && $userId !== null && $item->preview_enabled) {
            $previewPages = max(0, (int) $item->preview_pages);
        }

        return [
            'can_read' => $canRead,
            'requires_login' => $userId === null && $access !== 'free_public',
            'locked' => ! $canRead && $userId !== null && ! in_array($access, ['free_public', 'free_login'], true),
            'preview_pages' => $previewPages,
        ];
    }
}
