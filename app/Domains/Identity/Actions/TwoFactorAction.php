<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\Totp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Two-step sign-in with an authenticator app (STATUS §5lk). A person turns
 * it on for their own account: a new secret, confirmed by a first code, and
 * eight one-use recovery codes shown once. From then on a password (or an
 * OTP) is followed by a code. Turning it off, or new recovery codes, needs
 * the password. A code is never accepted twice (the last step is kept).
 */
class TwoFactorAction
{
    public const RECOVERY_CODES = 8;

    /**
     * @return array{enabled: bool, pending: bool, recovery_left: int}
     */
    public function status(User $user): array
    {
        return [
            'enabled' => $user->hasTwoFactor(),
            'pending' => ! $user->hasTwoFactor() && $user->two_factor_secret !== null,
            'recovery_left' => $user->hasTwoFactor() ? count((array) $user->two_factor_recovery_codes) : 0,
        ];
    }

    /**
     * A new secret, not yet on: the person scans it, then confirms with a code.
     *
     * @return array{secret: string, uri: string}
     */
    public function start(User $user): array
    {
        if ($user->hasTwoFactor()) {
            throw ValidationException::withMessages(['code' => __('security.error_already_on')]);
        }
        $secret = Totp::secret();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null, 'two_factor_last_step' => null])->save();

        return $this->pending($user);
    }

    /**
     * The secret waiting for its first code, for the setup screen.
     *
     * @return array{secret: string, uri: string}|null
     */
    public function pendingFor(User $user): ?array
    {
        return ! $user->hasTwoFactor() && $user->two_factor_secret !== null ? $this->pending($user) : null;
    }

    /**
     * The first code turns it on; the recovery codes come back once.
     *
     * @return list<string>
     */
    public function confirm(User $user, string $code): array
    {
        if ($user->hasTwoFactor() || $user->two_factor_secret === null) {
            throw ValidationException::withMessages(['code' => __('security.error_start_first')]);
        }
        $step = Totp::matchStep((string) $user->two_factor_secret, $code);
        if ($step === null) {
            throw ValidationException::withMessages(['code' => __('security.error_code')]);
        }
        $codes = $this->newRecoveryCodes();
        $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_last_step' => $step, 'two_factor_recovery_codes' => $codes])->save();

        return $codes;
    }

    public function disable(User $user, string $password): void
    {
        $this->checkPassword($user, $password);
        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null, 'two_factor_last_step' => null])->save();
    }

    /** @return list<string> */
    public function regenerateRecoveryCodes(User $user, string $password): array
    {
        $this->checkPassword($user, $password);
        if (! $user->hasTwoFactor()) {
            throw ValidationException::withMessages(['code' => __('security.error_start_first')]);
        }
        $codes = $this->newRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return $codes;
    }

    /**
     * At sign-in: a code from the app (never one already used), or one of the
     * recovery codes, which is then spent.
     */
    public function verify(User $user, string $code): bool
    {
        if (! $user->hasTwoFactor()) {
            return false;
        }
        $step = Totp::matchStep((string) $user->two_factor_secret, $code);
        if ($step !== null) {
            if ($user->two_factor_last_step !== null && $step <= (int) $user->two_factor_last_step) {
                return false;
            }
            $user->forceFill(['two_factor_last_step' => $step])->save();

            return true;
        }

        $given = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
        $codes = (array) $user->two_factor_recovery_codes;
        foreach ($codes as $i => $stored) {
            if ($given !== '' && hash_equals(strtolower(str_replace('-', '', (string) $stored)), $given)) {
                unset($codes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    /** @return array{secret: string, uri: string} */
    private function pending(User $user): array
    {
        $secret = (string) $user->two_factor_secret;

        return ['secret' => trim(chunk_split($secret, 4, ' ')), 'uri' => Totp::uri($secret, (string) ($user->email ?: $user->phone ?: $user->name), (string) config('app.name', 'Akuru'))];
    }

    /** @return list<string> */
    private function newRecoveryCodes(): array
    {
        return array_map(fn () => strtolower(Str::random(5).'-'.Str::random(5)), range(1, self::RECOVERY_CODES));
    }

    private function checkPassword(User $user, string $password): void
    {
        if (! Hash::check($password, (string) $user->password)) {
            throw ValidationException::withMessages(['password' => __('security.error_password')]);
        }
    }
}
