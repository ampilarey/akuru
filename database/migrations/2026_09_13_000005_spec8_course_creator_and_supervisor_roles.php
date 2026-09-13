<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

/**
 * SPEC §8 "User Roles" names seven. Six exist. **§8.3 Course Creator does
 * not** — the role is absent from the database entirely — and **§8.4 Dean /
 * Supervisor exists but cannot do a single one of its eight duties.**
 *
 * §8.3 "Course Creator" lists nine things it manages (assigned courses,
 * modules, lessons, content blocks, activities, assessments, question bank,
 * glossary, draft content) and one rule:
 *
 *   > Course creators should **not publish courses directly** unless
 *   > permission is granted.
 *
 * The rule was enforceable all along — `TransitionCourseWorkflowAction`
 * refuses `Published` without `courses.publish` — but there was no role that
 * held `courses.manage` *without* `courses.publish`. To let somebody build a
 * course you had to make them `admin` (108 permissions, publish included) or
 * `headmaster` (79). The one shape §8.3 asks for did not exist.
 *
 * §8.4 "Dean / Supervisor" is worse, because the role **does** exist and looks
 * correct from a distance. It holds 33 permissions and **none of them start
 * with `courses.`**, so every one of §8.4's duties — review submitted courses,
 * approve, reject, request changes, review assessments, review offerings, view
 * academic reports — answered 403. The `/catalog` route group did not list the
 * role either, so it failed twice over.
 *
 * That is also why this matters beyond the role table: PR #316 built §35's
 * approve / reject / request-changes control onto `/catalog/courses`, which is
 * a screen the supervisor could not open. The feature was reachable only by
 * administrators — that is, by everyone except the role the section is named
 * after.
 *
 * **Grants, and why each.**
 *
 * - `course_creator` gets `courses.manage` and deliberately **not**
 *   `courses.publish`, which is §8.3's rule expressed as the absence of a
 *   permission rather than as a special case in code. "Unless permission is
 *   granted" then means exactly what it says: an admin adds `courses.publish`
 *   to that one user.
 * - `supervisor` gets both. §8.4's "Approve courses" *is* publishing — in
 *   `CourseReviewDecision::targetStatus()` an approval moves the course to
 *   `Published`, and `RecordCourseReviewDecisionAction` refuses it without
 *   `courses.publish`. A supervisor who may approve but may not publish could
 *   not approve anything.
 *
 * **No `dean` role is invented.** §8.4 is one heading covering both words, and
 * `supervisor` is the name already in the database, the seeder and the route
 * middleware. A second role would be two names for one job.
 *
 * A **migration**, not a seeder, for the reason
 * `2026_09_10_000010_seeder_only_route_permissions` sets out at length:
 * `scripts/pull-deploy-test.sh` runs `migrate --force` and never `db:seed`, so
 * a role added to `RoleSeeder` never reaches a deployment that already exists.
 *
 * Additive and idempotent (rule 9): `firstOrCreate` plus Spatie's own
 * duplicate-safe `givePermissionTo`. Nothing is revoked from any role.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Both roles are created rather than only granted to. `supervisor`
        // already exists on any deployed database, but on a fresh one it is
        // `RoleSeeder` that makes it — and migrations run first, so an
        // assign-if-exists would silently do nothing and leave §8.4 exactly as
        // broken as it was found. `firstOrCreate` is idempotent either way.
        $creator = Role::firstOrCreate(['name' => 'course_creator', 'guard_name' => 'web']);
        $supervisor = Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);

        // §8.3's content duties. `courses.manage` is the permission every
        // catalog screen checks; `courses.publish` is pointedly absent.
        $this->grant($creator, ['courses.manage']);

        // §8.4's review duties, which the role could not perform at all.
        $this->grant($supervisor, ['courses.manage', 'courses.publish']);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function grant(Role $role, array $permissions): void
    {
        foreach ($permissions as $permission) {
            // Granting a permission that does not exist throws. Every one named
            // here is created by an earlier migration, so this is a guard
            // against ordering surprises rather than an expected branch.
            if (\Spatie\Permission\Models\Permission::where('name', $permission)->where('guard_name', 'web')->exists()) {
                $role->givePermissionTo($permission);
            }
        }
    }

    public function down(): void
    {
        // The supervisor grants are removed; the role itself predates this
        // migration and stays. `course_creator` is deleted because this
        // migration is what created it.
        $supervisor = Role::where('name', 'supervisor')->where('guard_name', 'web')->first();
        $supervisor?->revokePermissionTo(
            $supervisor->permissions()->whereIn('name', ['courses.manage', 'courses.publish'])->get()
        );

        Role::where('name', 'course_creator')->where('guard_name', 'web')->delete();
    }
};
