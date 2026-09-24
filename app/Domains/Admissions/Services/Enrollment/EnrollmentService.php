<?php

namespace App\Domains\Admissions\Services\Enrollment;

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Models\PaymentItem;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\RegisterCourseStudentAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class EnrollmentService
{
    public function __construct(
        protected \App\Domains\Finance\Services\Payment\PaymentService $paymentService
    ) {}

    /**
     * Enroll adult (18+) self. Creates/links student profile to user.
     *
     * @param  array{first_name: string, last_name: string, dob: string, gender?: string}  $studentData
     * @param  int[]  $courseIds
     */
    public function enrollAdultSelf(User $user, array $studentData, array $courseIds, ?int $termId = null): EnrollmentResult
    {
        $this->ensureUserHasVerifiedContact($user);

        $dob = \Carbon\Carbon::parse($studentData['dob']);
        if ($dob->age < 18) {
            throw ValidationException::withMessages([
                'dob' => ['You must be 18 or older to enroll yourself. Please use the parent/guardian flow.'],
            ]);
        }

        $student = app(RegisterCourseStudentAction::class)->forSelf(
            $user->id,
            array_merge($studentData, $this->extractIdFields($studentData)),
        );

        $result = $this->enrollStudentInCourses($student, $courseIds, $termId, $user, $user);

        // Auto-fix default "User" name after successful enrollment
        if ($user->name === 'User') {
            $user->update(['name' => $studentData['first_name'].' '.$studentData['last_name']]);
        }

        return $result;
    }

    /**
     * Enroll by parent. Creates or selects student, links guardian, enrolls.
     *
     * @param  array{first_name: string, last_name: string, dob: string, gender?: string}|int  $studentDataOrExistingId
     * @param  array{relationship?: string}  $guardianMeta
     */
    public function enrollByParent(
        User $parent,
        array|int $studentDataOrExistingId,
        array $courseIds,
        ?int $termId,
        array $guardianMeta = []
    ): EnrollmentResult {
        $this->ensureUserHasVerifiedContact($parent);

        $student = is_array($studentDataOrExistingId)
            ? $this->createOrGetStudentForParent($parent, $studentDataOrExistingId, $guardianMeta)
            : $this->ensureGuardianCanManageStudent($parent, $studentDataOrExistingId);

        $result = $this->enrollStudentInCourses($student, $courseIds, $termId, $parent, null);

        // Auto-fix default "User" name after successful enrollment (parent flow)
        if ($parent->name === 'User' && is_array($studentDataOrExistingId)) {
            $parent->update(['name' => $studentDataOrExistingId['first_name'].' '.$studentDataOrExistingId['last_name']]);
        }

        return $result;
    }

    /**
     * @return array{id: int, user_id: ?int, first_name: string, last_name: string, date_of_birth: ?string, gender: ?string, national_id: ?string, passport: ?string}
     */
    protected function createOrGetStudentForParent(User $parent, array $studentData, array $guardianMeta): array
    {
        $student = app(RegisterCourseStudentAction::class)->forChild(
            $parent->id,
            array_merge($studentData, $this->extractIdFields($studentData)),
            $guardianMeta['relationship'] ?? null,
        );

        // Create a login account for the child if they don't have one yet
        $childPassword = $guardianMeta['child_password'] ?? null;
        if ($childPassword && ! $student['user_id']) {
            $this->createChildUserAccount($student, $parent, $childPassword);
        }

        return $student;
    }

    /**
     * Create a User login account for a child student.
     * The child logs in with their national_id/passport.
     *
     * Password-reset codes reach the parent without copying anything: the
     * forgot-password page finds the child's student record, then its
     * guardian, then the guardian's verified mobile.
     *
     * This used to copy the parent's mobile onto the child as a contact. A
     * number belongs to one account (`user_contacts_type_value_unique`), so
     * for every parent who verified by mobile the copy failed, the exception
     * was logged and swallowed, the child's login was left behind unlinked,
     * and the family was never told (found by the Deploy 3 slice 2 tests).
     *
     * @param  array{id: int, first_name: string, last_name: string, date_of_birth: ?string, gender: ?string, national_id: ?string, passport: ?string}  $student
     */
    private function createChildUserAccount(array $student, User $parent, string $plainPassword): void
    {
        try {
            DB::transaction(function () use ($student, $plainPassword): void {
                $childUser = User::create([
                    'name' => $student['first_name'].' '.$student['last_name'],
                    'national_id' => $student['national_id'] ?? $student['passport'],
                    'passport' => $student['passport'],
                    'date_of_birth' => $student['date_of_birth'],
                    'gender' => $student['gender'],
                    'password' => Hash::make($plainPassword),
                    'is_active' => true,
                ]);

                $childUser->assignRole('student');

                app(RegisterCourseStudentAction::class)->linkAccount($student['id'], $childUser->id);
            });
        } catch (\Throwable $e) {
            // Log but don't fail enrollment — child can still be enrolled without an account
            \Illuminate\Support\Facades\Log::error('Failed to create child user account: '.$e->getMessage());
        }
    }

    /**
     * Extract national_id / passport from student data based on id_type selector.
     */
    private function extractIdFields(array $studentData): array
    {
        $idType = $studentData['id_type'] ?? null;

        return [
            'national_id' => $idType === 'national_id' ? (strtoupper(trim($studentData['national_id'] ?? '')) ?: null) : null,
            'passport' => $idType === 'passport' ? (strtoupper(trim($studentData['passport'] ?? '')) ?: null) : null,
        ];
    }

    /**
     * `$studentId` is a `students.id` since Deploy 3 slice 2.
     *
     * @return array{id: int, user_id: ?int, first_name: string, last_name: string, date_of_birth: ?string, gender: ?string, national_id: ?string, passport: ?string}
     */
    protected function ensureGuardianCanManageStudent(User $parent, int $studentId): array
    {
        $student = app(RegisterCourseStudentAction::class)->forActor($parent->id, $studentId);

        if ($student === null) {
            throw ValidationException::withMessages([
                'student' => ['You are not authorized to enroll this student.'],
            ]);
        }

        return $student;
    }

    /**
     * @param  array{id: int}  $student
     */
    protected function enrollStudentInCourses(
        array $student,
        array $courseIds,
        ?int $termId,
        User $createdBy,
        ?User $adultSelfUser
    ): EnrollmentResult {
        $studentId = $student['id'];

        $result = new EnrollmentResult;
        $courses = Course::whereIn('id', $courseIds)->get();
        $enrollmentsNeedingPayment = [];

        DB::transaction(function () use ($studentId, $courses, $termId, $createdBy, $adultSelfUser, $result, &$enrollmentsNeedingPayment) {
            $totalFee = 0;
            $feeEnrollments = [];

            foreach ($courses as $course) {
                // Check seat availability before enrolling
                if ($course->isFull()) {
                    throw ValidationException::withMessages([
                        'course' => ["\"{$course->title}\" is fully booked. No seats are available."],
                    ]);
                }

                // Deploy 3 slice 1: one enrolment per unified student.
                $existing = CourseEnrollment::where('unified_student_id', $studentId)
                    ->where('course_id', $course->id)
                    ->whereRaw('IFNULL(term_id, 0) = ?', [$termId ?? 0])
                    ->first();

                if ($existing) {
                    $result->existingEnrollments[] = $existing;
                    // If existing enrollment has pending payment, ensure we redirect to BML
                    if ($existing->payment_status === 'pending') {
                        $feeAmount = $course->getRegistrationFeeAmount();
                        $addToFeeEnrollments = $feeAmount > 0;

                        if ($existing->payment_id) {
                            $existingPayment = Payment::find($existing->payment_id);
                            if ($existingPayment && in_array($existingPayment->status, ['initiated', 'pending'], true)) {
                                $alreadyAdded = collect($result->paymentsInitiated)->contains('id', $existingPayment->id);
                                if (! $alreadyAdded) {
                                    $result->paymentsInitiated[] = $existingPayment;
                                    $addToFeeEnrollments = false;
                                }
                            }
                        }

                        // No usable payment: create one (orphaned or previous payment failed/expired)
                        if ($addToFeeEnrollments) {
                            $feeEnrollments[] = ['enrollment' => $existing, 'course' => $course, 'amount' => $feeAmount];
                            $totalFee += $feeAmount;
                        }
                    }

                    continue;
                }

                $feeAmount = (float) ($course->registration_fee_amount ?? 0);
                if ($feeAmount <= 0 && (float) ($course->fee ?? 0) > 0) {
                    $feeAmount = (float) $course->fee;
                }
                $paymentStatus = $feeAmount > 0 ? 'pending' : 'not_required';

                $enrollment = CourseEnrollment::create([
                    'unified_student_id' => $studentId,
                    'course_id' => $course->id,
                    'term_id' => $termId,
                    'status' => 'pending',
                    'enrolled_at' => null,
                    'created_by_user_id' => $createdBy->id,
                    'payment_status' => $paymentStatus,
                    'payment_id' => null,
                ]);

                $result->createdEnrollments[] = $enrollment;

                if ($feeAmount > 0) {
                    $totalFee += $feeAmount;
                    $feeEnrollments[] = ['enrollment' => $enrollment, 'course' => $course, 'amount' => $feeAmount];
                }
            }

            if (count($feeEnrollments) > 0 && $totalFee > 0) {
                $payer = $adultSelfUser ?? $createdBy;
                $payment = $this->paymentService->createConsolidatedPayment($payer, $studentId, $feeEnrollments);

                foreach ($feeEnrollments as $fe) {
                    $fe['enrollment']->update(['payment_id' => $payment->id]);
                }

                $result->paymentsInitiated[] = $payment;
            }
        });

        return $result;
    }

    /**
     * Called after BML confirms a payment that used the deferred-enrollment flow.
     * Creates the student (on `students`) + CourseEnrollment + PaymentItem records and
     * clears the pending payload from the payment row.
     *
     * Must be idempotent: if items already exist, skip silently.
     */
    public function createEnrollmentForConfirmedPayment(\App\Domains\Finance\Models\Payment $payment): void
    {
        $payload = $payment->enrollment_pending_payload;
        if (! $payload) {
            return;
        }

        // Already finalised by a previous call (webhook race-condition guard)
        if ($payment->items()->exists()) {
            $payment->update(['enrollment_pending_payload' => null]);

            return;
        }

        $user = User::findOrFail($payload['user_id']);
        $flow = $payload['flow'] ?? 'adult';
        $data = $payload['student_data'] ?? [];
        $courseIds = $payload['course_ids'] ?? [];
        $termId = $payload['term_id'] ?? null;
        $studentMode = $payload['student_mode'] ?? 'new';
        $childPw = $payload['child_password'] ?? null;

        DB::transaction(function () use ($user, $flow, $data, $courseIds, $termId, $studentMode, $childPw, $payment) {

            // ── Resolve / create student ──────────────────────────────────────
            if ($flow === 'adult') {
                $student = app(RegisterCourseStudentAction::class)->forSelf(
                    $user->id,
                    array_merge($data, $this->extractIdFields($data)),
                );

                if ($user->name === 'User') {
                    $user->update(['name' => $data['first_name'].' '.$data['last_name']]);
                }
            } else {
                // parent flow
                $guardianMeta = ['relationship' => $data['relationship'] ?? 'guardian', 'child_password' => $childPw];
                $student = $studentMode === 'existing'
                    ? $this->ensureGuardianCanManageStudent($user, (int) $data['student_id'])
                    : $this->createOrGetStudentForParent($user, $data, $guardianMeta);
            }

            // Link the student on the payment
            $payment->update(['unified_student_id' => $student['id']]);

            // ── Create enrollments + payment items ────────────────────────────
            $courses = Course::whereIn('id', $courseIds)->get();
            foreach ($courses as $course) {
                // Skip if already enrolled (idempotency)
                $alreadyEnrolled = CourseEnrollment::where('unified_student_id', $student['id'])
                    ->where('course_id', $course->id)
                    ->whereRaw('IFNULL(term_id, 0) = ?', [$termId ?? 0])
                    ->exists();

                if ($alreadyEnrolled) {
                    continue;
                }

                $requiresApproval = (bool) ($course->requires_admin_approval ?? false);
                $feeAmount = (float) ($course->registration_fee_amount ?? $course->fee ?? 0);

                $enrollment = CourseEnrollment::create([
                    'unified_student_id' => $student['id'],
                    'course_id' => $course->id,
                    'term_id' => $termId,
                    'status' => $requiresApproval ? 'pending' : 'active',
                    'enrolled_at' => now(),
                    'created_by_user_id' => $user->id,
                    'payment_status' => 'confirmed',
                    'payment_id' => $payment->id,
                ]);

                PaymentItem::create([
                    'payment_id' => $payment->id,
                    'enrollment_id' => $enrollment->id,
                    'course_id' => $course->id,
                    'amount' => $feeAmount,
                ]);
            }

            // Clear the pending payload — enrollment is now in the DB
            $payment->update(['enrollment_pending_payload' => null]);
        });
    }

    protected function ensureUserHasVerifiedContact(User $user): void
    {
        if (! $user->hasVerifiedContact()) {
            throw ValidationException::withMessages([
                'contact' => ['Please verify your contact before enrolling.'],
            ]);
        }
    }
}
