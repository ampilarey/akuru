<?php

namespace App\Domains\Portal\Actions;

use App\Support\Navigation\BuildNavigationAction;

/**
 * A workspace's home page (`/admin` for the Institute, `/school` for the
 * School; STATUS §5id): the workspace's *More* menu laid out as parts of
 * cards, so the whole workspace is one page to read. The admin panel's
 * parts (Admissions; Website & content; Shops & money; System) come with
 * each section's description and inner screens. The School's academic and
 * office groups become two more parts, each group a card whose chips are
 * its screens. Everything is the navigation for this person in this
 * workspace, so a screen they could only be refused is not on the page.
 *
 * @return list<array{key: string, label: string, sections: list<array{key: string, label: string, href: string, hard: bool, description: string, children: list<array{key: string, label: string, href: string, hard: bool}>}>}>
 */
class ComposeWorkspaceHomeAction
{
    /** The School's groups, gathered into two parts. */
    private const SCHOOL_PARTS = [
        'school_academics' => ['school_year', 'day_loop', 'exams_group', 'catalog_group', 'learn_group'],
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
            $parts[] = [
                'key' => $key,
                'label' => $group['label'],
                'sections' => array_map(fn (array $item) => $this->section($item['key'], $item['label'], $item, $item['children'] ?? []), $group['items']),
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
                    $parts[] = ['key' => $partKey, 'label' => __('admin.part_'.$partKey), 'sections' => $sections];
                }
            }
        }

        return $parts;
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
