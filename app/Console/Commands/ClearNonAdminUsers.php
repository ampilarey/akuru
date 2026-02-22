<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearNonAdminUsers extends Command
{
    protected $signature   = 'users:clear-non-admin {--force : Skip confirmation prompt}';
    protected $description = 'Delete all users except super_admin and admin roles (clears test data)';

    public function handle(): int
    {
        $keepIds = User::role(['super_admin', 'admin'])->pluck('id');

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

        // Get student IDs belonging to users being deleted (for course_enrollments)
        $deleteStudentIds = DB::table('registration_students')
            ->whereNotIn('user_id', $keepIds)
            ->pluck('id');

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('user_contacts')->whereNotIn('user_id', $keepIds)->delete();
        DB::table('model_has_roles')->whereNotIn('model_id', $keepIds)->where('model_type', \App\Models\User::class)->delete();
        DB::table('model_has_permissions')->whereNotIn('model_id', $keepIds)->where('model_type', \App\Models\User::class)->delete();
        DB::table('otps')->truncate();
        if ($deleteStudentIds->isNotEmpty()) {
            DB::table('course_enrollments')->whereIn('student_id', $deleteStudentIds)->delete();
        }
        DB::table('registration_students')->whereNotIn('user_id', $keepIds)->delete();
        DB::table('payments')->whereNotIn('user_id', $keepIds)->delete();
        User::whereNotIn('id', $keepIds)->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->info("Done! Deleted {$deleteCount} user(s). Remaining: " . User::count());

        return self::SUCCESS;
    }
}
