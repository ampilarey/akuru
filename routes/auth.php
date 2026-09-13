<?php

use App\Domains\Identity\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Domains\Identity\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Domains\Identity\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Domains\Identity\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Domains\Identity\Http\Controllers\Auth\OtpLoginController;
use App\Domains\Identity\Http\Controllers\Auth\OtpPasswordResetController;
use App\Domains\Identity\Http\Controllers\Auth\PasswordController;
use App\Domains\Identity\Http\Controllers\Auth\PasswordOtpController;
use App\Domains\Identity\Http\Controllers\Auth\RegisteredUserController;
use App\Domains\Identity\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    // SPEC §32 "Throttle: ... Registration attempts". This had no limit of any
    // kind, and `unique:users,email` answers "does this address already have an
    // account?" on every attempt.
    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:auth-register');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordOtpController::class, 'showRequestForm'])
        ->name('password.request');

    // SPEC §32 "Throttle: ... Password reset requests", per IP. The per-contact
    // limit in `OtpService` cannot see this attack: the controller looks a user
    // up by national ID, passport, email or phone **before** reaching the
    // service, so every probe uses a different contact and never touches that
    // counter — leaving an unlimited "does this identifier exist?" oracle.
    Route::post('forgot-password', [PasswordOtpController::class, 'sendOtp'])
        ->middleware('throttle:auth-password-reset')
        ->name('password.email');

    Route::get('reset-password/verify', [PasswordOtpController::class, 'showVerifyForm'])
        ->name('password.reset.verify');

    Route::post('reset-password/verify', [PasswordOtpController::class, 'verifyOtp'])
        ->name('password.reset.verify.store');

    Route::get('reset-password', [PasswordOtpController::class, 'showResetForm'])
        ->name('password.reset');

    Route::post('reset-password', [PasswordOtpController::class, 'resetPassword'])
        ->middleware('throttle:auth-password-reset')
        ->name('password.store');

    // OTP Login Routes
    Route::get('otp/login', [OtpLoginController::class, 'showLoginForm'])
        ->name('otp.login.form');

    // §32 per IP, across all contacts. `config/otp.php`'s per-phone cap is the
    // tighter limit for one target; this one sees an attack spread over many.
    Route::post('otp/request', [OtpLoginController::class, 'requestOtp'])
        ->middleware('throttle:auth-otp-request')
        ->name('otp.request');

    Route::get('otp/verify', [OtpLoginController::class, 'showVerifyForm'])
        ->name('otp.verify.form');

    Route::post('otp/verify', [OtpLoginController::class, 'verifyOtp'])
        ->name('otp.verify');

    Route::post('otp/resend', [OtpLoginController::class, 'resendOtp'])
        ->middleware('throttle:auth-otp-request')
        ->name('otp.resend');

    // OTP Password Reset Routes
    Route::get('password/otp/request', [OtpPasswordResetController::class, 'showRequestForm'])
        ->name('password.otp.request');

    Route::post('password/otp/send', [OtpPasswordResetController::class, 'requestOtp'])
        ->middleware('throttle:auth-password-reset')
        ->name('password.otp.send');

    Route::get('password/otp/verify', [OtpPasswordResetController::class, 'showVerifyForm'])
        ->name('password.otp.verify.form');

    Route::post('password/otp/verify', [OtpPasswordResetController::class, 'verifyOtp'])
        ->name('password.otp.verify');

    Route::get('password/otp/reset', [OtpPasswordResetController::class, 'showResetForm'])
        ->name('password.otp.reset.form');

    Route::post('password/otp/reset', [OtpPasswordResetController::class, 'reset'])
        ->name('password.otp.update');

    Route::post('password/otp/resend', [OtpPasswordResetController::class, 'resendOtp'])
        ->middleware('throttle:auth-password-reset')
        ->name('password.otp.resend');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
