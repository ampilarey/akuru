<?php

namespace App\Console\Commands;

use App\Models\UserContact;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Clear OTP rate limit for a contact so they can request/verify again.
 * Use when a number is stuck on "too many attempts".
 */
class ClearOtpRateLimitCommand extends Command
{
    protected $signature = 'otp:clear-rate-limit {contact : Phone number (e.g. 7820288) or email}';

    protected $description = 'Clear OTP send/verify rate limit for a contact (phone or email)';

    public function handle(): int
    {
        $input = $this->argument('contact');

        $contacts = UserContact::query()
            ->where(function ($q) use ($input) {
                $q->where('value', 'like', '%' . preg_replace('/\D/', '', $input) . '%')
                    ->orWhere('value', 'like', '%' . $input . '%');
            })
            ->get();

        if ($contacts->isEmpty()) {
            $this->warn("No contact found matching: {$input}");

            return self::FAILURE;
        }

        $purposes = ['verify_contact', 'password_reset'];

        foreach ($contacts as $contact) {
            foreach ($purposes as $purpose) {
                RateLimiter::clear('otp:send:' . $contact->id . ':' . $purpose);
                RateLimiter::clear('otp:verify:' . $contact->id . ':' . $purpose);
            }
            $this->info("Cleared OTP rate limit for contact #{$contact->id} ({$contact->type}: {$contact->value}).");
        }

        return self::SUCCESS;
    }
}
