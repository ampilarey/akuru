<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\ContactNormalizer;
use Illuminate\Support\Facades\Hash;

/**
 * The registration funnel's last step: an account that was created for a
 * contact takes on its owner's details and gets its first real password.
 *
 * Extracted from `CourseRegistrationController::setPassword`, which had grown
 * to 106 lines and which `ThinControllersTest` refused to let grow further —
 * correctly, since what was being added was a security check, and rule 5 puts
 * that in an Action rather than in a controller.
 *
 * It lives in **Identity**, not Admissions where the controller is: it writes
 * a `User` and its contacts, and rule 3 forbids one domain importing another's
 * models. The first draft sat in Admissions and `BaselineArchitectureTest`
 * refused it.
 *
 * **This Action does not authorise anything.** It writes what it is told to
 * write. Whether the caller has proved they own the account is decided before
 * this point, by the funnel checking that the OTP for *this* user id was
 * actually entered in *this* session — see the controller. That check is the
 * only thing standing between this Action and anybody who knows a phone
 * number, which is why it is named here as well as there.
 */
class ClaimAccountWithPasswordAction
{
    public function __construct(private readonly ContactNormalizer $normalizer) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $userId, array $data): User
    {
        $user = User::query()->findOrFail($userId);

        $usesNationalId = ($data['id_type'] ?? null) === 'national_id';

        $user->update([
            'name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')),
            'gender' => $data['gender'] ?? null,
            'date_of_birth' => $data['dob'] ?? null,
            'national_id' => $usesNationalId ? strtoupper(trim((string) ($data['national_id'] ?? ''))) : null,
            'passport' => $usesNationalId ? null : strtoupper(trim((string) ($data['passport'] ?? ''))),
            'password' => Hash::make($data['password']),
            // The account now has a password its owner chose, so it is no
            // longer in the "there is a hash here but nobody knows it" state.
            'force_password_change' => false,
        ]);

        $this->attachEmail($user, $data['email'] ?? null);

        return $user->refresh();
    }

    /**
     * An optional second contact, unverified — the funnel proved the mobile,
     * not this.
     */
    private function attachEmail(User $user, mixed $email): void
    {
        $email = trim((string) ($email ?? ''));

        if ($email === '') {
            return;
        }

        $normalized = $this->normalizer->normalizeEmail($email);

        if ($user->contacts()->where('type', 'email')->where('value', $normalized)->exists()) {
            return;
        }

        $user->contacts()->create([
            'type' => 'email',
            'value' => $normalized,
            'is_primary' => false,
            'verified_at' => null,
        ]);
    }
}
