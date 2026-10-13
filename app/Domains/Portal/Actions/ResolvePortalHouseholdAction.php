<?php

namespace App\Domains\Portal\Actions;

use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use App\Support\Navigation\ResolveWorkspacesAction;
use App\Support\PersonName;

/**
 * Who a household page is about.
 *
 * Family and Learn share `/portal/home`. A parent sees their children.
 * A pupil sees their own record. A login that is both follows the
 * workspace they switched to: Family stays the children, Learn is
 * their own attendance, homework and fees.
 */
class ResolvePortalHouseholdAction
{
    public function audience(?object $user): string
    {
        if ($user === null) {
            return 'children';
        }

        $resolved = app(ResolveWorkspacesAction::class)->execute($user);
        $held = array_column($resolved['list'], 'key');

        return $resolved['active'] === 'learn' && in_array('learn', $held, true) ? 'self' : 'children';
    }

    /**
     * @return list<array{id: int, name: string, relationship: string}>
     */
    public function people(int $userId, string $audience): array
    {
        $self = $this->self($userId);
        if ($audience === 'self') {
            return $self === null ? [] : [$self];
        }

        $children = [];
        foreach (app(ListGuardianChildrenAction::class)->executeForGuardianUserId($userId) as $child) {
            $id = (int) $child->id;
            if (isset($children[$id])) {
                continue;
            }
            $children[$id] = [
                'id' => $id,
                'name' => PersonName::ofStudent($child),
                'relationship' => (string) ($child->relationship ?? 'child'),
            ];
        }

        if ($children !== []) {
            return array_values($children);
        }

        return $self === null ? [] : [$self];
    }

    /**
     * @return array{id: int, name: string, relationship: string}|null
     */
    private function self(int $userId): ?array
    {
        $self = app(ResolveStudentForUserAction::class)->execute($userId);
        if ($self === null) {
            return null;
        }

        return [
            'id' => (int) $self['id'],
            'name' => PersonName::ofStudent($self),
            'relationship' => 'self',
        ];
    }
}
