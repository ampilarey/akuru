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

        // whereNotIn('user_id', $keepIds) does not match NULL. Guardian-only
        // registration_students (user_id IS NULL) would survive while their
        // guardian users are deleted. List both sets explicitly.
        $rsOwnedByDeletedUsers = DB::table('registration_students')
            ->whereNotNull('user_id')
            ->whereNotIn('user_id', $keepIds)
            ->pluck('id');
        $rsWithNullUserId = DB::table('registration_students')
            ->whereNull('user_id')
            ->pluck('id');
        $deleteRsIds = $rsOwnedByDeletedUsers->merge($rsWithNullUserId)->unique()->values();

        $deletedGuardianProfileIds = Schema::hasTable('parent_guardians')
            ? DB::table('parent_guardians')->whereIn('user_id', $deleteUserIds)->pluck('id')
            : collect();
        $deletedUnifiedStudentIds = Schema::hasTable('students')
            ? DB::table('students')->whereNotNull('user_id')->whereNotIn('user_id', $keepIds)->pluck('id')
            : collect();

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('user_contacts')->whereNotIn('user_id', $keepIds)->delete();
        $userMorph = (new User)->getMorphClass();
        DB::table('model_has_roles')->whereNotIn('model_id', $keepIds)->where('model_type', $userMorph)->delete();
        DB::table('model_has_permissions')->whereNotIn('model_id', $keepIds)->where('model_type', $userMorph)->delete();
        DB::table('otps')->truncate();

        if ($deleteRsIds->isNotEmpty() && Schema::hasTable('student_guardians')) {
            DB::table('student_guardians')->whereIn('student_id', $deleteRsIds)->delete();
        }
        if (Schema::hasTable('student_guardians')) {
            DB::table('student_guardians')->whereIn('guardian_user_id', $deleteUserIds)->delete();
        }
        if ($deletedGuardianProfileIds->isNotEmpty() && Schema::hasTable('guardian_student')) {
            DB::table('guardian_student')->whereIn('guardian_id', $deletedGuardianProfileIds)->delete();
        }
        if ($deletedUnifiedStudentIds->isNotEmpty() && Schema::hasTable('guardian_student')) {
            DB::table('guardian_student')->whereIn('student_id', $deletedUnifiedStudentIds)->delete();
        }
        if ($deletedGuardianProfileIds->isNotEmpty()) {
            DB::table('parent_guardians')->whereIn('id', $deletedGuardianProfileIds)->delete();
        }
        if ($deleteRsIds->isNotEmpty()) {
            DB::table('course_enrollments')->whereIn('student_id', $deleteRsIds)->delete();
            DB::table('registration_students')->whereIn('id', $deleteRsIds)->delete();
        }
        DB::table('payments')->whereNotNull('user_id')->whereNotIn('user_id', $keepIds)->delete();
        User::whereNotIn('id', $keepIds)->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->info("Done! Deleted {$deleteCount} user(s). Remaining: ".User::count());

        return self::SUCCESS;
    }
}
