<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\ContactNormalizer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * STATUS §5ly, the owner (2026-09-30): "customer should be able to buy in
 * book store and digital library without sign in … check the bake and grill
 * repo, make same way". Bake & Grill's guest checkout takes a name and a
 * mobile number, makes the customer an account and signs them in; a number
 * that already has an account must sign in instead. This is that.
 *
 * What it deliberately does not do: the mobile number is **not** proved (no
 * code is sent), so it is kept on the account (`users.phone`, which the
 * checkout and the receipts read) but never becomes a sign-in identity — no
 * `user_contacts` row. A verified mobile contact is a login; one typed into a
 * checkout form is not, and taking the number's contact row would also lock
 * its real owner out of registering it later. The account starts with
 * `force_password_change`, the "nobody knows this password" state, so its
 * owner can set one from My account without being asked for the old one.
 *
 * Refused: a number that already belongs to an account that signs in with it.
 */
class StartGuestAccountAction
{
    public const HAS_ACCOUNT = 'has_account';

    public const INVALID_PHONE = 'invalid_phone';

    public function __construct(private readonly ContactNormalizer $normalizer) {}

    /**
     * @return array{user_id: int|null, refused: string|null}
     */
    public function execute(string $name, string $rawPhone): array
    {
        $phone = $this->normalizer->normalizePhone($rawPhone);
        if (strlen(ltrim($phone, '+')) < 7) {
            return ['user_id' => null, 'refused' => self::INVALID_PHONE];
        }

        if (app(FindUserIdByVerifiedMobileAction::class)->execute($phone) !== null) {
            return ['user_id' => null, 'refused' => self::HAS_ACCOUNT];
        }

        $user = User::query()->create([
            'name' => trim($name),
            'email' => null,
            'password' => Hash::make(Str::random(40)),
            'phone' => $phone,
            'is_active' => true,
            'force_password_change' => true,
        ]);

        return ['user_id' => (int) $user->id, 'refused' => null];
    }
}
