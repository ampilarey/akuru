<?php

use App\Domains\Courses\Actions\AuthorizeLessonAccessAction;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\Lesson;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Models\PaymentItem;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * A correctly signed webhook turns money into access — the whole chain, under
 * the configuration that will actually be live.
 *
 * ## The gap this fills
 *
 * Both halves were already tested, and **only the halves**:
 *
 *  - `BmlWebhookSignatureTest` posts a correctly signed callback and asserts the
 *    **payment** is confirmed. It stops there.
 *  - `BmlWebhookTest` asserts the **enrolment** activates — but posts **no
 *    signature at all**, relying on `BML_WEBHOOK_ALLOW_UNSIGNED=true` in
 *    `phpunit.xml`.
 *
 * So the combination that goes live — *secret configured, unsigned refused,
 * correctly signed callback arrives, student gets in* — was covered by neither.
 * That is the exact configuration `docs/OWNER_ACTIONS.md` item 2 asks the owner
 * to switch on, and nothing had ever run it end to end.
 *
 * It is the same shape as three other findings this session: two halves each
 * covered, the join between them untested.
 *
 * ## Why it asserts a lesson opens
 *
 * Rule 12 makes the webhook the authority, and `PaymentService` comments that
 * "money→access happens HERE". Asserting `status = active` tests a column;
 * asserting the student can open a lesson they were locked out of a moment ago
 * tests the sentence.
 */
function paidCourseAwaitingPayment(): array
{
    $user = User::factory()->create();

    $pupil = makeStudent(['first_name' => 'Paying', 'last_name' => 'Family']);
    $pupil->forceFill(['user_id' => $user->id])->save();

    $course = Course::query()->create([
        'course_category_id' => DB::table('course_categories')->insertGetId([
            'name' => 'Paid', 'slug' => 'paid-'.Str::random(6), 'order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]),
        'title' => 'Paid course',
        'slug' => 'paid-course-'.Str::random(6),
        'short_desc' => 'x', 'body' => 'y', 'cover_image' => '',
        'workflow_status' => 'published', 'status' => 'open',
        'registration_fee_amount' => 500,
        'requires_admin_approval' => false,
    ]);

    $moduleId = DB::table('course_modules')->insertGetId([
        'course_id' => $course->id, 'title' => 'Module', 'position' => 1,
        'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $lesson = Lesson::query()->create([
        'course_id' => $course->id,
        'course_module_id' => $moduleId,
        'title' => 'Paid lesson',
        'slug' => 'paid-lesson-'.Str::random(6),
        'position' => 1,
        'status' => 'draft',
    ]);

    app(App\Domains\Courses\Actions\SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'text',
        'position' => 1,
        'data' => ['body' => 'Paid content'],
    ]);

    app(App\Domains\Courses\Actions\PublishLessonAction::class)->execute($lesson->fresh());

    $enrollment = CourseEnrollment::query()->create([
        'unified_student_id' => $pupil->id,
        'course_id' => $course->id,
        'status' => 'pending',
        'payment_status' => 'pending',
    ]);

    $reference = 'AKURU-SIGNED-'.Str::upper(Str::random(6));

    $payment = Payment::query()->create([
        'user_id' => $user->id,
        'unified_student_id' => $pupil->id,
        'course_id' => $course->id,
        'amount' => 500,
        'currency' => 'MVR',
        'status' => 'pending',
        'provider' => 'bml',
        'merchant_reference' => $reference,
        'local_id' => $reference,
    ]);

    PaymentItem::query()->create([
        'payment_id' => $payment->id,
        'enrollment_id' => $enrollment->id,
        'course_id' => $course->id,
        'amount' => 500,
    ]);

    $enrollment->update(['payment_id' => $payment->id]);

    return [$user, $lesson, $enrollment, $payment, $reference];
}

it('lets a student in only after a correctly signed webhook says they paid', function () {
    // Production-shaped: a secret is set and unsigned callbacks are refused.
    // The suite's own phpunit.xml sets BML_WEBHOOK_ALLOW_UNSIGNED=true, which is
    // why every other webhook test can skip the header — and why this one has
    // to turn it off to mean anything.
    config(['bml.webhook_secret' => 'a-real-secret', 'bml.webhook_allow_unsigned' => false]);

    [$user, $lesson, $enrollment, $payment, $reference] = paidCourseAwaitingPayment();

    // Locked out before the money arrives. Without this the test could pass on
    // a system that lets everybody in.
    expect(fn () => app(AuthorizeLessonAccessAction::class)->execute((int) $lesson->id, $user))
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);

    $body = json_encode(['localId' => $reference, 'state' => 'success']);

    test()->call(
        'POST',
        url('/webhooks/bml'),
        [], [], [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-BML-Signature' => hash_hmac('sha256', $body, 'a-real-secret'),
        ],
        $body,
    )->assertStatus(200);

    expect($payment->fresh()->status)->toBe('confirmed')
        ->and($enrollment->fresh()->status)->toBe('active')
        ->and($enrollment->fresh()->payment_status)->toBe('confirmed');

    // The sentence, not the column: the lesson opens now.
    $access = app(AuthorizeLessonAccessAction::class)->execute((int) $lesson->id, $user);

    expect($access['enrollment']?->id)->toBe($enrollment->id);
});

it('leaves the student locked out when the signature is wrong', function () {
    config(['bml.webhook_secret' => 'a-real-secret', 'bml.webhook_allow_unsigned' => false]);

    [$user, $lesson, $enrollment, $payment, $reference] = paidCourseAwaitingPayment();

    $body = json_encode(['localId' => $reference, 'state' => 'success']);

    test()->call(
        'POST',
        url('/webhooks/bml'),
        [], [], [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-BML-Signature' => hash_hmac('sha256', $body, 'not-the-secret'),
        ],
        $body,
    );

    expect($payment->fresh()->status)->toBe('pending')
        ->and($enrollment->fresh()->status)->toBe('pending');

    expect(fn () => app(AuthorizeLessonAccessAction::class)->execute((int) $lesson->id, $user))
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});
