<?php

namespace App\Domains\Settings\Actions;

use App\Support\Navigation\BuildNavigationAction;

/**
 * The admin panel's front door (the owner, 2026-09-26: "in Bake & Grill
 * admin is a separate app at /admin — is the way admin is set correct?",
 * then "still admin page is too much complicated — can't u categorize and
 * group everything to make it easy"). Akuru's admin is not a separate
 * app: it is the sections under `/admin/*`. This lists them for one
 * person in the panel's four parts — Admissions, Website & content, Shops
 * & money, System — each section with a line on what it is for and, where
 * it is a cluster of screens, the screens inside it. Everything is the
 * navigation map's admin group filtered by each route's own gate, so a
 * Bookstore manager sees one part with one section and a super admin
 * sees all of it. A part with nothing in it is not shown.
 *
 * @return list<array{key: string, label: string, sections: list<array{key: string, label: string, href: string, hard: bool, description: string, children: list<array{key: string, label: string, href: string, hard: bool}>}>}>
 */
class ListAdminSectionsAction
{
    public function execute(object $user, string $locale): array
    {
        $nav = app(BuildNavigationAction::class)->execute($user, $locale);
        $group = collect($nav['groups'])->firstWhere('key', 'admin_group');
        $parts = [];
        foreach ($group['items'] ?? [] as $item) {
            if (empty($item['section'])) {
                continue; // the front door itself
            }
            $part = $item['section']['key'];
            $parts[$part] ??= ['key' => $part, 'label' => $item['section']['label'], 'sections' => []];
            $parts[$part]['sections'][] = [
                'key' => $item['key'],
                'label' => $item['label'],
                'href' => $item['href'],
                'hard' => ! empty($item['hard']),
                'description' => __('admin.desc_'.$item['key']),
                'children' => array_map(fn (array $child) => [
                    'key' => $child['key'],
                    'label' => $child['label'],
                    'href' => $child['href'],
                    'hard' => ! empty($child['hard']),
                ], $item['children'] ?? []),
            ];
        }

        return array_values($parts);
    }
}
