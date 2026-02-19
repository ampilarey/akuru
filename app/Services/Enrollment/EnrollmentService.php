<?php

namespace App\Services\Enrollment;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Payment;
use App\Models\PaymentItem;
use App\Models\RegistrationStudent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
        if (!$student) {
            $student = RegistrationStudent::create([
                'user_id' => $user->id,
                'first_name' => $studentData['first_name'],
                'last_name' => $studentData['last_name'],
                'dob' => $dob,
                'gender' => $studentData['gender'] ?? null,
            ]);
        } else {
            $student->update([
                'first_name' => $studentData['first_name'],
                'last_name' => $studentData['last_name'],
                'dob' => $dob,
                'gender' => $studentData['gender'] ?? null,
            ]);
        }

        return $this->enrollStudentInCourses($student, $courseIds, $termId, $user, $user);
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

        return $this->enrollStudentInCourses($student, $courseIds, $termId, $parent, null);
    }

    protected function createOrGetStudentForParent(User $parent, array $studentData, array $guardianMeta): RegistrationStudent
    {
        $dob = \Carbon\Carbon::parse($studentData['dob']);

        $student = RegistrationStudent::create([
            'user_id' => null,
            'first_name' => $studentData['first_name'],
            'last_name' => $studentData['last_name'],
            'dob' => $dob,
            'gender' => $studentData['gender'] ?? null,
        ]);

        $parent->guardianStudents()->attach($student->id, [
            'relationship' => $guardianMeta['relationship'] ?? 'guardian',
            'is_primary' => true,
        ]);

        return $student;
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

    protected function ensureUserHasVerifiedContact(User $user): void
    {
        if (!$user->hasVerifiedContact()) {
            throw ValidationException::withMessages([
                'contact' => ['Please verify your contact before enrolling.'],
            ]);
        }
    }
}
