<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\Actions\Shop\ResolveStorefrontAction;
use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorPage;
use App\Domains\Bookshop\Models\VendorStorefront;
use App\Domains\Bookshop\Models\VendorStorefrontVersion;
use App\Domains\Bookshop\Support\Theme;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The draft goes live (BOOKSHOP_PLAN §6.2 "save as draft, preview, publish,
 * roll back"). Publishing re-checks every colour pair — a theme that fails
 * to read is refused with the reasons — and appends a numbered, optionally
 * named version, so any earlier look can come back. Rolling back publishes
 * an earlier version as a new one (the trail is append-only) and makes it
 * the draft too. Owners only: the page is the shop's face.
 *
 * Since B5 a version also carries the home's sections, the menu, the SEO
 * fields and every page's sections, and the pages' drafts go public with
 * the storefront's. The office's hold (§6.6) refuses publishing, as does a
 * draft holding a section type the office has locked for this shop; a
 * publish clears the "changes required" note and the public cache.
 */
class PublishStorefrontAction
{
    public function publish(VendorScope $scope, ?string $note = null): VendorStorefront
    {
        $this->owner($scope);

        return DB::transaction(function () use ($scope, $note) {
            $storefront = VendorStorefront::query()->where('vendor_id', $scope->vendorId)->lockForUpdate()->firstOrFail();
            $vendor = Vendor::query()->findOrFail($scope->vendorId);
            if ($storefront->isHeld()) {
                throw ValidationException::withMessages(['storefront' => __('shop.error_storefront_held')]);
            }
            $theme = Theme::normalize((array) ($storefront->draft_theme ?? []), $vendor);
            $problems = Theme::problems($theme);
            if ($problems !== []) {
                throw ValidationException::withMessages(['theme' => __('shop.error_theme_unreadable', ['count' => count($problems)])]);
            }
            $preset = $theme['preset'] !== null ? Theme::preset($theme['preset']) : [];
            if (($preset['badge'] ?? null) !== null && ! $vendor->hasBadge($preset['badge'])) {
                throw ValidationException::withMessages(['theme' => __('shop.error_preset_locked')]);
            }
            $pages = VendorPage::query()->where('vendor_id', $scope->vendorId)->orderBy('sort_order')->get();
            $locked = (array) ($storefront->locked_section_types ?? []);
            foreach ([(array) ($storefront->draft_sections ?? []), ...$pages->map(fn (VendorPage $p) => (array) ($p->draft_sections ?? []))->all()] as $sections) {
                foreach ($sections as $section) {
                    if (in_array($section['type'] ?? null, $locked, true)) {
                        throw ValidationException::withMessages(['sections' => __('shop.error_section_locked', ['type' => __('shop.section_'.$section['type'])])]);
                    }
                }
            }
            foreach ($pages as $page) {
                $page->update(['published_sections' => (array) ($page->draft_sections ?? []), 'published_at' => now()]);
            }

            return $this->record($storefront, [
                'identity' => (array) $storefront->draft_identity,
                'theme' => $theme,
                'sections' => (array) ($storefront->draft_sections ?? []),
                'navigation' => (array) ($storefront->draft_navigation ?? []),
                'seo' => (array) ($storefront->draft_seo ?? []),
                'pages' => $pages->map(fn (VendorPage $p) => ['id' => $p->id, 'slug' => $p->slug, 'title' => $p->title, 'title_dv' => $p->title_dv, 'title_ar' => $p->title_ar, 'sections' => (array) ($p->draft_sections ?? []), 'seo' => (array) ($p->seo ?? [])])->values()->all(),
            ], $scope->userId, trim((string) $note) ?: null);
        });
    }

    public function rollBack(VendorScope $scope, int $versionId): VendorStorefront
    {
        $this->owner($scope);

        return DB::transaction(function () use ($scope, $versionId) {
            $storefront = VendorStorefront::query()->where('vendor_id', $scope->vendorId)->lockForUpdate()->firstOrFail();
            if ($storefront->isHeld()) {
                throw ValidationException::withMessages(['storefront' => __('shop.error_storefront_held')]);
            }
            $version = VendorStorefrontVersion::query()->where('vendor_storefront_id', $storefront->id)->whereKey($versionId)->firstOrFail();
            $snapshot = [
                'identity' => (array) $version->identity,
                'theme' => (array) $version->theme,
                'sections' => (array) ($version->sections ?? []),
                'navigation' => (array) ($version->navigation ?? []),
                'seo' => (array) ($version->seo ?? []),
                'pages' => (array) ($version->pages ?? []),
            ];
            $storefront->update(['draft_identity' => $snapshot['identity'], 'draft_theme' => $snapshot['theme'], 'draft_sections' => $snapshot['sections'], 'draft_navigation' => $snapshot['navigation'], 'draft_seo' => $snapshot['seo']]);
            // Pages that still exist take the sections they had then; pages made since keep theirs.
            foreach ($snapshot['pages'] as $was) {
                VendorPage::query()->where('vendor_id', $scope->vendorId)->whereKey((int) ($was['id'] ?? 0))
                    ->update(['draft_sections' => json_encode((array) ($was['sections'] ?? [])), 'published_sections' => json_encode((array) ($was['sections'] ?? [])), 'seo' => json_encode((array) ($was['seo'] ?? [])), 'published_at' => now()]);
            }

            return $this->record($storefront, $snapshot, $scope->userId, __('shop.rolled_back_note', ['number' => $version->number]));
        });
    }

    /**
     * @param  array{identity: array<string, mixed>, theme: array<string, mixed>, sections: list<mixed>, navigation: list<mixed>, seo: array<string, mixed>, pages: list<mixed>}  $snapshot
     */
    private function record(VendorStorefront $storefront, array $snapshot, int $userId, ?string $note): VendorStorefront
    {
        $number = (int) VendorStorefrontVersion::query()->where('vendor_storefront_id', $storefront->id)->max('number') + 1;
        $version = VendorStorefrontVersion::query()->create($snapshot + [
            'vendor_storefront_id' => $storefront->id,
            'number' => $number,
            'note' => $note,
            'created_by' => $userId,
            'created_at' => now(),
        ]);
        $storefront->update([
            'published_identity' => $snapshot['identity'],
            'published_theme' => $snapshot['theme'],
            'published_sections' => $snapshot['sections'],
            'published_navigation' => $snapshot['navigation'],
            'published_seo' => $snapshot['seo'],
            'published_version_id' => $version->id,
            'published_at' => now(),
            'published_by' => $userId,
            'moderation_note' => null,
        ]);
        app(ResolveStorefrontAction::class)->forget($storefront->vendor_id);

        return $storefront->refresh();
    }

    private function owner(VendorScope $scope): void
    {
        if (! $scope->isOwner()) {
            throw new AuthorizationException(__('shop.owner_only'));
        }
    }
}
