<?php

namespace App\Console\Commands;

use App\Models\CourseEnrollment;
use App\Models\PaymentItem;
use App\Models\RegistrationStudent;
use App\Models\User;
use App\Models\UserContact;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Delete registered parents or students from the local database only.
 * Use for clearing test/registration data. Safe: only runs when APP_ENV=local.
 */
class LocalClearRegistrationCommand extends Command
{
    protected $signature = 'local:clear-registration
                            {--student= : Delete one registration student by ID}
                            {--user= : Remove a user (parent) from guardian links by user ID; use with --delete-user to also delete the user and contacts}
                            {--delete-user : When used with --user=, also delete the user and their contacts}
                            {--all : Delete all registration students and their enrollments/guardian links}
                            {--list : List registration students and guardian users (no delete)}';

    protected $description = 'Delete registered parents or students from local DB (APP_ENV=local only)';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('This command only runs when APP_ENV=local.');

            return self::FAILURE;
        }

        if ($this->option('list')) {
            return $this->listData();
        }

        if ($this->option('all')) {
            return $this->deleteAll();
        }

        $studentId = $this->option('student');
        $userId = $this->option('user');

        if ($studentId !== null) {
            return $this->deleteStudent((int) $studentId);
        }

        if ($userId !== null) {
            return $this->detachOrDeleteUser((int) $userId);
        }

        $this->warn('Use --student=ID, --user=ID, --all, or --list. See --help.');

        return self::FAILURE;
    }

    private function listData(): int
    {
        $students = RegistrationStudent::withCount(['enrollments', 'guardians'])
            ->orderBy('id')
            ->get();

        if ($students->isEmpty()) {
            $this->info('No registration students found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'DOB', 'Enrollments', 'Guardians'],
            $students->map(fn ($s) => [
                $s->id,
                $s->full_name,
                $s->dob?->format('Y-m-d') ?? '-',
                $s->enrollments_count,
                $s->guardians_count,
            ])
        );

        $guardians = User::whereHas('guardianStudents')->withCount('guardianStudents')->get();
        if ($guardians->isNotEmpty()) {
            $this->newLine();
            $this->info('Users linked as guardians:');
            $this->table(
                ['User ID', 'Name', 'Email', 'Phone', 'Guardian of (count)'],
                $guardians->map(fn ($u) => [
                    $u->id,
                    $u->name,
                    $u->email ?? '-',
                    $u->phone ?? '-',
                    $u->guardian_students_count,
                ])
            );
        }

        return self::SUCCESS;
    }

    private function deleteStudent(int $id): int
    {
        $student = RegistrationStudent::find($id);
        if (! $student) {
            $this->error("Registration student #{$id} not found.");

            return self::FAILURE;
        }

        $enrollments = $student->enrollments()->get();
        $enrollmentIds = $enrollments->pluck('id')->toArray();

        DB::transaction(function () use ($student, $enrollmentIds) {
            PaymentItem::whereIn('enrollment_id', $enrollmentIds)->delete();
            CourseEnrollment::whereIn('id', $enrollmentIds)->update(['payment_id' => null]);
            $student->enrollments()->delete();
            $student->guardians()->detach();
            $student->delete();
        });

        $this->info("Deleted registration student #{$id} ({$student->full_name}) and related enrollments/guardian links.");

        return self::SUCCESS;
    }

    private function detachOrDeleteUser(int $id): int
    {
        $user = User::find($id);
        if (! $user) {
            $this->error("User #{$id} not found.");

            return self::FAILURE;
        }

        $guardianCount = $user->guardianStudents()->count();

        if ($this->option('delete-user')) {
            DB::transaction(function () use ($user) {
                $user->guardianStudents()->detach();
                UserContact::where('user_id', $user->id)->delete();
                $user->delete();
            });
            $this->info("Deleted user #{$id} ({$user->name}), their contacts, and guardian links.");

            return self::SUCCESS;
        }

        $user->guardianStudents()->detach();
        $this->info("Removed user #{$id} ({$user->name}) from {$guardianCount} guardian link(s). User and contacts kept.");

        return self::SUCCESS;
    }

    private function deleteAll(): int
    {
        $count = RegistrationStudent::count();
        if ($count === 0) {
            $this->info('No registration students to delete.');

            return self::SUCCESS;
        }

        if (! $this->confirm("Delete all {$count} registration student(s) and their enrollments/guardian links?", true)) {
            return self::SUCCESS;
        }

        DB::transaction(function () {
            $enrollmentIds = CourseEnrollment::pluck('id');
            PaymentItem::whereIn('enrollment_id', $enrollmentIds)->delete();
            CourseEnrollment::query()->update(['payment_id' => null]);
            CourseEnrollment::query()->delete();
            DB::table('student_guardians')->delete();
            RegistrationStudent::query()->delete();
        });

        $this->info("Deleted all registration students and related data.");

        return self::SUCCESS;
    }
}
