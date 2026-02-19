<?php

namespace App\Services;

class ContactNormalizer
{
    protected string $defaultCountryCode;

    public function __construct(?string $defaultCountryCode = null)
    {
        $this->defaultCountryCode = $defaultCountryCode ?? config('registration.default_country_code', '960');
    }

    /**
     * Normalize phone to E.164 format.
     * Requires +countrycode. If missing, adds default (Maldives 960).
     */
    public function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/[^\d]/', '', $raw);

        // Already has country code (e.g. 9607771234 → +9607771234)
        if (str_starts_with($digits, $this->defaultCountryCode)) {
            return '+' . $digits;
        }

        // 7-digit Maldives local number (e.g. 7771234 → +9607771234)
        if (strlen($digits) === 7 && $this->defaultCountryCode === '960') {
            return '+' . $this->defaultCountryCode . $digits;
        }

        return '+' . $digits;
    }

    /**
     * Normalize email to lowercase and trimmed.
     */
    public function normalizeEmail(string $raw): string
    {
        return strtolower(trim($raw));
    }

    /**
     * Normalize contact value based on type.
     */
    public function normalize(string $type, string $value): string
    {
        return $type === 'mobile' ? $this->normalizePhone($value) : $this->normalizeEmail($value);
    }
}
