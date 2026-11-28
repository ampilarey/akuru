<?php

namespace App\Domains\Settings\Actions;

use App\Support\Navigation\BuildNavigationAction;

/**
 * The Settings index (admin audit 2026-11-27, ST1, STATUS §5ut): one card
 * per product's own settings page, and nothing else on the page. Each
 * product keeps its settings separate; this is only the way to them. A card
 * shows to whoever the page itself admits — read off the route's own gate,
 * as the menu is — so the index can never offer a 403.
 */
class ListSettingsIndexAction
{
    /**
     * `group`: the Institute's products, or the School's own (which stay in
     * the School workspace, ADR-040, and are only linked from here).
     *
     * @var list<array{key: string, href: string, group: string, can?: list<string>}>
     */
    public const CARDS = [
        ['key' => 'system_settings', 'href' => '/admin/settings/system', 'group' => 'institute'],
        ['key' => 'library_settings', 'href' => '/admin/library/settings', 'group' => 'institute'],
        // ST4: the business settings that lived in `.env` (config/office-knobs.php), each beside its product.
        ['key' => 'blog_settings', 'href' => '/admin/settings/office/blogs', 'group' => 'institute', 'can' => ['library.manage']],
        ['key' => 'bookshop_settings', 'href' => '/admin/bookshop/settings', 'group' => 'institute', 'can' => ['bookshop.manage']],
        ['key' => 'bookstore_rules', 'href' => '/admin/settings/office/bookstore', 'group' => 'institute', 'can' => ['bookshop.manage']],
        ['key' => 'bookstore_money', 'href' => '/admin/settings/office/bookstore_money', 'group' => 'institute', 'can' => ['bookshop.manage']],
        ['key' => 'print_settings', 'href' => '/admin/bookshop/print', 'group' => 'institute', 'can' => ['bookshop.manage']],
        ['key' => 'lending_settings', 'href' => '/admin/settings/office/lending', 'group' => 'institute', 'can' => ['bookshop.manage']],
        ['key' => 'wallet_settings', 'href' => '/admin/settings/office/wallet', 'group' => 'institute', 'can' => ['commerce.manage']],
        ['key' => 'translations', 'href' => '/admin/translations', 'group' => 'institute'],
        ['key' => 'finance_settings', 'href' => '/finance/settings', 'group' => 'school'],
        ['key' => 'hr_settings', 'href' => '/hr/settings', 'group' => 'school'],
    ];

    /**
     * @return list<array{key: string, href: string, group: string, title: string, description: string}>
     */
    public function execute(?object $user, string $locale): array
    {
        $nav = app(BuildNavigationAction::class);
        $cards = [];
        foreach (self::CARDS as $card) {
            if (! $nav->admits($user, $card, $locale)) {
                continue;
            }
            $cards[] = [
                'key' => $card['key'], 'href' => $card['href'], 'group' => $card['group'],
                'title' => (string) __('admin.settings_card_'.$card['key']),
                'description' => (string) __('admin.settings_card_'.$card['key'].'_about'),
            ];
        }

        return $cards;
    }

    /**
     * ST5 (STATUS §5uy): an office's own settings pages, for the one
     * Settings button on the office — only those this person may open.
     *
     * @param  list<string>  $keys
     * @return list<array{key: string, href: string, title: string}>
     */
    public function links(?object $user, string $locale, array $keys): array
    {
        return collect($this->execute($user, $locale))
            ->filter(fn (array $card) => in_array($card['key'], $keys, true))
            ->sortBy(fn (array $card) => array_search($card['key'], $keys, true))
            ->map(fn (array $card) => ['key' => $card['key'], 'href' => $card['href'], 'title' => $card['title']])
            ->values()->all();
    }
}
