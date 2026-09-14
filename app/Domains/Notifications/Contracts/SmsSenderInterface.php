<?php

namespace App\Domains\Notifications\Contracts;

/**
 * Rule 4 — SMS lives behind this interface, never an SDK in domain logic.
 *
 * **`sendOtp` is on here because `OtpService` calls it**, and for a long time
 * it was not. The interface declared `sendSms` alone; `SmsGatewayService`
 * happened to have `sendOtp` as well, and `OtpService` — typed against the
 * *interface* — called that.
 *
 * `LogSmsSender` is the binding used whenever live SMS is not allowed, which
 * is local, staging, and any production without `SMS_LIVE`. It implemented the
 * interface faithfully and therefore had no `sendOtp`. So every mobile OTP
 * threw, `OtpService::send` caught it and turned it into *"Unable to send
 * verification code. Please try again."*, and **OTP login could not work
 * anywhere except live-SMS production**.
 *
 * The lesson is the interface's, not the implementation's: a contract that
 * does not declare what its consumers call is not a contract, and the one
 * implementation that obeyed it was the one that broke.
 * `SmsSenderContractIsCompleteTest` now fails on any method called through
 * this interface that the interface does not declare.
 */
interface SmsSenderInterface
{
    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function sendSms(string $phoneNumber, string $message, array $options = []): array;

    /**
     * A one-time code, composed here rather than by each caller so the wording
     * and the `type` are the same on every driver.
     *
     * @return array<string, mixed>
     */
    public function sendOtp(string $phoneNumber, string $otp): array;
}
