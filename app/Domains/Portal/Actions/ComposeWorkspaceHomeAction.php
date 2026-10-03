<?php

namespace App\Domains\Portal\Actions;

use App\Support\Navigation\BuildNavigationAction;

/**
 * A workspace's home page (`/admin` for the Institute, `/school` for the
 * School; STATUS §5id): the workspace's *More* menu laid out as parts of
 * cards, so the whole workspace is one page to read. The admin panel's
 * parts (Website & content; Admissions; Shops & money; Settings; System)
 * come with each section's description and inner screens. The School's academic and
 * office groups become two more parts, each group a card whose chips are
 * its screens. Everything is the navigation for this person in this
 * workspace, so a screen they could only be refused is not on the page.
 *
 * A part may also name clusters: sections that belong together (the
 * bookstore's screens, the platform's settings) so the home can head
 * them as one group. A cluster is omitted when fewer than two of its
 * sections are actually on the page.
 *
 * @return list<array{key: string, label: string, clusters: list<array{key: string, label: string, sections: list<string>}>, sections: list<array{key: string, label: string, href: string, hard: bool, description: string, children: list<array{key: string, label: string, href: string, hard: bool}>}>}>
 */
class ComposeWorkspaceHomeAction
{
    /**
     * Sections of one part that read as one job. The home prints a heading
     * over them; the order of the part itself does not change.
     *
     * @var array<string, array<string, list<string>>>
     */
    private const CLUSTERS = [
        'panel_website' => [
            'site' => ['website_cms', 'admin_instructors'],
            'learning' => ['prayer_times', 'pronunciation_office'],
        ],
        'panel_money' => [
            'bookstore' => ['bookshop', 'akuru_fulfilment', 'complaints', 'sms_campaigns', 'shop_customers', 'shop_credit'],
        ],
        // System settings and the translations moved to the Settings part
        // in the navigation re-audit (ADMIN_PANEL.md §8), so the platform
        // cluster went with them; Settings is one job and needs no heading.
        'panel_system' => [
            'readiness' => ['ops_checklist', 'feature_walkthrough'],
        ],
    ];

    /** The School's groups, gathered into two parts. */
    private const SCHOOL_PARTS = [
        'school_academics' => ['school_year', 'day_loop', 'exams_group', 'catalog_group', 'teaching'],
        'school_office' => ['people', 'finance_group', 'hr_group', 'library_group'],
    ];

    public function execute(object $user, string $workspace, string $locale): array
    {
        $nav = app(BuildNavigationAction::class)->execute($user, $locale, $workspace);
        $groups = collect($nav['groups'])->keyBy('key');
        $parts = [];

        // The admin panel's parts: a card per section, with its inner screens.
        foreach ($groups as $key => $group) {
            if (! str_starts_with($key, 'panel_')) {
                continue;
            }
            $sections = array_map(fn (array $item) => $this->section($item['key'], $item['label'], $item, $item['children'] ?? []), $group['items']);
            $parts[] = [
                'key' => $key,
                'label' => $group['label'],
                'clusters' => $this->clusters($key, $sections),
                'sections' => $sections,
            ];
        }

        if ($workspace === 'school') {
            foreach (self::SCHOOL_PARTS as $partKey => $groupKeys) {
                $sections = [];
                foreach ($groupKeys as $groupKey) {
                    $group = $groups[$groupKey] ?? null;
                    if ($group === null) {
                        continue;
                    }
                    $sections[] = $this->section($groupKey, $group['label'], $group['items'][0], $group['items']);
                }
                if ($sections !== []) {
                    $parts[] = ['key' => $partKey, 'label' => __('admin.part_'.$partKey), 'clusters' => [], 'sections' => $sections];
                }
            }
        }

        return $parts;
    }

    /**
     * @param  list<array{key: string}>  $sections
     * @return list<array{key: string, label: string, sections: list<string>}>
     */
    private function clusters(string $partKey, array $sections): array
    {
        $present = array_column($sections, 'key');
        $clusters = [];
        foreach (self::CLUSTERS[$partKey] ?? [] as $key => $members) {
            $members = array_values(array_filter($members, fn (string $member) => in_array($member, $present, true)));
            if (count($members) < 2) {
                continue;
            }
            $clusters[] = ['key' => $key, 'label' => __('admin.cluster_'.$key), 'sections' => $members];
        }

        return $clusters;
    }

    /**
     * @param  array{href: string, hard?: bool}  $opens
     * @param  list<array{key: string, label: string, href: string, hard?: bool}>  $children
     * @return array{key: string, label: string, href: string, hard: bool, description: string, children: list<array{key: string, label: string, href: string, hard: bool}>}
     */
    private function section(string $key, string $label, array $opens, array $children): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'href' => $opens['href'],
            'hard' => ! empty($opens['hard']),
            'description' => __('admin.desc_'.$key),
            'children' => array_map(fn (array $child) => [
                'key' => $child['key'],
                'label' => $child['label'],
                'href' => $child['href'],
                'hard' => ! empty($child['hard']),
            ], $children),
        ];
    }
}
