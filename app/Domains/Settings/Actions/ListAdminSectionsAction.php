<?php

namespace App\Domains\Settings\Actions;

use App\Support\Navigation\BuildNavigationAction;

/**
 * The admin panel's front door (the owner, 2026-09-26: "in Bake & Grill
 * admin is a separate app at /admin — is the way admin is set correct?").
 * Akuru's admin is not a separate app: it is the sections under
 * `/admin/*`, and until now nothing answered at `/admin` itself. This
 * lists, for one person, the sections they may open — the navigation
 * map's admin group, filtered by each route's own gate, so a Bookstore
 * manager sees the Bookstore and a super admin sees everything — each
 * with a line saying what it is for.
 *
 * @return list<array{key: string, label: string, href: string, hard: bool, description: string}>
 */
class ListAdminSectionsAction
{
    public function execute(object $user, string $locale): array
    {
        $nav = app(BuildNavigationAction::class)->execute($user, $locale);
        $group = collect($nav['groups'])->firstWhere('key', 'admin_group');
        $sections = [];
        foreach ($group['items'] ?? [] as $item) {
            if ($item['key'] === 'admin_home') {
                continue;
            }
            $sections[] = [
                'key' => $item['key'],
                'label' => $item['label'],
                'href' => $item['href'],
                'hard' => ! empty($item['hard']),
                'description' => __('admin.desc_'.$item['key']),
            ];
        }

        return $sections;
    }
}
