<?php

namespace App\Http\Controllers;

use App\Http\Requests\Registration\EnrollAdultRequest;
use App\Http\Requests\Registration\EnrollParentRequest;
use App\Http\Requests\Registration\SetPasswordRequest;
use App\Http\Requests\Registration\StartRegistrationRequest;
use App\Http\Requests\Registration\VerifyOtpRequest;
use App\Models\Course;
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
use Illuminate\View\View;

class CourseRegistrationController extends Controller
{
    public function __construct(
        protected AccountResolverService $accountResolver,
        protected OtpService $otpService,
        protected ContactNormalizer $normalizer,
        protected EnrollmentService $enrollmentService,
        protected PaymentService $paymentService
    ) {}

    public function show(Course $course): View
    {
        return view('courses.register', [
            'course' => $course,
            'fee' => $course->getRegistrationFeeAmount(),
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
            'pending_contact_id' => $contact->id,
            'pending_user_id' => $user->id,
            'pending_course_id' => $request->input('course_id'),
            'pending_selected_course_ids' => $request->input('course_id') ? [(int) $request->input('course_id')] : [],
            'pending_term_id' => $request->input('term_id'),
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

        return view('courses.register-continue', [
            'user' => $user,
            'courses' => $courses,
            'courseIds' => $courseIds,
            'termId' => session('pending_term_id'),
        ]);
    }

    public function enroll(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasVerifiedContact()) {
            return redirect()->route('public.courses.index')->with('error', 'Please verify your contact first.');
        }

        $flow = $request->input('flow', 'adult');
        $courseIds = $request->input('course_ids', session('pending_selected_course_ids', []));
        $termId = $request->input('term_id') ?: session('pending_term_id');

        if (empty($courseIds)) {
            $courseId = session('pending_course_id');
            if ($courseId) {
                $courseIds = [$courseId];
            }
        }

        if (empty($courseIds)) {
            return back()->withErrors(['course_ids' => ['Please select at least one course.']]);
        }

        try {
            if ($flow === 'parent') {
                $req = EnrollParentRequest::createFrom($request)->replace($request->all());
                $req->setContainer(app());
                $req->setRedirector(app('redirect'));
                $req->validateResolved();
                $data = $req->validated();

                if (($data['student_mode'] ?? '') === 'new') {
                    $studentData = [
                        'first_name' => $data['first_name'],
                        'last_name' => $data['last_name'],
                        'dob' => $data['dob'],
                        'gender' => $data['gender'] ?? null,
                    ];
                    $guardianMeta = ['relationship' => $data['relationship'] ?? 'guardian'];
                    $result = $this->enrollmentService->enrollByParent($user, $studentData, $courseIds, $termId, $guardianMeta);
                } else {
                    $result = $this->enrollmentService->enrollByParent($user, (int) $data['student_id'], $courseIds, $termId);
                }
            } else {
                $req = EnrollAdultRequest::createFrom($request)->replace($request->all());
                $req->setContainer(app());
                $req->setRedirector(app('redirect'));
                $req->validateResolved();
                $data = $req->validated();

                $studentData = [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'dob' => $data['dob'],
                    'gender' => $data['gender'] ?? null,
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
            return back()->withErrors(['payment' => $init->error ?? 'Payment initiation failed.'])->withInput();
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
        if ($paymentRef) {
            $payment = \App\Models\Payment::where('merchant_reference', $paymentRef)->first();
            if ($payment) {
                foreach ($payment->items as $item) {
                    $enrollments[] = $item->enrollment;
                }
            }
        }

        $this->clearPendingSession();

        return view('courses.register-complete', [
            'enrollments' => collect($enrollments)->unique('id')->values(),
            'paymentRef' => $paymentRef,
        ]);
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
