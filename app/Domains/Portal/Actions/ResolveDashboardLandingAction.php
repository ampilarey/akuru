<?php

namespace App\Domains\Portal\Actions;

/**
 * Where `/dashboard` sends a person, and every other identity they hold.
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
 * fill. The fix is not a different order, it is that the *other* identities
 * stop being invisible: `views` lists every home this person holds — the
 * admin panel, a teacher's day, the family portal, Learn, a vendor's shop,
 * a writer's desk, a reviewer's queue, the catalogue — so the shell can offer
 * each one from any page (the owner, 2026-09-27: "a parent may be enrolled
 * in a course, and he may be a vendor and a writer"). A person with one
 * identity gets one view and the shell shows none.
 *
 * Takes role names rather than a User: Portal may not import Identity\Models
 * (rule 3), and a pure function of roles is trivially testable.
 */
class ResolveDashboardLandingAction
{
    /** Landings that mean "you are here to run the school". */
    private const STAFF_KINDS = ['admin', 'supervisor', 'registers', 'bookshop'];

    private const ADMIN_ROLES = ['super_admin', 'admin', 'headmaster', 'supervisor'];

    /** @var list<string> */
    private const FAMILY_ROLES = ['student', 'parent'];

    /**
     * Every identity's home, in the order the shell offers them: the roles
     * that hold it, the `nav.php` label key, the route.
     *
     * @var list<array{key: string, roles: list<string>, route: string}>
     */
    private const VIEWS = [
        ['key' => 'admin', 'roles' => self::ADMIN_ROLES, 'route' => 'admin.index'],
        ['key' => 'bookshop', 'roles' => ['bookshop_manager'], 'route' => 'admin.bookshop.index'],
        ['key' => 'teacher', 'roles' => ['teacher'], 'route' => 'portal.teacher'],
        ['key' => 'family', 'roles' => ['parent'], 'route' => 'portal.home'],
        ['key' => 'learn', 'roles' => ['student'], 'route' => 'learn.dashboard'],
        ['key' => 'vendor', 'roles' => ['vendor'], 'route' => 'vendor.index'],
        ['key' => 'write', 'roles' => ['writer'], 'route' => 'write.index'],
        ['key' => 'review', 'roles' => ['reviewer'], 'route' => 'review.index'],
        ['key' => 'catalog', 'roles' => ['course_creator'], 'route' => 'catalog.courses.index'],
    ];

    /**
     * @param  list<string>  $roleNames
     * @return array{kind: string, alternate: ?array{label: string, route: string}, views: list<array{key: string, label: string, route: string}>}
     */
    public function execute(array $roleNames): array
    {
        $kind = $this->kindFor($roleNames);

        return [
            'kind' => $kind,
            'alternate' => $this->alternateFor($kind, $roleNames),
            'views' => $this->viewsFor($roleNames),
        ];
    }

    /**
     * @param  list<string>  $roleNames
     */
    private function kindFor(array $roleNames): string
    {
        $has = fn (string ...$roles): bool => array_intersect($roles, $roleNames) !== [];

        return match (true) {
            // The administrator's home is the admin panel (STATUS §5ia); the
            // three kinds are kept apart because their full dashboards differ.
            $has('super_admin') => 'super_admin',
            $has('admin', 'headmaster') => 'overview',
            $has('supervisor') => 'supervisor',
            $has('teacher') => 'registers',
            // B10b: a Bookstore admin's job is the Bookstore office screen.
            $has('bookshop_manager') => 'bookshop',
            $has('student', 'parent') => 'portal_home',
            // A vendor, a writer, a reviewer or a course creator with no other
            // role used to fall through to the public "My Dashboard", a page
            // about course enrolments they may not have. Their job has a home.
            $has('vendor') => 'vendor',
            $has('writer') => 'writer',
            $has('reviewer') => 'reviewer',
            $has('course_creator') => 'catalog',
            default => 'public',
        };
    }

    /**
     * The identity the landing did *not* choose, so the UI can offer it.
     *
     * Kept for the E7 case (a staff member who is also a parent); `views`
     * generalises it to every identity a person holds.
     *
     * @param  list<string>  $roleNames
     * @return ?array{label: string, route: string}
     */
    private function alternateFor(string $kind, array $roleNames): ?array
    {
        $staff = in_array($kind, self::STAFF_KINDS, true) || in_array($kind, ['super_admin', 'overview'], true);
        if (! $staff) {
            return null;
        }

        return array_intersect(self::FAMILY_ROLES, $roleNames) !== []
            ? ['label' => 'Family view', 'route' => 'portal.home']
            : null;
    }

    /**
     * @param  list<string>  $roleNames
     * @return list<array{key: string, label: string, route: string}>
     */
    private function viewsFor(array $roleNames): array
    {
        $views = [];
        foreach (self::VIEWS as $view) {
            if (array_intersect($view['roles'], $roleNames) !== []) {
                $views[] = ['key' => $view['key'], 'label' => 'view_'.$view['key'], 'route' => $view['route']];
            }
        }

        return $views;
    }
}
