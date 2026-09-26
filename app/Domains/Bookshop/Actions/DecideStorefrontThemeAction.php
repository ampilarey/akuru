<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Models\StorefrontTheme;
use App\Domains\Bookshop\Support\CustomCss;
use Illuminate\Validation\ValidationException;

/**
 * The office's side of the theme gallery (slice B10d, ADR-039): looks the
 * shops offered, waiting; what is in the gallery; publish (a shop's look
 * becomes available to every shop — its CSS is cleaned again, and
 * publishing it is approving it for every shop that applies it), decline
 * with a note, or withdraw a theme (shops already using it keep their look).
 */
class DecideStorefrontThemeAction
{
    /**
     * @return array{waiting: list<array<string, mixed>>, gallery: list<array<string, mixed>>}
     */
    public function list(): array
    {
        $all = StorefrontTheme::query()->with('sourceVendor:id,name')->whereIn('status', ['submitted', 'published'])->orderBy('name')->get();

        return [
            'waiting' => $all->where('status', 'submitted')->map(fn (StorefrontTheme $t) => $t->present())->values()->all(),
            'gallery' => $all->where('status', 'published')->map(fn (StorefrontTheme $t) => $t->present())->values()->all(),
        ];
    }

    public function publish(int $themeId, int $byUserId, ?string $name = null): StorefrontTheme
    {
        $theme = StorefrontTheme::query()->whereIn('status', ['submitted', 'withdrawn'])->findOrFail($themeId);
        $css = null;
        if ($theme->custom_css !== null && $theme->custom_css !== '') {
            $clean = CustomCss::clean($theme->custom_css);
            if ($clean['errors'] !== []) {
                throw ValidationException::withMessages(['theme' => array_map(fn (string $r) => __('shop.css_error_'.$r), $clean['errors'])]);
            }
            $css = $clean['css'];
        }
        $theme->update(['status' => 'published', 'custom_css' => $css, 'name' => trim((string) $name) ?: $theme->name, 'reviewed_by' => $byUserId, 'reviewed_at' => now(), 'review_note' => null]);
        if ($theme->source_vendor_id !== null) {
            app(NotifyBookshopUserAction::class)->vendor((int) $theme->source_vendor_id, __('shop.notice_theme_published_title'), __('shop.notice_theme_published_body', ['name' => $theme->name]), '/vendor/storefront');
        }

        return $theme->refresh();
    }

    public function decline(int $themeId, int $byUserId, string $note): StorefrontTheme
    {
        if (trim($note) === '') {
            throw ValidationException::withMessages(['note' => __('shop.error_css_decline_note')]);
        }
        $theme = StorefrontTheme::query()->where('status', 'submitted')->findOrFail($themeId);
        $theme->update(['status' => 'declined', 'reviewed_by' => $byUserId, 'reviewed_at' => now(), 'review_note' => trim($note)]);
        if ($theme->source_vendor_id !== null) {
            app(NotifyBookshopUserAction::class)->vendor((int) $theme->source_vendor_id, __('shop.notice_theme_declined_title'), __('shop.notice_css_declined_body', ['note' => trim($note)]), '/vendor/storefront');
        }

        return $theme->refresh();
    }

    public function withdraw(int $themeId, int $byUserId): StorefrontTheme
    {
        $theme = StorefrontTheme::query()->where('status', 'published')->findOrFail($themeId);
        $theme->update(['status' => 'withdrawn', 'reviewed_by' => $byUserId, 'reviewed_at' => now()]);

        return $theme->refresh();
    }
}
