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
use Illuminate\Support\Facades\RateLimiter;
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
    public function checkout(Course $course): View|\Illuminate\Http\RedirectResponse
    {
        if (! $course->is_enrollment_open) {
            return view('courses.register', [
                'course' => $course,
                'fee'    => $course->getRegistrationFeeAmount(),
                'closed' => true,
            ]);
        }

        // If the user is already logged in, skip the login/register step entirely
        if (auth()->check() && auth()->user()->hasVerifiedContact()) {
            session([
                'pending_selected_course_ids' => [$course->id],
                'pending_term_id'             => null,
                'checkout_flow'               => 'adult',
            ]);
            return redirect()->route('courses.register.continue');
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

        // Brute-force protection: max 10 attempts per contact+IP per 15 minutes
        $throttleKey = 'checkout-login:' . sha1($request->input('login_contact') . '|' . $request->ip());
        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()
                ->withInput($request->only('login_contact'))
                ->withErrors(['login_contact' => 'Too many login attempts. Please try again in ' . ceil($seconds / 60) . ' minutes, or use OTP login.']);
        }

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
            RateLimiter::hit($throttleKey, 15 * 60);
            return $fail();
        }

        RateLimiter::clear($throttleKey);
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
        $type       = $request->input('contact_type');
        $value      = $request->input('contact_value');
        $normalized = $this->normalizer->normalize($type, $value);

        // If this contact already exists + verified → ask the user to log in
        $existing = \App\Models\UserContact::where('type', $type)
            ->where('value', $normalized)
            ->whereNotNull('verified_at')
            ->first();

        if ($existing) {
            $course = \App\Models\Course::find($request->input('course_id'));
            return redirect()
                ->route('courses.checkout.show', $course ?? abort(404))
                ->with('existing_account', $value)
                ->withInput(['login_contact' => $value]);
        }

        // ── NEW REGISTRATION path (form includes personal info + password) ──
        if ($request->filled('first_name')) {
            $request->validate([
                'first_name'  => ['required', 'string', 'max:100'],
                'last_name'   => ['required', 'string', 'max:100'],
                'gender'      => ['required', 'in:male,female'],
                'dob'         => ['required', 'date', 'before:today'],
                'id_type'     => ['required', 'in:national_id,passport'],
                'national_id' => ['nullable', 'string', 'max:20'],
                'passport'    => ['nullable', 'string', 'max:20'],
                'email'       => ['nullable', 'email', 'max:255'],
                'password'    => ['required', 'string', 'min:8', 'confirmed'],
            ]);

            if ($request->id_type === 'national_id' && empty(trim((string) $request->national_id))) {
                return back()->withErrors(['national_id' => 'Please enter your Maldivian ID card number.'])->withInput();
            }
            if ($request->id_type === 'passport' && empty(trim((string) $request->passport))) {
                return back()->withErrors(['passport' => 'Please enter your passport number.'])->withInput();
            }

            $flowType = $request->input('flow_type') ?: 'adult';
            if ($flowType === 'adult' && \Carbon\Carbon::parse($request->dob)->age < 18) {
                return back()->withInput()
                    ->withErrors(['dob' => 'You must be 18 or older to enroll yourself. If enrolling a child, choose "Parent / guardian".']);
            }

            $emailNorm = $request->filled('email') ? $this->normalizer->normalizeEmail($request->email) : null;

            // Store full registration data in session — NO account created yet
            session([
                'reg_pending_data' => [
                    'contact_type' => $type,
                    'contact_value' => $normalized,
                    'first_name'  => trim($request->first_name),
                    'last_name'   => trim($request->last_name),
                    'gender'      => $request->gender,
                    'dob'         => $request->dob,
                    'id_type'     => $request->id_type,
                    'national_id' => $request->id_type === 'national_id' ? strtoupper(trim($request->national_id ?? '')) : null,
                    'passport'    => $request->id_type === 'passport'    ? strtoupper(trim($request->passport ?? ''))    : null,
                    'email'       => $emailNorm,
                    'password_hash' => Hash::make($request->password),
                    'flow_type'   => $flowType,
                ],
                'pending_course_id'           => $request->input('course_id'),
                'pending_selected_course_ids' => $request->input('course_id') ? [(int) $request->input('course_id')] : [],
                'pending_term_id'             => $request->input('term_id'),
                'checkout_flow'               => $flowType,
            ]);

            // Send OTP via cache — no UserContact created yet
            $this->otpService->sendForNewRegistration($type, $normalized);

            return redirect()->route('courses.register.otp')
                ->with('success', 'Verification code sent to your ' . ($type === 'mobile' ? 'phone' : 'email') . '.');
        }

        // ── RETURNING USER / OTP LOGIN path ──
        [$user, $contact, $isNew] = $this->accountResolver->resolveOrCreateByContact($type, $normalized);

        $this->otpService->send($contact, 'verify_contact');

        session([
            'pending_contact_id'          => $contact->id,
            'pending_user_id'             => $user->id,
            'pending_course_id'           => $request->input('course_id'),
            'pending_selected_course_ids' => $request->input('course_id') ? [(int) $request->input('course_id')] : [],
            'pending_term_id'             => $request->input('term_id'),
            'checkout_flow'               => $request->input('flow_type'),
        ]);

        return redirect()->route('courses.register.otp')
            ->with('success', 'Verification code sent. Please check your ' . ($type === 'mobile' ? 'phone' : 'email') . '.');
    }

    public function otpForm(Request $request): View|RedirectResponse
    {
        $pendingReg = session('reg_pending_data');

        if ($pendingReg) {
            return view('courses.register-otp', [
                'contact'        => null,
                'contactType'    => $pendingReg['contact_type'],
                'contactDisplay' => $this->maskContact($pendingReg['contact_type'], $pendingReg['contact_value']),
                'isNewReg'       => true,
            ]);
        }

        if (! session('pending_contact_id')) {
            return redirect()->route('public.courses.index')->with('error', 'Session expired. Please start again.');
        }

        $contact = UserContact::find(session('pending_contact_id'));
        if (! $contact) {
            return redirect()->route('public.courses.index')->with('error', 'Session expired.');
        }

        return view('courses.register-otp', [
            'contact'        => $contact,
            'contactType'    => $contact->type,
            'contactDisplay' => $this->maskContact($contact->type, $contact->value),
            'isNewReg'       => false,
        ]);
    }

    public function verify(VerifyOtpRequest $request): RedirectResponse
    {
        $pendingReg = session('reg_pending_data');

        if ($pendingReg) {
            // ── New registration: verify cache OTP then create account ──
            $type  = $pendingReg['contact_type'];
            $value = $pendingReg['contact_value'];

            $this->otpService->verifyForNewRegistration($type, $value, $request->input('code'));

            $user = \App\Models\User::create([
                'name'                 => trim($pendingReg['first_name'] . ' ' . $pendingReg['last_name']),
                'gender'               => $pendingReg['gender'],
                'date_of_birth'        => $pendingReg['dob'],
                'national_id'          => $pendingReg['national_id'],
                'passport'             => $pendingReg['passport'],
                'password'             => $pendingReg['password_hash'],
                'force_password_change' => false,
            ]);

            UserContact::create([
                'user_id'    => $user->id,
                'type'       => $type,
                'value'      => $value,
                'is_primary' => true,
                'verified_at' => now(),
            ]);

            if (! empty($pendingReg['email'])) {
                \App\Models\UserContact::firstOrCreate(
                    ['type' => 'email', 'value' => $pendingReg['email']],
                    ['user_id' => $user->id, 'is_primary' => false, 'verified_at' => null]
                );
            }

            session()->forget('reg_pending_data');
            session(['checkout_flow' => $pendingReg['flow_type'] ?? 'adult']);

            Auth::login($user);
            $this->notifyAdminNewRegistration($user);

            return redirect()->route('courses.register.continue');
        }

        // ── Returning user / OTP login ──
        $contactId = session('pending_contact_id');
        if (! $contactId) {
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

    public function resendNewRegistrationOtp(Request $request): RedirectResponse
    {
        $pendingReg = session('reg_pending_data');
        if (! $pendingReg) {
            return redirect()->route('public.courses.index')->with('error', 'Session expired. Please start again.');
        }

        $this->otpService->sendForNewRegistration($pendingReg['contact_type'], $pendingReg['contact_value']);

        return back()->with('success', 'A new verification code has been sent.');
    }

    private function maskContact(string $type, string $value): string
    {
        if ($type === 'mobile') {
            return '****' . substr($value, -4);
        }
        $parts  = explode('@', $value);
        $name   = $parts[0] ?? '';
        $domain = $parts[1] ?? '';
        return substr($name, 0, 2) . str_repeat('*', max(2, strlen($name) - 2)) . '@' . $domain;
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

        $request->validate([
            'first_name'  => ['required', 'string', 'max:100'],
            'last_name'   => ['required', 'string', 'max:100'],
            'gender'      => ['required', 'in:male,female'],
            'dob'         => ['required', 'date', 'before:today'],
            'id_type'     => ['required', 'in:national_id,passport'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'passport'    => ['nullable', 'string', 'max:20'],
            'email'       => ['nullable', 'email', 'max:255'],
            'password'    => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Validate that the correct ID field is filled
        if ($request->id_type === 'national_id' && empty(trim((string)$request->national_id))) {
            return back()->withErrors(['national_id' => 'Please enter your Maldivian ID card number.'])->withInput();
        }
        if ($request->id_type === 'passport' && empty(trim((string)$request->passport))) {
            return back()->withErrors(['passport' => 'Please enter your passport number.'])->withInput();
        }

        $user = \App\Models\User::findOrFail($userId);

        $user->update([
            'name'                  => trim($request->first_name . ' ' . $request->last_name),
            'gender'                => $request->gender,
            'date_of_birth'         => $request->dob,
            'national_id'           => $request->id_type === 'national_id' ? strtoupper(trim($request->national_id)) : null,
            'passport'              => $request->id_type === 'passport' ? strtoupper(trim($request->passport)) : null,
            'password'              => Hash::make($request->password),
            'force_password_change' => false,
        ]);

        // Save optional email contact
        if ($request->filled('email')) {
            $emailNorm = $this->normalizer->normalizeEmail($request->email);
            $emailExists = $user->contacts()->where('type', 'email')->where('value', $emailNorm)->exists();
            if (!$emailExists) {
                $user->contacts()->create([
                    'type'        => 'email',
                    'value'       => $emailNorm,
                    'is_primary'  => false,
                    'verified_at' => null,
                ]);
            }
        }

        Auth::login($user);

        // Notify super admins via SMS about new registration
        $this->notifyAdminNewRegistration($user);

        // If a course is pending AND this is an adult self-enrollment, we already have
        // all student data from the profile form — skip register-continue.
        // For parent/guardian flow the child's details are NOT on this form,
        // so we must always send them to register-continue to fill in the child's data.
        $courseIds    = session('pending_selected_course_ids', []);
        // session('checkout_flow') is often "" (empty string) because the hidden input
        // is never populated on new-account checkout — treat falsy as 'adult'.
        $checkoutFlow = session('checkout_flow') ?: 'adult';

        if (!empty($courseIds) && $checkoutFlow === 'adult') {
            // Age check — enrollAdultSelf requires 18+; catch early before OTP step
            if (\Carbon\Carbon::parse($request->dob)->age < 18) {
                return back()->withInput()
                    ->withErrors(['dob' => 'You must be 18 or older to enroll yourself. If this is for a child, use the parent/guardian enrollment flow.']);
            }

            $studentData = [
                'first_name'  => trim($request->first_name),
                'last_name'   => trim($request->last_name),
                'dob'         => $request->dob,
                'gender'      => $request->gender,
                'id_type'     => $request->id_type,
                'national_id' => $request->id_type === 'national_id' ? strtoupper(trim($request->national_id)) : null,
                'passport'    => $request->id_type === 'passport'    ? strtoupper(trim($request->passport))    : null,
            ];

            // Duplicate check — same as enroll() adult path
            $searchNid      = $request->id_type === 'national_id' ? strtoupper(trim($request->national_id ?? '')) : null;
            $searchPassport = $request->id_type === 'passport'    ? strtoupper(trim($request->passport ?? ''))    : null;
            foreach (\App\Models\RegistrationStudent::whereNotNull('user_id')->get() as $candidate) {
                $idMatch = ($searchNid && $candidate->national_id === $searchNid)
                        || ($searchPassport && $candidate->passport === $searchPassport);
                if (! $idMatch) {
                    continue;
                }
                $existingEnrollment = \App\Models\CourseEnrollment::where('student_id', $candidate->id)
                    ->whereIn('course_id', $courseIds)
                    ->where('status', '!=', 'rejected')
                    ->with('course')
                    ->first();
                $idLabel  = $searchNid ? "ID card {$searchNid}" : "Passport {$searchPassport}";
                $realName = trim($candidate->first_name . ' ' . $candidate->last_name);
                if ($existingEnrollment) {
                    $title  = $existingEnrollment->course?->title
                              ?? \App\Models\Course::whereIn('id', $courseIds)->first()?->title ?? 'this course';
                    $status = $this->humanEnrollmentStatus($existingEnrollment);
                    return back()->withInput()
                        ->withErrors(['national_id' => "This {$idLabel} belongs to {$realName}, who is already enrolled in \"{$title}\" — {$status}."]);
                }
                return back()->withInput()
                    ->withErrors(['national_id' => "This {$idLabel} is already registered to {$realName}. Please log in to that account instead."]);
            }

            session([
                'enroll_pending_data'         => $studentData,
                'enroll_pending_flow'         => 'adult',
                'enroll_pending_course_ids'   => $courseIds,
                'enroll_pending_term_id'      => session('pending_term_id'),
                'enroll_pending_email'        => $request->filled('email') ? $this->normalizer->normalizeEmail($request->email) : '',
                'enroll_pending_student_mode' => 'new',
            ]);

            // Store contact for OTP — do NOT send yet; user reads T&C first then clicks "Send OTP"
            $mobileContact = $user->contacts()->where('type', 'mobile')->whereNotNull('verified_at')->first();
            if ($mobileContact) {
                session(['enroll_otp_contact_id' => $mobileContact->id]);
            }
            session()->forget('enroll_otp_sent');

            return redirect()->route('courses.register.enroll.otp')
                ->with('success', 'Account created! Please read the terms and send your OTP to confirm enrollment.');
        }

        // Parent flow (or adult with no pending courses) → must fill in enrollment form
        return redirect()->route('courses.register.continue');
    }

    protected function notifyAdminNewRegistration(\App\Models\User $user): void
    {
        try {
            $admins = \App\Models\User::role(['super_admin', 'admin'])->get();
            $mobile = $user->contacts()->where('type', 'mobile')->value('value')
                ?? $user->phone ?? 'N/A';
            $name = $user->name ?? 'Unknown';
            $id   = $user->national_id ?? $user->passport ?? 'N/A';
            $time = now()->format('d M Y, g:i A');

            $message = "[Akuru] New registration: {$name} ({$mobile})";

            foreach ($admins as $admin) {
                $adminMobile = $admin->contacts()->where('type', 'mobile')->value('value')
                    ?? $admin->phone ?? null;
                if ($adminMobile) {
                    app(\App\Services\SmsGatewayService::class)->sendSms($adminMobile, $message);
                }
            }
        } catch (\Throwable) {
            // non-critical — never block registration
        }
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
        $checkoutFlow = session('checkout_flow');
        $defaultFlow  = $checkoutFlow
            ?? ($existingProfile ? 'adult' : ($user->guardianStudents->isNotEmpty() ? 'parent' : 'adult'));

        // Pre-fill data from user profile (name, gender, DOB, ID) for new users
        $nameParts   = explode(' ', $user->name ?? '', 2);
        $hasNationalId = ! empty($existingProfile?->national_id ?? $user->national_id);
        $hasPassport   = ! empty($existingProfile?->passport);
        $prefill = [
            'first_name'  => $existingProfile?->first_name  ?? $nameParts[0] ?? '',
            'last_name'   => $existingProfile?->last_name   ?? $nameParts[1] ?? '',
            'dob'         => $existingProfile?->dob?->format('Y-m-d') ?? $user->date_of_birth?->format('Y-m-d') ?? '',
            'gender'      => $existingProfile?->gender      ?? $user->gender ?? '',
            'national_id' => $existingProfile?->national_id ?? $user->national_id ?? '',
            'passport'    => $existingProfile?->passport    ?? '',
            'id_type'     => $hasNationalId ? 'national_id' : ($hasPassport ? 'passport' : 'national_id'),
        ];

        return view('courses.register-continue', [
            'user'            => $user,
            'courses'         => $courses,
            'courseIds'       => $courseIds,
            'termId'          => session('pending_term_id'),
            'existingProfile' => $existingProfile,
            'defaultFlow'     => $defaultFlow,
            'prefill'         => $prefill,
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

        $flow = $request->input('flow', 'adult');

        $courseValidator = Validator::make($request->all(), [
            'course_ids'   => ['required', 'array', 'min:1'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
        ]);
        if ($courseValidator->fails()) {
            return back()->withErrors($courseValidator)->withInput();
        }
        $courseIds = $courseValidator->validated()['course_ids'];
        $termId    = ($request->input('term_id') !== '' && $request->input('term_id') !== null)
            ? (int) $request->input('term_id') : null;

        // ── Validate student data fields FIRST ───────────────────────────────
        $data = $this->validateEnrollRequest($request, $flow);
        if ($data instanceof RedirectResponse) {
            return $data;
        }

        // ── ALL duplicate checks happen here, before OTP is ever sent ─────────

        // Adult self-enrollment duplicate
        if ($flow === 'adult') {
            $studentProfile = $user->registrationStudentProfile;
            if ($studentProfile) {
                $existing = \App\Models\CourseEnrollment::where('student_id', $studentProfile->id)
                    ->whereIn('course_id', $courseIds)
                    ->where('status', '!=', 'rejected')
                    ->with('course')
                    ->first();
                if ($existing) {
                    $title  = $existing->course?->title
                              ?? \App\Models\Course::whereIn('id', $courseIds)->first()?->title
                              ?? 'this course';
                    $status = $this->humanEnrollmentStatus($existing);
                    return back()->withInput()
                        ->withErrors(['course_ids' => "You are already enrolled in \"{$title}\" — {$status}."]);
                }
            }
        }

        // Parent enrolling existing child duplicate
        if ($flow === 'parent' && $request->input('student_mode') === 'existing' && !empty($data['student_id'])) {
            $studentId = (int) $data['student_id'];
            $existing  = \App\Models\CourseEnrollment::where('student_id', $studentId)
                ->whereIn('course_id', $courseIds)
                ->where('status', '!=', 'rejected')
                ->with('course')
                ->first();
            if ($existing) {
                $title       = $existing->course?->title
                               ?? \App\Models\Course::find($courseIds[0])?->title
                               ?? 'this course';
                $status      = $this->humanEnrollmentStatus($existing);
                $studentName = \App\Models\RegistrationStudent::find($studentId)?->full_name ?? 'This student';
                return back()->withInput()
                    ->withErrors(['student_id' => "{$studentName} is already enrolled in \"{$title}\" — {$status}."]);
            }
        }

        // Parent enrolling new child — match encrypted national_id / passport
        if ($flow === 'parent' && $request->input('student_mode') === 'new') {
            $searchNid      = ($data['id_type'] ?? '') === 'national_id'
                              ? strtoupper(trim($data['national_id'] ?? '')) : null;
            $searchPassport = ($data['id_type'] ?? '') === 'passport'
                              ? strtoupper(trim($data['passport'] ?? '')) : null;

            $existingStudent = null;
            $user->loadMissing('guardianStudents');
            foreach ($user->guardianStudents as $gs) {
                if ($searchNid && $gs->national_id === $searchNid)           { $existingStudent = $gs; break; }
                if ($searchPassport && $gs->passport === $searchPassport)    { $existingStudent = $gs; break; }
            }
            if (! $existingStudent) {
                foreach (\App\Models\RegistrationStudent::whereNotNull('user_id')->get() as $c) {
                    if ($searchNid && $c->national_id === $searchNid)        { $existingStudent = $c; break; }
                    if ($searchPassport && $c->passport === $searchPassport) { $existingStudent = $c; break; }
                }
            }
            if ($existingStudent) {
                $existing = \App\Models\CourseEnrollment::where('student_id', $existingStudent->id)
                    ->whereIn('course_id', $courseIds)
                    ->where('status', '!=', 'rejected')
                    ->with('course')
                    ->first();

                // The ID belongs to a known student — always report the real name
                $realName = trim($existingStudent->first_name . ' ' . $existingStudent->last_name);
                $idLabel  = $searchNid ? "ID card {$searchNid}" : "Passport {$searchPassport}";

                if ($existing) {
                    $title  = $existing->course?->title
                              ?? \App\Models\Course::find($courseIds[0])?->title
                              ?? 'this course';
                    $status = $this->humanEnrollmentStatus($existing);
                    return back()->withInput()
                        ->withErrors(['national_id' => "This {$idLabel} belongs to {$realName}, who is already enrolled in \"{$title}\" — {$status}."]);
                }

                // ID exists but not yet enrolled in this course — warn the parent
                return back()->withInput()
                    ->withErrors(['national_id' => "This {$idLabel} is already registered to {$realName}. If this is your child, select \"Enroll existing child\" and choose them from the list."]);
            }
        }

        // Guard: check for an existing un-confirmed pending payment (deferred enrollment)
        // to prevent duplicate payment records for the same user + courses.
        $existingPendingPayment = \App\Models\Payment::where('user_id', $user->id)
            ->whereNotNull('enrollment_pending_payload')
            ->whereIn('status', ['initiated', 'pending'])
            ->get()
            ->first(function ($p) use ($courseIds) {
                $payload = $p->enrollment_pending_payload ?? [];
                return ! empty(array_intersect($payload['course_ids'] ?? [], $courseIds));
            });

        if ($existingPendingPayment) {
            session(['pending_payment_ref' => $existingPendingPayment->merchant_reference]);
            $this->clearEnrollPendingSession();
            return redirect()->route('courses.register.complete')
                ->with('info', 'You already have a pending payment for this course. Please complete it below.');
        }

        // Store all form data in session
        session([
            'enroll_pending_data'          => $data,
            'enroll_pending_flow'          => $flow,
            'enroll_pending_course_ids'    => $courseIds,
            'enroll_pending_term_id'       => $termId,
            'enroll_pending_email'         => trim((string) $request->input('email', '')),
            'enroll_pending_student_mode'  => $request->input('student_mode'),
            'enroll_pending_child_password'=> $flow === 'parent' && $request->input('student_mode') === 'new'
                                               ? $request->input('child_password')
                                               : null,
        ]);

        // Store the contact ID in session so the OTP page can send OTP on demand
        $mobileContact = $user->contacts()->where('type', 'mobile')->whereNotNull('verified_at')->first();
        if ($mobileContact) {
            session(['enroll_otp_contact_id' => $mobileContact->id]);
        }

        // Do NOT send OTP here — user will click "Send OTP" after reading T&C
        session()->forget('enroll_otp_sent');

        return redirect()->route('courses.register.enroll.otp');
    }

    /** Show enrollment consent OTP page */
    public function enrollOtpForm(Request $request): View|RedirectResponse
    {
        if (!session('enroll_pending_course_ids')) {
            return redirect()->route('public.courses.index')->with('error', 'Session expired. Please start again.');
        }

        $user      = $request->user();
        $courseIds = session('enroll_pending_course_ids', []);
        $courses   = Course::whereIn('id', $courseIds)->get();
        $totalFee  = $courses->sum(fn($c) => $c->fee ?? 0);

        // All verified contacts the user can receive an OTP on
        $availableContacts = $user
            ? $user->contacts()->whereNotNull('verified_at')->get()
            : collect();

        // Ensure session contact is one of the available contacts; default to mobile → email
        $currentContactId = session('enroll_otp_contact_id');
        if (! $currentContactId || ! $availableContacts->contains('id', $currentContactId)) {
            $preferred = $availableContacts->firstWhere('type', 'mobile')
                      ?? $availableContacts->first();
            if ($preferred) {
                session(['enroll_otp_contact_id' => $preferred->id]);
                $currentContactId = $preferred->id;
            }
        }

        $contact = $availableContacts->firstWhere('id', $currentContactId);

        $maskedContact = $contact
            ? $this->maskContact($contact->type, $contact->value)
            : 'your contact';

        // Pre-compute masked values for all contacts so the view doesn't need controller methods
        $contactsForDisplay = $availableContacts->map(fn ($c) => (object) [
            'id'     => $c->id,
            'type'   => $c->type,
            'masked' => $this->maskContact($c->type, $c->value),
        ]);

        $otpSent = (bool) session('enroll_otp_sent');

        return view('courses.register-enroll-confirm', compact(
            'courses', 'totalFee', 'maskedContact', 'otpSent',
            'availableContacts', 'currentContactId', 'contactsForDisplay'
        ));
    }

    /** Verify enrollment consent OTP + T&C → process enrollment */
    public function enrollConfirm(Request $request): RedirectResponse
    {
        $request->validate([
            'otp_code'       => ['required', 'string', 'size:6'],
            'terms_accepted' => ['accepted'],
        ], [
            'terms_accepted.accepted' => 'You must accept the Terms & Conditions to continue.',
        ]);

        if (!session('enroll_pending_course_ids')) {
            return redirect()->route('public.courses.index')->with('error', 'Session expired. Please start again.');
        }

        // OTP verification is always required
        $contactId = session('enroll_otp_contact_id');
        if (!$contactId) {
            return redirect()->route('public.courses.index')
                ->with('error', 'Session expired. Please start the enrollment again.');
        }
        $contact = \App\Models\UserContact::find($contactId);
        if (!$contact) {
            return redirect()->route('public.courses.index')
                ->with('error', 'Verification contact not found. Please start again.');
        }
        try {
            $this->otpService->verify($contact, 'login', $request->otp_code);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $user = $request->user();
        if (!$user) {
            return redirect()->route('public.courses.index')->with('error', 'Session expired.');
        }

        return $this->processEnrollmentFromSession($user);
    }

    /** Send (or resend) enrollment OTP — triggered by user clicking "Send OTP" button */
    public function enrollResendOtp(Request $request): RedirectResponse
    {
        // Allow switching contact before or after OTP is sent
        if ($request->filled('contact_id')) {
            $user = $request->user();
            $chosen = $user?->contacts()
                ->whereNotNull('verified_at')
                ->where('id', (int) $request->input('contact_id'))
                ->first();
            if ($chosen) {
                session([
                    'enroll_otp_contact_id' => $chosen->id,
                    'enroll_otp_sent'       => false,
                ]);
                session()->forget('enroll_otp_sent_before');
            }
        }

        $contactId = session('enroll_otp_contact_id');

        // Session expired — redirect to enrollment form, not homepage
        if (!$contactId) {
            return redirect()->route('courses.register.continue')
                ->with('error', 'Your session expired. Please fill in the enrollment form again.');
        }

        $contact = \App\Models\UserContact::find($contactId);
        if (!$contact) {
            $this->clearEnrollPendingSession();
            return redirect()->route('courses.register.continue')
                ->with('error', 'Could not find your verification contact. Please start the enrollment again.');
        }

        try {
            $this->otpService->send($contact, 'login');
            session(['enroll_otp_sent' => true]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $isResend = session('enroll_otp_sent_before');
        session(['enroll_otp_sent_before' => true]);

        $contactLabel = $contact->type === 'email' ? 'email' : 'mobile';
        $msg = $isResend
            ? "New OTP sent to your {$contactLabel}."
            : "OTP sent to your {$contactLabel}. Enter it below.";

        return redirect()->route('courses.register.enroll.otp')->with('success', $msg);
    }

    /** Process enrollment from session data (called after OTP consent) */
    protected function processEnrollmentFromSession(\App\Models\User $user): RedirectResponse
    {
        $flow          = session('enroll_pending_flow', 'adult');
        $data          = session('enroll_pending_data', []);
        $courseIds     = session('enroll_pending_course_ids', []);
        $termId        = session('enroll_pending_term_id');
        $emailInput    = session('enroll_pending_email', '');
        $studentMode   = session('enroll_pending_student_mode');
        $childPassword = session('enroll_pending_child_password');

        // Save optional email contact
        if ($emailInput && filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
            $normalized = $this->normalizer->normalize('email', $emailInput);
            if (! $user->contacts()->where('type', 'email')->exists()) {
                $user->contacts()->create([
                    'type'        => 'email',
                    'value'       => $normalized,
                    'is_primary'  => false,
                    'verified_at' => null,
                ]);
            }
        }

        $courses   = \App\Models\Course::whereIn('id', $courseIds)->get();
        $totalFee  = $courses->sum(fn ($c) => (float) ($c->registration_fee_amount ?? $c->fee ?? 0));

        // ── PAID COURSES: deferred enrollment — create only a Payment record now ──
        // RegistrationStudent + CourseEnrollment are created AFTER BML confirms payment.
        if ($totalFee > 0) {
            $enrollmentPayload = [
                'user_id'      => $user->id,
                'flow'         => $flow,
                'student_data' => $data,
                'course_ids'   => $courseIds,
                'term_id'      => $termId,
                'student_mode' => $studentMode,
                'child_password' => $childPassword,
            ];

            $payment = $this->paymentService->createPaymentForPendingEnrollment($user, $enrollmentPayload, $courses);

            $init = $this->paymentService->initiatePayment($payment, [
                'return_url' => route('payments.bml.return') . '?ref=' . $payment->merchant_reference,
            ]);

            \Illuminate\Support\Facades\Log::info('BML deferred-enrollment initiation', [
                'payment_id'       => $payment->id,
                'success'          => $init->success,
                'has_redirect_url' => ! empty($init->redirectUrl),
                'error'            => $init->error ?? null,
            ]);

            if ($init->success && $init->redirectUrl) {
                session(['pending_payment_ref' => $payment->merchant_reference]);
                $this->clearEnrollPendingSession();
                return redirect()->away($init->redirectUrl);
            }

            // BML initiation failed — payment record created but no redirect available
            $this->clearEnrollPendingSession();
            $this->clearPendingSession();
            session(['pending_payment_ref' => $payment->merchant_reference]);
            return redirect()->route('courses.register.complete')
                ->with('error', 'Your enrollment request was received but we could not connect to the payment gateway right now. Please use the "Proceed to payment" button below to complete your payment.');
        }

        // ── FREE COURSES: create enrollment immediately (no payment needed) ──
        try {
            if ($flow === 'parent') {
                if ($studentMode === 'new') {
                    $guardianMeta = ['relationship' => $data['relationship'] ?? 'guardian', 'child_password' => $childPassword];
                    $result = $this->enrollmentService->enrollByParent($user, $data, $courseIds, $termId, $guardianMeta);
                } else {
                    $result = $this->enrollmentService->enrollByParent($user, (int) $data['student_id'], $courseIds, $termId);
                }
            } else {
                $result = $this->enrollmentService->enrollAdultSelf($user, $data, $courseIds, $termId);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            session()->forget('enroll_otp_sent');
            $contactId = session('enroll_otp_contact_id');
            if ($contactId) {
                $contact = \App\Models\UserContact::find($contactId);
                if ($contact) {
                    try {
                        $this->otpService->send($contact, 'login');
                        session(['enroll_otp_sent' => true]);
                    } catch (\Throwable) {}
                }
            }
            return redirect()->route('courses.register.enroll.otp')
                ->withErrors($e->errors())
                ->with('info', 'A new OTP has been sent to your mobile. Please enter it below.');
        }

        if (empty($result->createdEnrollments) && ! $result->hasPaymentsPending()) {
            $this->clearEnrollPendingSession();
            $this->clearPendingSession();

            $msg = 'You are already enrolled in the selected course(s).';
            if (! empty($result->existingEnrollments)) {
                $e = $result->existingEnrollments[0];
                $e->loadMissing('course');
                $courseTitle = $e->course?->title ?? '';
                $status      = $this->humanEnrollmentStatus($e);
                $msg = $courseTitle
                    ? "You are already enrolled in \"{$courseTitle}\" — {$status}."
                    : "You are already enrolled — {$status}.";
            }

            return redirect()->route('my.enrollments')->with('info', $msg);
        }

        if (! empty($result->createdEnrollments)) {
            $this->sendFreeEnrollmentStudentNotifications($user, $result->createdEnrollments);
            $this->notifyAdminFreeEnrollment($user, $result->createdEnrollments);
        }

        $this->clearEnrollPendingSession();
        $this->clearPendingSession();
        return redirect()->route('courses.register.complete')->with('enrollments', $result->allEnrollments());
    }

    protected function notifyAdminNewEnrollment(\App\Models\User $user, $enrollments): void
    {
        try {
            $admins = \App\Models\User::role(['super_admin', 'admin'])->get();
            foreach (collect($enrollments)->filter() as $enrollment) {
                $enrollment->loadMissing('course');
                $courseName = $enrollment->course?->title ?? 'Unknown';
                $fee        = $enrollment->course?->fee ?? 0;
                $name       = $user->name ?? 'Unknown';
                $mobile     = $user->contacts()->where('type','mobile')->value('value') ?? 'N/A';
                $feeText = $fee > 0 ? " MVR {$fee}" : " Free";
                $message = "[Akuru] Enrollment: {$name} → {$courseName} ({$feeText})";
                foreach ($admins as $admin) {
                    $adminMobile = $admin->contacts()->where('type','mobile')->value('value') ?? $admin->phone ?? null;
                    if ($adminMobile) {
                        app(\App\Services\SmsGatewayService::class)->sendSms($adminMobile, $message);
                    }
                }
            }
        } catch (\Throwable) {}
    }

    /** Return a human-readable enrollment status for user-facing messages. */
    private function humanEnrollmentStatus(\App\Models\CourseEnrollment $enrollment): string
    {
        if ($enrollment->status === 'active') {
            return 'enrollment confirmed';
        }
        if ($enrollment->status === 'pending') {
            if (in_array($enrollment->payment_status, ['pending', 'initiated'], true)) {
                return 'payment pending';
            }
            return 'pending admin approval';
        }
        return ucfirst($enrollment->status);
    }

    protected function clearEnrollPendingSession(): void
    {
        session()->forget(['enroll_pending_data','enroll_pending_flow','enroll_pending_course_ids','enroll_pending_term_id','enroll_pending_email','enroll_pending_student_mode','enroll_pending_child_password','enroll_otp_contact_id','enroll_otp_sent','enroll_otp_sent_before']);
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
        $showProceedToPayment = false;
        if ($paymentRef) {
            $payment = \App\Models\Payment::where('merchant_reference', $paymentRef)->first();
            if ($payment) {
                $paymentIdForStatus = $payment->id;
                $showProceedToPayment = ! in_array($payment->status ?? '', ['confirmed', 'paid'], true);
                foreach ($payment->items as $item) {
                    $enrollments[] = $item->enrollment;
                }
            } else {
                $showProceedToPayment = true;
            }
        }

        $this->clearPendingSession();

        return view('courses.register-complete', [
            'enrollments' => collect($enrollments)->unique('id')->values(),
            'paymentRef' => $paymentRef,
            'paymentIdForStatus' => $paymentIdForStatus,
            'paymentError' => session('error'),
            'showProceedToPayment' => $showProceedToPayment,
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
                $rules['first_name']                  = ['required', 'string', 'max:100'];
                $rules['last_name']                   = ['required', 'string', 'max:100'];
                $rules['dob']                         = ['required', 'date', 'before:today'];
                $rules['gender']                      = ['nullable', 'in:male,female'];
                $rules['relationship']                = ['nullable', 'in:father,mother,guardian,other'];
                $rules['id_type']                     = ['required', 'in:national_id,passport'];
                $rules['national_id']                 = ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z][0-9]{5,9}$/'];
                $rules['passport']                    = ['nullable', 'string', 'max:20'];
                $rules['child_password']              = ['required', 'string', 'min:8', 'confirmed'];
                $rules['child_password_confirmation'] = ['required', 'string'];
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

    protected function sendFreeEnrollmentStudentNotifications(\App\Models\User $user, $enrollments): void
    {
        foreach (collect($enrollments)->filter() as $enrollment) {
            $enrollment->loadMissing(['course', 'student']);

            // Email
            $emailAddress = $user->email
                ?? $user->contacts()->where('type', 'email')->value('value');

            if ($emailAddress) {
                try {
                    \Illuminate\Support\Facades\Mail::to($emailAddress)
                        ->queue(new \App\Mail\FreeEnrollmentConfirmedMail($enrollment));
                } catch (\Throwable) {
                    // non-critical
                }
            }

            // SMS
            $mobile = $user->mobile
                ?? $user->contacts()->where('type', 'mobile')->value('value');

            if ($mobile) {
                try {
                    app(\App\Services\SmsGatewayService::class)->sendSms(
                        $mobile,
                        "Akuru: Enrollment received for {$enrollment->course?->title}. Pending approval."
                    );
                } catch (\Throwable) {
                    // non-critical
                }
            }
        }
    }

    protected function notifyAdminFreeEnrollment(\App\Models\User $user, $enrollments): void
    {
        $adminEmail = config('mail.admin_notification_address')
            ?? config('mail.from.address');

        if (! $adminEmail) {
            return;
        }

        foreach (collect($enrollments)->filter() as $enrollment) {
            try {
                \Illuminate\Support\Facades\Mail::to($adminEmail)
                    ->queue(new \App\Mail\AdminFreeEnrollmentMail($user, $enrollment));
            } catch (\Throwable) {
                // non-critical
            }
        }
    }
}
