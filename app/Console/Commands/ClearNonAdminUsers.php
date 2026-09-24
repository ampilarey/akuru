<?php

namespace App\Console\Commands;

use App\Domains\Identity\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClearNonAdminUsers extends Command
{
    protected $signature = 'users:clear-non-admin {--force : Skip confirmation prompt}';

    protected $description = 'Delete all users except super_admin and admin roles (clears test data)';

    public function handle(): int
    {
        $keepIds = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['super_admin', 'admin']))
            ->pluck('id');

        if ($keepIds->isEmpty()) {
            $this->error('No admin/super_admin users found. Aborting to prevent deleting everyone.');

            return self::FAILURE;
        }

        $deleteCount = User::whereNotIn('id', $keepIds)->count();

        if ($deleteCount === 0) {
            $this->info('Nothing to delete — only admin/super_admin users exist.');

            return self::SUCCESS;
        }

        $this->table(['Keeping (ID)', 'Name', 'Roles'], User::whereIn('id', $keepIds)->get()->map(fn ($u) => [
            $u->id,
            $u->name,
            $u->getRoleNames()->implode(', '),
        ]));

        $this->warn("This will permanently delete {$deleteCount} user(s) and all their related data.");

        if (! $this->option('force') && ! $this->confirm('Are you sure?')) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $deleteUserIds = User::query()->whereNotIn('id', $keepIds)->pluck('id');

        $deletedGuardianProfileIds = Schema::hasTable('parent_guardians')
            ? DB::table('parent_guardians')->whereIn('user_id', $deleteUserIds)->pluck('id')
            : collect();
        $deletedUnifiedStudentIds = Schema::hasTable('students')
            ? DB::table('students')->whereNotNull('user_id')->whereNotIn('user_id', $keepIds)->pluck('id')
            : collect();

        // Children a wiped parent registered have no login of their own, so
        // `user_id` cannot find them: they are the students whose every
        // guardian is being wiped. (Before Deploy 3 this was "every
        // registration_students row with a NULL user_id".)
        $orphanedChildIds = $deletedGuardianProfileIds->isEmpty() ? collect() : DB::table('students')
            ->whereNull('user_id')
            ->whereIn('id', DB::table('guardian_student')->whereIn('guardian_id', $deletedGuardianProfileIds)->select('student_id'))
            ->whereNotIn('id', DB::table('guardian_student')->whereNotIn('guardian_id', $deletedGuardianProfileIds)->select('student_id'))
            ->pluck('id');
        $registrantIds = $deletedUnifiedStudentIds->merge($orphanedChildIds)->unique()->values();

        // The archive (Deploy 3 slice 3) is wiped the way the live legacy
        // tables were: rows owned by wiped users, and guardian-only rows.
        $archivedRsIds = Schema::hasTable('archived_registration_students')
            ? DB::table('archived_registration_students')
                ->where(fn ($q) => $q->whereNull('user_id')->orWhereNotIn('user_id', $keepIds))
                ->pluck('id')
            : collect();

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('user_contacts')->whereNotIn('user_id', $keepIds)->delete();
        $userMorph = (new User)->getMorphClass();
        DB::table('model_has_roles')->whereNotIn('model_id', $keepIds)->where('model_type', $userMorph)->delete();
        DB::table('model_has_permissions')->whereNotIn('model_id', $keepIds)->where('model_type', $userMorph)->delete();
        // OTPs for removed users go with their user_contacts rows above
        // (user_contact_otps.user_contact_id cascades on delete). The pre-2026
        // `otps` table this used to truncate is gone — see the drop migration.

        if (Schema::hasTable('archived_student_guardians')) {
            DB::table('archived_student_guardians')
                ->where(fn ($q) => $q->whereIn('student_id', $archivedRsIds)->orWhereIn('guardian_user_id', $deleteUserIds))
                ->delete();
        }
        if ($deletedGuardianProfileIds->isNotEmpty() && Schema::hasTable('guardian_student')) {
            DB::table('guardian_student')->whereIn('guardian_id', $deletedGuardianProfileIds)->delete();
        }
        if ($registrantIds->isNotEmpty() && Schema::hasTable('guardian_student')) {
            DB::table('guardian_student')->whereIn('student_id', $registrantIds)->delete();
        }
        if ($deletedGuardianProfileIds->isNotEmpty()) {
            DB::table('parent_guardians')->whereIn('id', $deletedGuardianProfileIds)->delete();
        }
        if ($registrantIds->isNotEmpty()) {
            DB::table('course_enrollments')->whereIn('unified_student_id', $registrantIds)->delete();
        }
        if ($archivedRsIds->isNotEmpty()) {
            DB::table('course_enrollments')->whereIn('archived_registration_student_id', $archivedRsIds)->delete();
            DB::table('archived_registration_students')->whereIn('id', $archivedRsIds)->delete();
        }
        DB::table('payments')->whereNotNull('user_id')->whereNotIn('user_id', $keepIds)->delete();
        User::whereNotIn('id', $keepIds)->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->info("Done! Deleted {$deleteCount} user(s). Remaining: ".User::count());

        return self::SUCCESS;
    }
}
