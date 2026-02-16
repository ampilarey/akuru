<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\OtpLoginController;
use App\Http\Controllers\Auth\OtpPasswordResetController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [\App\Http\Controllers\Auth\PasswordOtpController::class, 'showRequestForm'])
        ->name('password.request');

    Route::post('forgot-password', [\App\Http\Controllers\Auth\PasswordOtpController::class, 'sendOtp'])
        ->name('password.email');

    Route::get('reset-password/verify', [\App\Http\Controllers\Auth\PasswordOtpController::class, 'showVerifyForm'])
        ->name('password.reset.verify');

    Route::post('reset-password/verify', [\App\Http\Controllers\Auth\PasswordOtpController::class, 'verifyOtp'])
        ->name('password.reset.verify.store');

    Route::get('reset-password', [\App\Http\Controllers\Auth\PasswordOtpController::class, 'showResetForm'])
        ->name('password.reset');

    Route::post('reset-password', [\App\Http\Controllers\Auth\PasswordOtpController::class, 'resetPassword'])
        ->name('password.store');

    // OTP Login Routes
    Route::get('otp/login', [OtpLoginController::class, 'showLoginForm'])
        ->name('otp.login.form');

    Route::post('otp/request', [OtpLoginController::class, 'requestOtp'])
        ->name('otp.request');

    Route::get('otp/verify', [OtpLoginController::class, 'showVerifyForm'])
        ->name('otp.verify.form');

    Route::post('otp/verify', [OtpLoginController::class, 'verifyOtp'])
        ->name('otp.verify');

    Route::post('otp/resend', [OtpLoginController::class, 'resendOtp'])
        ->name('otp.resend');

    // OTP Password Reset Routes
    Route::get('password/otp/request', [OtpPasswordResetController::class, 'showRequestForm'])
        ->name('password.otp.request');

    Route::post('password/otp/send', [OtpPasswordResetController::class, 'requestOtp'])
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
