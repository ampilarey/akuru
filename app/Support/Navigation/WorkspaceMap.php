<?php

namespace App\Support\Navigation;

/**
 * The workspaces: one per job a person may hold, each with its own home,
 * primary bar and *More* groups. The shell shows one workspace at a time
 * and a switcher for the others (the owner, 2026-09-27: "admin or super
 * admin can be a dean or supervisor or teacher, but their setting should be
 * seen when he changes to his specific role"; STATUS §5id, ADR-040).
 *
 * A workspace never grants access: every route still enforces its own gate
 * and `BuildNavigationAction` hides what the person could only be refused.
 * The workspace decides which home, which bar and which groups are shown —
 * the Institute's (the business: website, users, system, shops, the
 * library office), the School's (education: admissions, academics, the
 * office), a family's, a shop's.
 *
 * Order matters: a person who holds several lands on the first they hold,
 * and the switcher lists them in this order. Staff first, because running
 * the institute or the school is the job the person signed in to do (E7).
 *
 * A workspace's groups are its own, the way an EduPage account's drawer is
 * (docs/SIGN_IN_PLAN.md, ID1): the School's office groups, its teaching and
 * its communication; a household's Communication, Education, Evaluation
 * and Other; a shop, a writer's desk or the Bookstore office nothing of
 * the school's. Every workspace ends with the same Personal group (`me`).
 */
final class WorkspaceMap
{
    /** The workspace of a signed-in person with no role at all: their own account. */
    public const ACCOUNT = 'account';

    /** The groups a family and a pupil hold, in EduPage's order. */
    private const HOUSEHOLD = ['communication', 'education', 'evaluation', 'other', 'me'];

    /**
     * @return array<string, array{roles: list<string>, groups: list<string>, home: string}>
     */
    public static function all(): array
    {
        return [
            'institute' => ['roles' => ['super_admin'], 'groups' => ['panel_website', 'panel_money', 'panel_system', 'me'], 'home' => 'admin.index'],
            'school' => ['roles' => ['admin', 'headmaster', 'supervisor', 'teacher'], 'groups' => ['panel_admissions', 'school_year', 'people', 'day_loop', 'exams_group', 'catalog_group', 'teaching', 'finance_group', 'hr_group', 'library_group', 'communication', 'my_work', 'me'], 'home' => 'school.index'],
            'bookstore' => ['roles' => ['bookshop_manager'], 'groups' => ['me'], 'home' => 'admin.bookshop.index'],
            'family' => ['roles' => ['parent'], 'groups' => self::HOUSEHOLD, 'home' => 'portal.home'],
            'learn' => ['roles' => ['student'], 'groups' => self::HOUSEHOLD, 'home' => 'portal.home'],
            'vendor' => ['roles' => ['vendor'], 'groups' => ['me'], 'home' => 'vendor.index'],
            'writing' => ['roles' => ['writer', 'reviewer'], 'groups' => ['me'], 'home' => 'write.index'],
            'catalog' => ['roles' => ['course_creator'], 'groups' => ['catalog_group', 'me'], 'home' => 'catalog.courses.index'],
        ];
    }

    /**
     * @return array{roles: list<string>, groups: list<string>, home: string}
     */
    public static function definition(string $workspace): array
    {
        return self::all()[$workspace] ?? ['roles' => [], 'groups' => ['me'], 'home' => 'dashboard'];
    }

    /**
     * Which primary bars (`NavigationMap::primary()` keys) a person's roles
     * contribute inside a workspace. Roles from another workspace add
     * nothing here: a super admin who is also a teacher gets the Institute
     * bar in the Institute and the teacher's bar in the School.
     *
     * @param  list<string>  $roles
     * @return list<string>
     */
    public static function barsFor(string $workspace, array $roles): array
    {
        $has = fn (string ...$wanted): bool => array_intersect($wanted, $roles) !== [];

        return match ($workspace) {
            'institute' => ['institute'],
            'school' => [
                ...($has('admin', 'headmaster', 'supervisor') ? ['admins'] : []),
                ...($has('teacher') ? ['teacher'] : []),
            ],
            'bookstore' => ['bookshop_manager'],
            'family' => ['parent'],
            'learn' => ['student'],
            'vendor' => ['vendor'],
            'writing' => [
                ...($has('writer') ? ['writer'] : []),
                ...($has('reviewer') ? ['reviewer'] : []),
            ],
            'catalog' => ['course_creator'],
            default => [],
        };
    }

    /**
     * The home route of a person in a workspace. A teacher who runs nothing
     * else lands on their own day, not the school office; a reviewer who
     * does not write lands on the review queue.
     *
     * @param  list<string>  $roles
     */
    public static function homeFor(string $workspace, array $roles): string
    {
        $has = fn (string ...$wanted): bool => array_intersect($wanted, $roles) !== [];

        return match (true) {
            $workspace === 'school' && ! $has('admin', 'headmaster', 'supervisor') => 'portal.teacher',
            $workspace === 'writing' && ! $has('writer') => 'review.index',
            default => self::definition($workspace)['home'],
        };
    }
}
