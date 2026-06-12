<?php

namespace App\Domains\Hifz\Services;

use App\Domains\Hifz\Models\HifzEnrollment;
use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\Hifz\Models\HifzSession;
use App\Domains\Hifz\Models\HifzSessionRecord;
use App\Domains\Identity\Models\User;
use App\Domains\People\Models\Student;
use Illuminate\Support\Collection;

class HifzScopeService
{
    public function canAccessProgram(User $user, HifzProgram $program): bool
    {
        if ($user->isHifzDean() || $user->isAdminLevel()) {
            return true;
        }

        if ($user->isSupervisor()) {
            return $program->supervisor_id === $user->id
                || HifzEnrollment::where('hifz_program_id', $program->id)
                    ->where('supervisor_id', $user->id)->exists();
        }

        if ($user->isTeacher() && $user->teacher) {
            return HifzEnrollment::where('hifz_program_id', $program->id)
                ->where('teacher_id', $user->teacher->id)->exists()
                || $program->default_teacher_id === $user->teacher->id;
        }

        if ($user->isStudent() && $user->student) {
            return HifzEnrollment::where('hifz_program_id', $program->id)
                ->where('student_id', $user->student->id)->exists();
        }

        if ($user->isParent()) {
            $childIds = $this->parentChildIds($user);

            return HifzEnrollment::where('hifz_program_id', $program->id)
                ->whereIn('student_id', $childIds)->exists();
        }

        return false;
    }

    public function canAccessStudent(User $user, Student $student): bool
    {
        if ($user->isHifzDean() || $user->isAdminLevel()) {
            return true;
        }

        if ($user->isSupervisor()) {
            return HifzEnrollment::where('student_id', $student->id)
                ->where(function ($q) use ($user) {
                    $q->where('supervisor_id', $user->id)
                        ->orWhereHas('program', fn ($p) => $p->where('supervisor_id', $user->id));
                })->exists();
        }

        if ($user->isTeacher() && $user->teacher) {
            return HifzEnrollment::where('student_id', $student->id)
                ->where('teacher_id', $user->teacher->id)->exists();
        }

        if ($user->isStudent() && $user->student) {
            return $user->student->id === $student->id;
        }

        if ($user->isParent()) {
            return $this->parentChildIds($user)->contains($student->id);
        }

        return false;
    }

    public function canAccessSession(User $user, HifzSession $session): bool
    {
        return $this->canAccessProgram($user, $session->program);
    }

    public function canAccessRecord(User $user, HifzSessionRecord $record): bool
    {
        return $this->canAccessStudent($user, $record->student);
    }

    public function assignedProgramIds(User $user): Collection
    {
        if ($user->isHifzDean() || $user->isAdminLevel()) {
            return HifzProgram::pluck('id');
        }

        if ($user->isSupervisor()) {
            return HifzProgram::where('supervisor_id', $user->id)
                ->orWhereIn('id', HifzEnrollment::where('supervisor_id', $user->id)->pluck('hifz_program_id'))
                ->pluck('id');
        }

        if ($user->isTeacher() && $user->teacher) {
            return HifzEnrollment::where('teacher_id', $user->teacher->id)
                ->pluck('hifz_program_id')
                ->unique();
        }

        if ($user->isStudent() && $user->student) {
            return HifzEnrollment::where('student_id', $user->student->id)->pluck('hifz_program_id');
        }

        if ($user->isParent()) {
            return HifzEnrollment::whereIn('student_id', $this->parentChildIds($user))->pluck('hifz_program_id');
        }

        return collect();
    }

    public function assignedStudentIds(User $user): Collection
    {
        if ($user->isHifzDean() || $user->isAdminLevel()) {
            return HifzEnrollment::where('status', 'active')->pluck('student_id');
        }

        if ($user->isSupervisor()) {
            return HifzEnrollment::where(function ($q) use ($user) {
                $q->where('supervisor_id', $user->id)
                    ->orWhereIn('hifz_program_id', HifzProgram::where('supervisor_id', $user->id)->pluck('id'));
            })->pluck('student_id');
        }

        if ($user->isTeacher() && $user->teacher) {
            return HifzEnrollment::where('teacher_id', $user->teacher->id)->pluck('student_id');
        }

        if ($user->isStudent() && $user->student) {
            return collect([$user->student->id]);
        }

        if ($user->isParent()) {
            return $this->parentChildIds($user);
        }

        return collect();
    }

    protected function parentChildIds(User $user): Collection
    {
        $parent = $user->parentGuardian;

        if (! $parent) {
            return collect();
        }

        return $parent->students()->pluck('students.id');
    }
}
