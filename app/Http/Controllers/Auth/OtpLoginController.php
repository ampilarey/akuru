<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class OtpLoginController extends Controller
{
    protected OtpService $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->middleware('guest')->except('logout');
        $this->otpService = $otpService;
    }

    /**
     * Show OTP login form
     */
    public function showLoginForm()
    {
        return view('auth.otp-login');
    }

    /**
     * Request OTP for login
     */
    public function requestOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $phone = $request->phone;

        // Check if user exists with this phone
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            return back()->withErrors([
                'phone' => 'No account found with this phone number.',
            ])->withInput();
        }

        // Check if user is active
        if (!$user->is_active) {
            return back()->withErrors([
                'phone' => 'Your account is inactive. Please contact support.',
            ])->withInput();
        }

        // Generate and send OTP
        $result = $this->otpService->generate($phone, 'login');

        if (!$result['success']) {
            return back()->withErrors([
                'phone' => $result['error'],
            ])->withInput();
        }

        // Store phone in session for verification step
        session(['otp_phone' => $phone]);

        return redirect()->route('otp.verify.form')
            ->with('success', 'OTP sent to your phone. Please enter the code.');
    }

    /**
     * Show OTP verification form
     */
    public function showVerifyForm()
    {
        if (!session('otp_phone')) {
            return redirect()->route('otp.login.form')
                ->withErrors(['error' => 'Please enter your phone number first.']);
        }

        return view('auth.otp-verify');
    }

    /**
     * Verify OTP and login
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $phone = session('otp_phone');

        if (!$phone) {
            return redirect()->route('otp.login.form')
                ->withErrors(['error' => 'Session expired. Please try again.']);
        }

        // Verify OTP
        $result = $this->otpService->verify($phone, $request->code, 'login');

        if (!$result['success']) {
            return back()->withErrors([
                'code' => $result['error'],
            ]);
        }

        // Find user
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            return redirect()->route('otp.login.form')
                ->withErrors(['error' => 'User not found.']);
        }

        // Log the user in
        Auth::login($user, true);

        // Clear session
        session()->forget('otp_phone');

        // Regenerate session
        $request->session()->regenerate();

        Log::info('User logged in via OTP', [
            'user_id' => $user->id,
            'phone' => $phone,
        ]);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Resend OTP
     */
    public function resendOtp(Request $request)
    {
        $phone = session('otp_phone');

        if (!$phone) {
            return redirect()->route('otp.login.form')
                ->withErrors(['error' => 'Session expired. Please try again.']);
        }

        // Generate and send new OTP
        $result = $this->otpService->generate($phone, 'login');

        if (!$result['success']) {
            return back()->withErrors([
                'error' => $result['error'],
            ]);
        }

        return back()->with('success', 'New OTP sent to your phone.');
    }
}

