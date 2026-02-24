<?php

namespace App\Services\Enrollment;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Payment;
use App\Models\PaymentItem;
use App\Models\RegistrationStudent;
use App\Models\User;
use App\Models\UserContact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class EnrollmentService
{
    public function __construct(
        protected \App\Services\Payment\PaymentService $paymentService
    ) {}

    /**
     * Enroll adult (18+) self. Creates/links student profile to user.
     *
     * @param array{first_name: string, last_name: string, dob: string, gender?: string} $studentData
     * @param int[] $courseIds
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

        $student = $user->registrationStudentProfile;
        $idFields = $this->extractIdFields($studentData);

        if (!$student) {
            $student = RegistrationStudent::create(array_merge([
                'user_id'    => $user->id,
                'first_name' => $studentData['first_name'],
                'last_name'  => $studentData['last_name'],
                'dob'        => $dob,
                'gender'     => $studentData['gender'] ?? null,
            ], $idFields));
        } else {
            $student->update(array_merge([
                'first_name' => $studentData['first_name'],
                'last_name'  => $studentData['last_name'],
                'dob'        => $dob,
                'gender'     => $studentData['gender'] ?? null,
            ], $idFields));
        }

        $result = $this->enrollStudentInCourses($student, $courseIds, $termId, $user, $user);

        // Auto-fix default "User" name after successful enrollment
        if ($user->name === 'User') {
            $user->update(['name' => $studentData['first_name'] . ' ' . $studentData['last_name']]);
        }

        return $result;
    }

    /**
     * Enroll by parent. Creates or selects student, links guardian, enrolls.
     *
     * @param array{first_name: string, last_name: string, dob: string, gender?: string}|int $studentDataOrExistingId
     * @param array{relationship?: string} $guardianMeta
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
            $parent->update(['name' => $studentDataOrExistingId['first_name'] . ' ' . $studentDataOrExistingId['last_name']]);
        }

        return $result;
    }

    protected function createOrGetStudentForParent(User $parent, array $studentData, array $guardianMeta): RegistrationStudent
    {
        $dob = \Carbon\Carbon::parse($studentData['dob']);
        $idFields = $this->extractIdFields($studentData);

        // national_id / passport are encrypted — cannot query directly.
        // Search the parent's existing guardian students first (PHP comparison after decryption),
        // then fall back to scanning all registration_students with a user_id (smaller set).
        $student = null;
        $searchNid      = $idFields['national_id'] ?? null;
        $searchPassport = $idFields['passport']    ?? null;

        // 1. Check among parent's already-linked children (most common re-enrol case)
        $parent->loadMissing('guardianStudents');
        foreach ($parent->guardianStudents as $gs) {
            if ($searchNid      && $gs->national_id === $searchNid)      { $student = $gs; break; }
            if ($searchPassport && $gs->passport    === $searchPassport) { $student = $gs; break; }
        }

        // 2. Broader scan: students that have a user_id (child accounts) — smaller table subset
        if (! $student) {
            $candidates = RegistrationStudent::whereNotNull('user_id')->get();
            foreach ($candidates as $c) {
                if ($searchNid      && $c->national_id === $searchNid)      { $student = $c; break; }
                if ($searchPassport && $c->passport    === $searchPassport) { $student = $c; break; }
            }
        }

        if (! $student) {
            $student = RegistrationStudent::create(array_merge([
                'user_id'    => null,
                'first_name' => $studentData['first_name'],
                'last_name'  => $studentData['last_name'],
                'dob'        => $dob,
                'gender'     => $studentData['gender'] ?? null,
            ], $idFields));
        }

        // Ensure this parent is linked as a guardian (avoid duplicate pivot rows)
        if (! $parent->guardianStudents()->where('registration_students.id', $student->id)->exists()) {
            $parent->guardianStudents()->attach($student->id, [
                'relationship' => $guardianMeta['relationship'] ?? 'guardian',
                'is_primary'   => true,
            ]);
        }

        // Create a login account for the child if they don't have one yet
        $childPassword = $guardianMeta['child_password'] ?? null;
        if ($childPassword && ! $student->user_id) {
            $this->createChildUserAccount($student, $parent, $childPassword);
        }

        return $student;
    }

    /**
     * Create a User login account for a child student.
     * The child logs in with their national_id/passport.
     * Password reset OTP is sent to the parent's verified mobile.
     */
    private function createChildUserAccount(RegistrationStudent $student, User $parent, string $plainPassword): void
    {
        try {
            $childUser = User::create([
                'name'          => $student->first_name . ' ' . $student->last_name,
                'national_id'   => $student->national_id ?? $student->passport,
                'passport'      => $student->passport,
                'date_of_birth' => $student->dob,
                'gender'        => $student->gender,
                'password'      => Hash::make($plainPassword),
                'is_active'     => true,
            ]);

            // Assign student role
            $childUser->assignRole('student');

            // Link parent's verified mobile as the child's password-reset contact
            // (so reset OTPs go to the parent's phone)
            $parentMobile = $parent->contacts()
                ->where('type', 'mobile')
                ->whereNotNull('verified_at')
                ->first();

            if ($parentMobile) {
                // Only create if not already linked
                UserContact::firstOrCreate(
                    ['user_id' => $childUser->id, 'type' => 'mobile', 'value' => $parentMobile->value],
                    ['is_primary' => true, 'verified_at' => now()]
                );
            }

            // Link the user account to the student profile
            $student->update(['user_id' => $childUser->id]);

        } catch (\Throwable $e) {
            // Log but don't fail enrollment — child can still be enrolled without an account
            \Illuminate\Support\Facades\Log::error('Failed to create child user account: ' . $e->getMessage());
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
            'passport'    => $idType === 'passport'    ? (strtoupper(trim($studentData['passport']    ?? '')) ?: null) : null,
        ];
    }

    protected function ensureGuardianCanManageStudent(User $parent, int $studentId): RegistrationStudent
    {
        $student = RegistrationStudent::findOrFail($studentId);

        $isGuardian = $parent->guardianStudents()->where('registration_students.id', $studentId)->exists();
        $isSelf = $student->user_id === $parent->id;

        if (!$isGuardian && !$isSelf) {
            throw ValidationException::withMessages([
                'student' => ['You are not authorized to enroll this student.'],
            ]);
        }

        return $student;
    }

    protected function enrollStudentInCourses(
        RegistrationStudent $student,
        array $courseIds,
        ?int $termId,
        User $createdBy,
        ?User $adultSelfUser
    ): EnrollmentResult {
        $result = new EnrollmentResult();
        $courses = Course::whereIn('id', $courseIds)->get();
        $enrollmentsNeedingPayment = [];

        DB::transaction(function () use ($student, $courses, $termId, $createdBy, $adultSelfUser, $result, &$enrollmentsNeedingPayment) {
            $totalFee = 0;
            $feeEnrollments = [];

            foreach ($courses as $course) {
                // Check seat availability before enrolling
                if ($course->isFull()) {
                    throw ValidationException::withMessages([
                        'course' => ["\"{$course->title}\" is fully booked. No seats are available."],
                    ]);
                }

                $existing = CourseEnrollment::where('student_id', $student->id)
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
                                if (!$alreadyAdded) {
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
                    'student_id' => $student->id,
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
                $payment = $this->paymentService->createConsolidatedPayment($payer, $student, $feeEnrollments);

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
     * Creates RegistrationStudent + CourseEnrollment + PaymentItem records and
     * clears the pending payload from the payment row.
     *
     * Must be idempotent: if items already exist, skip silently.
     */
    public function createEnrollmentForConfirmedPayment(\App\Models\Payment $payment): void
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

        $user        = User::findOrFail($payload['user_id']);
        $flow        = $payload['flow'] ?? 'adult';
        $data        = $payload['student_data'] ?? [];
        $courseIds   = $payload['course_ids'] ?? [];
        $termId      = $payload['term_id'] ?? null;
        $studentMode = $payload['student_mode'] ?? 'new';
        $childPw     = $payload['child_password'] ?? null;

        DB::transaction(function () use ($user, $flow, $data, $courseIds, $termId, $studentMode, $childPw, $payment) {

            // ── Resolve / create student ──────────────────────────────────────
            if ($flow === 'adult') {
                $idFields = $this->extractIdFields($data);
                $student  = $user->registrationStudentProfile;
                if (! $student) {
                    $student = RegistrationStudent::create(array_merge([
                        'user_id'    => $user->id,
                        'first_name' => $data['first_name'],
                        'last_name'  => $data['last_name'],
                        'dob'        => \Carbon\Carbon::parse($data['dob']),
                        'gender'     => $data['gender'] ?? null,
                    ], $idFields));
                } else {
                    $student->update(array_merge([
                        'first_name' => $data['first_name'],
                        'last_name'  => $data['last_name'],
                        'dob'        => \Carbon\Carbon::parse($data['dob']),
                        'gender'     => $data['gender'] ?? null,
                    ], $idFields));
                }

                if ($user->name === 'User') {
                    $user->update(['name' => $data['first_name'] . ' ' . $data['last_name']]);
                }
            } else {
                // parent flow
                $guardianMeta = ['relationship' => $data['relationship'] ?? 'guardian', 'child_password' => $childPw];
                $student = $studentMode === 'existing'
                    ? $this->ensureGuardianCanManageStudent($user, (int) $data['student_id'])
                    : $this->createOrGetStudentForParent($user, $data, $guardianMeta);
            }

            // Link student_id on the payment
            $payment->update(['student_id' => $student->id]);

            // ── Create enrollments + payment items ────────────────────────────
            $courses = Course::whereIn('id', $courseIds)->get();
            foreach ($courses as $course) {
                // Skip if already enrolled (idempotency)
                $alreadyEnrolled = CourseEnrollment::where('student_id', $student->id)
                    ->where('course_id', $course->id)
                    ->whereRaw('IFNULL(term_id, 0) = ?', [$termId ?? 0])
                    ->exists();

                if ($alreadyEnrolled) {
                    continue;
                }

                $requiresApproval = (bool) ($course->requires_admin_approval ?? false);
                $feeAmount = (float) ($course->registration_fee_amount ?? $course->fee ?? 0);

                $enrollment = CourseEnrollment::create([
                    'student_id'         => $student->id,
                    'course_id'          => $course->id,
                    'term_id'            => $termId,
                    'status'             => $requiresApproval ? 'pending' : 'active',
                    'enrolled_at'        => now(),
                    'created_by_user_id' => $user->id,
                    'payment_status'     => 'confirmed',
                    'payment_id'         => $payment->id,
                ]);

                PaymentItem::create([
                    'payment_id'    => $payment->id,
                    'enrollment_id' => $enrollment->id,
                    'course_id'     => $course->id,
                    'amount'        => $feeAmount,
                ]);
            }

            // Clear the pending payload — enrollment is now in the DB
            $payment->update(['enrollment_pending_payload' => null]);
        });
    }

    protected function ensureUserHasVerifiedContact(User $user): void
    {
        if (!$user->hasVerifiedContact()) {
            throw ValidationException::withMessages([
                'contact' => ['Please verify your contact before enrolling.'],
            ]);
        }
    }
}
