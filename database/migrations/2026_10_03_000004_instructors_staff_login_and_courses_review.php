<?php

use App\Support\Authorization\RoleGrants;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Teachers mark their own courses (BACKLOG C16 slice N6, STATUS §5ob;
 * OWNER_ACTIONS 16, decided 2026-10-03: "teachers mark only their own
 * courses").
 *
 * Two things had to exist before that sentence could be true:
 *
 * - **A way to say whose course it is.** `course_instructor` joined courses
 *   to `instructors` — the public website's profiles, which knew nothing of
 *   a login — so "the submissions from my courses" could not be expressed.
 *   `instructors.user_id` links a profile to a staff account; one account,
 *   one profile.
 * - **A permission that is not authoring.** `courses.manage` opens the
 *   whole catalogue to edit. `courses.review` opens the review queue alone,
 *   narrowed to the courses the person teaches; a reviewer who also holds
 *   `courses.manage` (the dean, the supervisor, a course creator) keeps the
 *   whole school's queue. The school roles are re-synced to their
 *   `RoleGrants` sets, which is where the grant lives; `course_creator`,
 *   which `RoleGrants` does not decide, is granted directly so it loses
 *   nothing it had.
 *
 * Additive (rule 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->unique()->constrained('users')->nullOnDelete();
        });

        $matrix = RoleGrants::matrix();
        $all = array_values(array_unique(array_merge(...array_values($matrix))));

        foreach ($all as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        foreach ($matrix as $role => $permissions) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])->syncPermissions($permissions);
        }

        Role::firstOrCreate(['name' => 'course_creator', 'guard_name' => 'web'])->givePermissionTo('courses.review');
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web'])->givePermissionTo($all);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        // The grant stays: a role that holds it is a decision, not a column.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
