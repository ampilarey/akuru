<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Enums\GuardianRelationship;
use App\Domains\People\Enums\StudentStatus;
use App\Domains\People\Models\ParentGuardian;
use App\Domains\People\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * The student a course registration is for, on `students` (rule 11).
 *
 * S1 Deploy 3, slice 2. Until now registration created a
 * `registration_students` row and `DualWriteCourseStudentAction` mirrored it
 * onto `students`; guardians went into `student_guardians` and were mirrored
 * onto `guardian_student`. Every read already used the mirror (Deploy 2), so
 * the mirror becomes the only write and the legacy tables stop growing.
 *
 * Admissions may not import People's models (rule 3), so everything here
 * returns the plain snapshot {@see snapshot()} describes.
 *
 * The matching rules are the ones registration already used, moved rather
 * than reinvented: a parent's new child is first looked for among their own
 * children, then among students who have an account, by ID card or passport.
 */
class RegisterCourseStudentAction
{
    /**
     * An adult registering themselves: their own student record, created on
     * first registration and refreshed after.
     *
     * @param  array{first_name: string, last_name: string, dob: string, gender?: ?string, national_id?: ?string, passport?: ?string}  $details
     * @return array{id: int, user_id: ?int, first_name: string, last_name: string, date_of_birth: ?string, gender: ?string, national_id: ?string, passport: ?string}
     */
    public function forSelf(int $userId, array $details): array
    {
        $student = Student::query()->where('user_id', $userId)->orderBy('id')->first();

        $student = $student === null
            ? $this->create($userId, $details)
            : $this->refresh($student, $details);

        return $this->snapshot($student);
    }

    /**
     * A parent registering a new child. An existing record with the same ID
     * card or passport is reused rather than duplicated, and the parent is
     * linked to it as a guardian either way.
     *
     * @param  array{first_name: string, last_name: string, dob: string, gender?: ?string, national_id?: ?string, passport?: ?string}  $details
     * @return array{id: int, user_id: ?int, first_name: string, last_name: string, date_of_birth: ?string, gender: ?string, national_id: ?string, passport: ?string}
     */
    public function forChild(int $guardianUserId, array $details, ?string $relationship = null): array
    {
        $nid = $this->identity($details['national_id'] ?? null);
        $passport = $this->identity($details['passport'] ?? null);

        $student = $this->matchAmong($this->childrenQuery($guardianUserId), $nid, $passport)
            ?? $this->matchAmong(Student::query()->whereNotNull('user_id'), $nid, $passport)
            ?? $this->create(null, $details);

        $this->linkGuardian($guardianUserId, $student, $relationship);

        return $this->snapshot($student);
    }

    /**
     * A student who has an account, found by ID card or passport. Used to
     * refuse a registration that would reuse somebody else's identity.
     *
     * @return array{id: int, user_id: ?int, first_name: string, last_name: string, date_of_birth: ?string, gender: ?string, national_id: ?string, passport: ?string}|null
     */
    public function findWithAccountByIdentity(?string $nationalId, ?string $passport): ?array
    {
        $student = $this->matchAmong(Student::query()->whereNotNull('user_id'), $this->identity($nationalId), $this->identity($passport));

        return $student === null ? null : $this->snapshot($student);
    }

    /**
     * One of this parent's own children, found by ID card or passport.
     *
     * @return array{id: int, user_id: ?int, first_name: string, last_name: string, date_of_birth: ?string, gender: ?string, national_id: ?string, passport: ?string}|null
     */
    public function findChildByIdentity(int $guardianUserId, ?string $nationalId, ?string $passport): ?array
    {
        $student = $this->matchAmong($this->childrenQuery($guardianUserId), $this->identity($nationalId), $this->identity($passport));

        return $student === null ? null : $this->snapshot($student);
    }

    /**
     * The student, if this person may register or pay for them: their own
     * record, or a child they are linked to as a guardian.
     *
     * @return array{id: int, user_id: ?int, first_name: string, last_name: string, date_of_birth: ?string, gender: ?string, national_id: ?string, passport: ?string}|null
     */
    public function forActor(int $userId, int $studentId): ?array
    {
        $student = Student::query()->whereKey($studentId)->first();
        if ($student === null) {
            return null;
        }

        $mayAct = (int) $student->user_id === $userId
            || $this->childrenQuery($userId)->whereKey($studentId)->exists();

        return $mayAct ? $this->snapshot($student) : null;
    }

    /**
     * Every student id this person may register or pay for.
     *
     * @return list<int>
     */
    public function idsForActor(int $userId): array
    {
        $ids = $this->childrenQuery($userId)->pluck('students.id')->all();
        $own = Student::query()->where('user_id', $userId)->pluck('id')->all();

        return array_values(array_unique(array_map('intval', array_merge($ids, $own))));
    }

    /** The child's own login, created at registration, is recorded on their student. */
    public function linkAccount(int $studentId, int $userId): void
    {
        Student::query()->whereKey($studentId)->update(['user_id' => $userId]);
    }

    private function childrenQuery(int $guardianUserId)
    {
        return Student::query()->whereIn('students.id', function ($query) use ($guardianUserId) {
            $query->select('guardian_student.student_id')
                ->from('guardian_student')
                ->join('parent_guardians', 'parent_guardians.id', '=', 'guardian_student.guardian_id')
                ->where('parent_guardians.user_id', $guardianUserId);
        });
    }

    private function matchAmong($query, ?string $nid, ?string $passport): ?Student
    {
        if ($nid === null && $passport === null) {
            return null;
        }

        return $query
            ->where(function ($q) use ($nid, $passport) {
                if ($nid !== null) {
                    $q->orWhere('national_id', $nid);
                }
                if ($passport !== null) {
                    $q->orWhere('passport', $passport);
                }
            })
            ->orderBy('students.id')
            ->first();
    }

    private function create(?int $userId, array $details): Student
    {
        $student = new Student;
        $student->forceFill([
            'user_id' => $userId,
            'first_name' => trim($details['first_name']),
            'last_name' => trim($details['last_name']),
            'date_of_birth' => $details['dob'],
            // `students.gender` is required and the registration forms let it
            // be left empty. This is the default the dual write applied, kept
            // as it was; KNOWN_ISSUES records it.
            'gender' => $this->plain($details['gender'] ?? null) ?? 'male',
            'national_id' => $this->identity($details['national_id'] ?? null),
            'passport' => $this->identity($details['passport'] ?? null),
            'status' => StudentStatus::Prospective,
        ]);
        $student->save();

        return $student;
    }

    private function refresh(Student $student, array $details): Student
    {
        $student->first_name = trim($details['first_name']);
        $student->last_name = trim($details['last_name']);
        $student->date_of_birth = $details['dob'];
        if (($gender = $this->plain($details['gender'] ?? null)) !== null) {
            $student->gender = $gender;
        }
        // An ID already on the record is kept: a registration form is not
        // where somebody's identity document gets replaced.
        if ($this->plain($student->national_id) === null) {
            $student->national_id = $this->identity($details['national_id'] ?? null);
        }
        if ($this->plain($student->passport) === null) {
            $student->passport = $this->identity($details['passport'] ?? null);
        }
        $student->save();

        return $student;
    }

    private function linkGuardian(int $guardianUserId, Student $student, ?string $relationship): void
    {
        $relationship = $relationship !== null && GuardianRelationship::tryFrom($relationship) !== null
            ? $relationship
            : GuardianRelationship::Guardian->value;

        $parent = ParentGuardian::query()->where('user_id', $guardianUserId)->orderBy('id')->first()
            ?? $this->createParentFromUser($guardianUserId, $relationship);

        if ($parent === null || $student->guardians()->where('parent_guardians.id', $parent->id)->exists()) {
            return;
        }

        app(AttachGuardianAction::class)->execute(
            $student,
            $parent,
            $relationship,
            isPrimary: true,
            actorId: $guardianUserId,
        );
    }

    /** As the dual write did: a guardian row for the registering account. */
    private function createParentFromUser(int $userId, string $relationship): ?ParentGuardian
    {
        $user = DB::table('users')->where('id', $userId)->first();
        if ($user === null) {
            return null;
        }

        $parts = preg_split('/\s+/', trim((string) ($user->name ?? '')), 2) ?: [];

        return ParentGuardian::query()->create([
            'user_id' => $userId,
            'first_name' => ($parts[0] ?? '') !== '' ? $parts[0] : 'Guardian',
            'last_name' => ($parts[1] ?? '') !== '' ? $parts[1] : '—',
            'phone' => $this->plain(isset($user->phone) ? (string) $user->phone : null) ?? '—',
            'email' => $this->plain(isset($user->email) ? (string) $user->email : null) ?? "guardian-{$userId}@unification.invalid",
            'address' => $this->plain(isset($user->address) ? (string) $user->address : null) ?? '—',
            'relationship' => $relationship,
        ]);
    }

    /**
     * @return array{id: int, user_id: ?int, first_name: string, last_name: string, date_of_birth: ?string, gender: ?string, national_id: ?string, passport: ?string}
     */
    private function snapshot(Student $student): array
    {
        $student->refresh();
        $gender = $student->gender instanceof \BackedEnum ? $student->gender->value : $student->gender;

        return [
            'id' => (int) $student->id,
            'user_id' => $student->user_id !== null ? (int) $student->user_id : null,
            'first_name' => (string) $student->first_name,
            'last_name' => (string) $student->last_name,
            'date_of_birth' => $student->date_of_birth?->format('Y-m-d'),
            'gender' => $gender !== null ? (string) $gender : null,
            'national_id' => $this->plain($student->national_id),
            'passport' => $this->plain($student->passport),
        ];
    }

    private function plain(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /** ID cards and passports are stored upper-case, as registration always wrote them. */
    private function identity(?string $value): ?string
    {
        $plain = $this->plain($value);

        return $plain === null ? null : strtoupper($plain);
    }
}
