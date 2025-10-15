<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

class OtpPasswordResetController extends Controller
{
    protected OtpService $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->middleware('guest');
        $this->otpService = $otpService;
    }

    /**
     * Show password reset request form
     */
    public function showRequestForm()
    {
        return view('auth.passwords.otp-request');
    }

    /**
     * Request OTP for password reset
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

        // Generate and send OTP
        $result = $this->otpService->generate($phone, 'password_reset');

        if (!$result['success']) {
            return back()->withErrors([
                'phone' => $result['error'],
            ])->withInput();
        }

        // Store phone in session
        session(['password_reset_phone' => $phone]);

        return redirect()->route('password.otp.verify.form')
            ->with('success', 'OTP sent to your phone. Please enter the code.');
    }

    /**
     * Show OTP verification form
     */
    public function showVerifyForm()
    {
        if (!session('password_reset_phone')) {
            return redirect()->route('password.otp.request')
                ->withErrors(['error' => 'Please enter your phone number first.']);
        }

        return view('auth.passwords.otp-verify');
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $phone = session('password_reset_phone');

        if (!$phone) {
            return redirect()->route('password.otp.request')
                ->withErrors(['error' => 'Session expired. Please try again.']);
        }

        // Verify OTP
        $result = $this->otpService->verify($phone, $request->code, 'password_reset');

        if (!$result['success']) {
            return back()->withErrors([
                'code' => $result['error'],
            ]);
        }

        // Store verification status
        session(['otp_verified' => true]);

        return redirect()->route('password.otp.reset.form')
            ->with('success', 'OTP verified. Please set your new password.');
    }

    /**
     * Show password reset form
     */
    public function showResetForm()
    {
        if (!session('otp_verified') || !session('password_reset_phone')) {
            return redirect()->route('password.otp.request')
                ->withErrors(['error' => 'Please verify your OTP first.']);
        }

        return view('auth.passwords.otp-reset');
    }

    /**
     * Reset password
     */
    public function reset(Request $request)
    {
        $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $phone = session('password_reset_phone');
        $verified = session('otp_verified');

        if (!$phone || !$verified) {
            return redirect()->route('password.otp.request')
                ->withErrors(['error' => 'Session expired. Please try again.']);
        }

        // Find user
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            return redirect()->route('password.otp.request')
                ->withErrors(['error' => 'User not found.']);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Clear session
        session()->forget(['password_reset_phone', 'otp_verified']);

        Log::info('Password reset via OTP', [
            'user_id' => $user->id,
            'phone' => $phone,
        ]);

        return redirect()->route('login')
            ->with('success', 'Password reset successfully. You can now login with your new password.');
    }

    /**
     * Resend OTP
     */
    public function resendOtp(Request $request)
    {
        $phone = session('password_reset_phone');

        if (!$phone) {
            return redirect()->route('password.otp.request')
                ->withErrors(['error' => 'Session expired. Please try again.']);
        }

        // Generate and send new OTP
        $result = $this->otpService->generate($phone, 'password_reset');

        if (!$result['success']) {
            return back()->withErrors([
                'error' => $result['error'],
            ]);
        }

        return back()->with('success', 'New OTP sent to your phone.');
    }
}

