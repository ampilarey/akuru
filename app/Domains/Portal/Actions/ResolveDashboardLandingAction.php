<?php

namespace App\Domains\Portal\Actions;

/**
 * Where `/dashboard` sends a person, and what other identity they hold.
 *
 * This was a flat `elseif` chain in DashboardController, which meant the order
 * of the branches — not any stated rule — decided where someone with two
 * identities landed. A teacher who is also a parent hit `isTeacher()` first and
 * the dashboard never offered them their child's view (E7).
 *
 * Precedence is unchanged and deliberate: **staff first, because teaching or
 * running the school is the job the person signed in to do.** Flipping the
 * order would only break the same case in the other direction — landing a
 * teacher on their child's attendance instead of the register they have to
 * fill. The fix is not a different order, it is that the *other* identity stops
 * being invisible.
 *
 * Takes role names rather than a User: Portal may not import Identity\Models
 * (rule 3), and a pure function of roles is trivially testable.
 */
class ResolveDashboardLandingAction
{
    /** Landings that mean "you are here to run the school". */
    private const STAFF_KINDS = ['super_admin', 'overview', 'supervisor', 'registers'];

    /** @var list<string> */
    private const FAMILY_ROLES = ['student', 'parent'];

    /**
     * @param  list<string>  $roleNames
     * @return array{kind: string, alternate: ?array{label: string, route: string}}
     */
    public function execute(array $roleNames): array
    {
        $kind = $this->kindFor($roleNames);

        return [
            'kind' => $kind,
            'alternate' => $this->alternateFor($kind, $roleNames),
        ];
    }

    /**
     * @param  list<string>  $roleNames
     */
    private function kindFor(array $roleNames): string
    {
        $has = fn (string ...$roles): bool => array_intersect($roles, $roleNames) !== [];

        return match (true) {
            $has('super_admin') => 'super_admin',
            $has('admin', 'headmaster') => 'overview',
            $has('supervisor') => 'supervisor',
            $has('teacher') => 'registers',
            $has('student', 'parent') => 'portal_home',
            default => 'public',
        };
    }

    /**
     * The identity the landing did *not* choose, so the UI can offer it.
     *
     * Only one direction is possible. Every staff role outranks student and
     * parent, so a `portal_home` landing means the person holds no staff role
     * at all — there is no "staff view" alternate to offer, and code for one
     * would never run.
     *
     * Role-based on purpose: `/dashboard` routes by role, so the alternate has
     * to agree with it, and it costs no query — Spatie has the roles in memory
     * already, and this runs on every Inertia response.
     *
     * @param  list<string>  $roleNames
     * @return ?array{label: string, route: string}
     */
    private function alternateFor(string $kind, array $roleNames): ?array
    {
        if (! in_array($kind, self::STAFF_KINDS, true)) {
            return null;
        }

        return array_intersect(self::FAMILY_ROLES, $roleNames) !== []
            ? ['label' => 'Family view', 'route' => 'portal.home']
            : null;
    }
}
