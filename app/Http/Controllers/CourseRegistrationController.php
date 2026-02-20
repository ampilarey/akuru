<?php

namespace App\Http\Controllers;

use App\Http\Requests\Registration\SetPasswordRequest;
use App\Http\Requests\Registration\StartRegistrationRequest;
use App\Http\Requests\Registration\VerifyOtpRequest;
use App\Models\Course;
use App\Models\Payment;
use App\Models\RegistrationFlow;
use App\Models\UserContact;
use App\Services\AccountResolverService;
use App\Services\ContactNormalizer;
use App\Services\Enrollment\EnrollmentService;
use App\Services\OtpService;
use App\Services\Payment\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class CourseRegistrationController extends PublicRegistrationController
{
    public function __construct(
        protected AccountResolverService $accountResolver,
        protected OtpService $otpService,
        protected ContactNormalizer $normalizer,
        protected EnrollmentService $enrollmentService,
        protected PaymentService $paymentService
    ) {}

    /** New checkout start page — replaces the old register form. */
    public function checkout(Course $course): View
    {
        if (! $course->is_enrollment_open) {
            return view('courses.register', [
                'course' => $course,
                'fee'    => $course->getRegistrationFeeAmount(),
                'closed' => true,
            ]);
        }

        return view('courses.checkout', [
            'course' => $course,
            'fee'    => $course->getRegistrationFeeAmount(),
        ]);
    }

    /** Password login at checkout — for returning users who know their password. */
    public function checkoutLogin(Request $request, Course $course): RedirectResponse
    {
        $request->validate([
            'login_contact' => ['required', 'string'],
            'password'      => ['required', 'string'],
        ]);

        $raw        = trim($request->input('login_contact'));
        $isEmail    = str_contains($raw, '@');
        $type       = $isEmail ? 'email' : 'mobile';
        $normalized = $this->normalizer->normalize($type, $raw);

        // Find contact → user
        $contact = \App\Models\UserContact::where('type', $type)
            ->where('value', $normalized)
            ->first();

        $user = $contact?->user;

        // Generic error — never reveal whether account exists
        $fail = fn() => back()
            ->withInput($request->only('login_contact'))
            ->withErrors(['login_contact' => 'Incorrect contact or password. Try OTP login instead.']);

        if (! $user || ! $user->password || ! Hash::check($request->input('password'), $user->password)) {
            return $fail();
        }

        Auth::login($user);

        // Carry course into session for the continue form
        session([
            'pending_course_id'          => $course->id,
            'pending_selected_course_ids' => [$course->id],
            'pending_term_id'            => null,
        ]);

        // Pre-select flow based on existing profile
        $flow = $user->registrationStudentProfile ? 'adult' : ($user->guardianStudents()->exists() ? 'parent' : null);
        if ($flow) {
            session(['checkout_flow' => $flow]);
        }

        return redirect()->route('courses.register.continue');
    }

    /** Legacy entry point — kept for backward compatibility. */
    public function show(Course $course): View
    {
        return view('courses.register', [
            'course' => $course,
            'fee'    => $course->getRegistrationFeeAmount(),
        ]);
    }

    public function start(StartRegistrationRequest $request): RedirectResponse
    {
        $type = $request->input('contact_type');
        $value = $request->input('contact_value');
        $normalized = $this->normalizer->normalize($type, $value);

        [$user, $contact, $isNew] = $this->accountResolver->resolveOrCreateByContact($type, $normalized);

        $this->otpService->send($contact, 'verify_contact');

        session([
            'pending_contact_id'          => $contact->id,
            'pending_user_id'             => $user->id,
            'pending_course_id'           => $request->input('course_id'),
            'pending_selected_course_ids' => $request->input('course_id') ? [(int) $request->input('course_id')] : [],
            'pending_term_id'             => $request->input('term_id'),
            'checkout_flow'               => $request->input('flow_type'), // adult / parent
        ]);

        return redirect()->route('courses.register.otp')
            ->with('success', 'Verification code sent. Please check your ' . ($type === 'mobile' ? 'phone' : 'email') . '.');
    }

    public function otpForm(Request $request): View|RedirectResponse
    {
        if (!session('pending_contact_id')) {
            return redirect()->route('public.courses.index')->with('error', 'Session expired. Please start again.');
        }

        $contact = UserContact::find(session('pending_contact_id'));
        if (!$contact) {
            return redirect()->route('public.courses.index')->with('error', 'Session expired.');
        }

        return view('courses.register-otp', ['contact' => $contact]);
    }

    public function verify(VerifyOtpRequest $request): RedirectResponse
    {
        $contactId = session('pending_contact_id');
        if (!$contactId) {
            return redirect()->route('public.courses.index')->with('error', 'Session expired.');
        }

        $contact = UserContact::findOrFail($contactId);
        $this->otpService->verify($contact, 'verify_contact', $request->input('code'));

        $contact->update(['verified_at' => now()]);

        $user = $contact->user;

        if ($user->force_password_change) {
            return redirect()->route('courses.register.set-password');
        }

        Auth::login($user);
        return redirect()->route('courses.register.continue');
    }

    public function passwordForm(Request $request): View|RedirectResponse
    {
        if (!session('pending_contact_id') || !session('pending_user_id')) {
            return redirect()->route('public.courses.index')->with('error', 'Session expired.');
        }

        return view('courses.register-set-password');
    }

    public function setPassword(SetPasswordRequest $request): RedirectResponse
    {
        $userId = session('pending_user_id');
        if (!$userId) {
            return redirect()->route('public.courses.index')->with('error', 'Session expired.');
        }

        $user = \App\Models\User::findOrFail($userId);
        $user->update([
            'password' => Hash::make($request->input('password')),
            'force_password_change' => false,
        ]);

        Auth::login($user);
        return redirect()->route('courses.register.continue');
    }

    public function continueForm(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            $userId = session('pending_user_id');
            if (!$userId) {
                return redirect()->route('public.courses.index')->with('error', 'Session expired.');
            }
            $user = \App\Models\User::findOrFail($userId);
            Auth::login($user);
        }

        if (!$user->hasVerifiedContact()) {
            return redirect()->route('public.courses.index')->with('error', 'Please verify your contact first.');
        }

        $courseIds = session('pending_selected_course_ids', []);
        $courses = Course::whereIn('id', $courseIds)->get();
        if ($courses->isEmpty() && session('pending_course_id')) {
            $course = Course::find(session('pending_course_id'));
            if ($course) {
                $courses = collect([$course]);
                $courseIds = [$course->id];
            }
        }

        if ($courses->isEmpty()) {
            return redirect()->route('public.courses.index')->with('error', 'No course selected. Please start registration from a course page.');
        }

        // Pre-fill existing student profile for returning users
        $existingProfile = $user->registrationStudentProfile;
        $user->loadMissing('guardianStudents');

        // Default flow: honour the choice made on checkout start, then fall back to profile
        $checkoutFlow = session('checkout_flow'); // 'adult' or 'parent' set at checkout start
        $defaultFlow  = $checkoutFlow
            ?? ($existingProfile ? 'adult' : ($user->guardianStudents->isNotEmpty() ? 'parent' : 'adult'));

        return view('courses.register-continue', [
            'user'            => $user,
            'courses'         => $courses,
            'courseIds'       => $courseIds,
            'termId'          => session('pending_term_id'),
            'existingProfile' => $existingProfile,
            'defaultFlow'     => $defaultFlow,
        ]);
    }

    public function enroll(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            $userId = session('pending_user_id');
            if (!$userId) {
                return redirect()->route('public.courses.index')->with('error', 'Session expired. Please start again.');
            }
            $user = \App\Models\User::findOrFail($userId);
            Auth::login($user);
        }
        if (!$user->hasVerifiedContact()) {
            return redirect()->route('public.courses.index')->with('error', 'Please verify your contact first.');
        }

        $flow      = $request->input('flow', 'adult');
        $courseIds = $request->input('course_ids');
        $termId    = $request->input('term_id');

        // Strict validation: course_ids must be explicitly present in the request
        $courseValidator = Validator::make($request->all(), [
            'course_ids'   => ['required', 'array', 'min:1'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
        ]);

        if ($courseValidator->fails()) {
            return back()->withErrors($courseValidator)->withInput();
        }

        $courseIds = $courseValidator->validated()['course_ids'];
        // term_id is optional; treat empty string as null
        $termId = ($request->input('term_id') !== '' && $request->input('term_id') !== null)
            ? (int) $request->input('term_id')
            : null;

        $data = $this->validateEnrollRequest($request, $flow);
        if ($data instanceof RedirectResponse) {
            return $data;
        }

        try {
            if ($flow === 'parent') {
                // Read student_mode from request directly — it's not a validated field
                if ($request->input('student_mode') === 'new') {
                    $studentData = [
                        'first_name'  => $data['first_name'],
                        'last_name'   => $data['last_name'],
                        'dob'         => $data['dob'],
                        'gender'      => $data['gender'] ?? null,
                        'id_type'     => $data['id_type'] ?? null,
                        'national_id' => $data['national_id'] ?? null,
                        'passport'    => $data['passport'] ?? null,
                    ];
                    $guardianMeta = ['relationship' => $data['relationship'] ?? 'guardian'];
                    $result = $this->enrollmentService->enrollByParent($user, $studentData, $courseIds, $termId, $guardianMeta);
                } else {
                    $result = $this->enrollmentService->enrollByParent($user, (int) $data['student_id'], $courseIds, $termId);
                }
            } else {
                $studentData = [
                    'first_name'  => $data['first_name'],
                    'last_name'   => $data['last_name'],
                    'dob'         => $data['dob'],
                    'gender'      => $data['gender'] ?? null,
                    'id_type'     => $data['id_type'] ?? null,
                    'national_id' => $data['national_id'] ?? null,
                    'passport'    => $data['passport'] ?? null,
                ];
                $result = $this->enrollmentService->enrollAdultSelf($user, $studentData, $courseIds, $termId);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        if ($result->hasPaymentsPending()) {
            $payment = $result->getConsolidatedPayment();
            $init = $this->paymentService->initiatePayment($payment, [
                'return_url' => route('payments.bml.return') . '?ref=' . $payment->merchant_reference,
            ]);
            if ($init->success && $init->redirectUrl) {
                session(['pending_payment_ref' => $payment->merchant_reference]);
                return redirect()->away($init->redirectUrl);
            }
            $paymentError = $init->error ?? 'Payment initiation failed.';
            if (stripos($paymentError, 'unauthorized') !== false) {
                $paymentError = 'Payment service is not available right now. Your registration was saved. Please contact us to complete payment, or try again later.';
            }
            if (stripos($paymentError, 'different mobile') !== false || stripos($paymentError, 'already be linked') !== false) {
                $paymentError = 'This number or account may already be linked to a payment. Please use a different mobile number, or contact us to complete payment.';
            }
            return back()->withErrors(['payment' => $paymentError])->withInput();
        }

        $this->clearPendingSession();
        return redirect()->route('courses.register.complete')->with('enrollments', $result->allEnrollments());
    }

    public function complete(Request $request): View|RedirectResponse
    {
        $enrollments = session('enrollments');
        $paymentRef = session('pending_payment_ref');

        if (!$enrollments && !$paymentRef) {
            return redirect()->route('public.courses.index')->with('error', 'No enrollment data found.');
        }

        $enrollments = $enrollments ?? [];
        $paymentIdForStatus = null;
        if ($paymentRef) {
            $payment = \App\Models\Payment::where('merchant_reference', $paymentRef)->first();
            if ($payment) {
                $paymentIdForStatus = $payment->id;
                foreach ($payment->items as $item) {
                    $enrollments[] = $item->enrollment;
                }
            }
        }

        $this->clearPendingSession();

        return view('courses.register-complete', [
            'enrollments' => collect($enrollments)->unique('id')->values(),
            'paymentRef' => $paymentRef,
            'paymentIdForStatus' => $paymentIdForStatus,
        ]);
    }

    /**
     * Validate enroll form without using Form Request (avoids any authorization check).
     * Returns validated data array or a RedirectResponse on failure.
     */
    protected function validateEnrollRequest(Request $request, string $flow): array|RedirectResponse
    {
        // course_ids and term_id are validated upstream in enroll() before this method is called.
        $rules = [];

        if ($flow === 'parent') {
            if ($request->input('student_mode') === 'new') {
                $rules['first_name']   = ['required', 'string', 'max:100'];
                $rules['last_name']    = ['required', 'string', 'max:100'];
                $rules['dob']          = ['required', 'date', 'before:today'];
                $rules['gender']       = ['nullable', 'in:male,female'];
                $rules['relationship'] = ['nullable', 'in:father,mother,guardian,other'];
                $rules['id_type']      = ['required', 'in:national_id,passport'];
                $rules['national_id']  = ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z][0-9]{5,9}$/'];
                $rules['passport']     = ['nullable', 'string', 'max:20'];
            } else {
                $rules['student_id'] = ['required', 'exists:registration_students,id'];
            }
        } else {
            $rules['first_name']  = ['required', 'string', 'max:100'];
            $rules['last_name']   = ['required', 'string', 'max:100'];
            $rules['dob']         = ['required', 'date', 'before:today'];
            $rules['gender']      = ['nullable', 'in:male,female'];
            $rules['id_type']     = ['required', 'in:national_id,passport'];
            $rules['national_id'] = ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z][0-9]{5,9}$/'];
            $rules['passport']    = ['nullable', 'string', 'max:20'];
        }

        $validator = Validator::make($request->all(), $rules);

        // Require whichever ID type was selected
        $validator->after(function ($v) use ($request) {
            $idType = $request->input('id_type');
            if ($idType === 'national_id' && empty(trim((string) $request->input('national_id')))) {
                $v->errors()->add('national_id', 'Please enter your Maldivian ID card number.');
            }
            if ($idType === 'passport' && empty(trim((string) $request->input('passport')))) {
                $v->errors()->add('passport', 'Please enter your passport number.');
            }
        });

        if ($flow === 'adult') {
            $validator->after(function ($validator) use ($request) {
                $dob = $request->input('dob');
                if ($dob && \Carbon\Carbon::parse($dob)->age < 18) {
                    $validator->errors()->add('dob', 'You must be 18 or older to enroll yourself.');
                }
            });
        }

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        return $validator->validated();
    }

    /**
     * Resume a registration that may have lost session state.
     * Accepts ?flow=<uuid> or finds the latest active flow for the authenticated user.
     */
    public function resume(Request $request): RedirectResponse
    {
        $user = $request->user();

        $flowUuid = $request->query('flow');
        $flow = null;

        if ($flowUuid) {
            $flow = RegistrationFlow::findResumable($flowUuid, $user?->id);
        } elseif ($user) {
            $flow = RegistrationFlow::latestActiveForUser($user->id);
        }

        if (! $flow) {
            return redirect()->route('public.courses.index')
                ->with('error', 'No active registration found. Please start again from a course page.');
        }

        // Re-hydrate session from DB-backed payload
        $payload = $flow->payload ?? [];
        if (! empty($payload['course_ids'])) {
            session([
                'pending_selected_course_ids' => $payload['course_ids'],
                'pending_term_id'             => $payload['term_id'] ?? null,
            ]);
        }

        if ($flow->user_id && ! $user) {
            $user = \App\Models\User::find($flow->user_id);
            if ($user) {
                Auth::login($user);
            }
        }

        if (! $user || ! $user->hasVerifiedContact()) {
            session(['pending_contact_id' => $flow->contact_id, 'pending_user_id' => $flow->user_id]);
            return redirect()->route('courses.register.otp')
                ->with('info', 'Please verify your contact to continue.');
        }

        return redirect()->route('courses.register.continue')
            ->with('info', 'Welcome back! Please complete your enrollment.');
    }

    /**
     * Retry payment for an existing enrollment whose payment failed or expired.
     * Accepts ?ref=<merchant_reference> or ?flow=<uuid>.
     */
    public function retryPayment(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('public.courses.index')->with('error', 'Please log in to retry payment.');
        }

        $ref = $request->query('ref');
        $payment = null;

        if ($ref) {
            $payment = Payment::where('merchant_reference', $ref)
                ->orWhere('local_id', $ref)
                ->first();
        }

        if (! $payment) {
            // Try via flow uuid
            $flowUuid = $request->query('flow');
            if ($flowUuid) {
                $flow = RegistrationFlow::findResumable($flowUuid, $user->id);
                if ($flow?->payment_id) {
                    $payment = Payment::find($flow->payment_id);
                }
            }
        }

        if (! $payment) {
            return redirect()->route('public.courses.index')->with('error', 'Payment not found.');
        }

        // If already confirmed, go straight to complete
        if ($payment->isConfirmed()) {
            return redirect()->route('courses.register.complete')
                ->with('success', 'Your payment is already confirmed!');
        }

        // For failed/expired payments, try to re-initiate
        $init = $this->paymentService->initiatePayment($payment, [
            'return_url' => route('payments.bml.return') . '?ref=' . $payment->merchant_reference,
        ]);

        if ($init->success && $init->redirectUrl) {
            session(['pending_payment_ref' => $payment->merchant_reference]);
            return redirect()->away($init->redirectUrl);
        }

        $error = $init->error ?? 'Payment initiation failed.';
        return redirect()->route('public.courses.index')->with('error', $error);
    }

    protected function clearPendingSession(): void
    {
        session()->forget([
            'pending_contact_id', 'pending_user_id', 'pending_course_id',
            'pending_selected_course_ids', 'pending_term_id', 'pending_flow',
            'pending_payment_ref', 'enrollments',
        ]);
    }
}
