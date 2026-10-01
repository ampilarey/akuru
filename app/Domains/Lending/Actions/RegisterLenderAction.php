<?php

namespace App\Domains\Lending\Actions;

use App\Domains\Identity\Actions\IdentityVerificationAction;
use App\Domains\Lending\Models\Lender;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * A person becomes a lender, or changes how they appear (L1): the name
 * borrowers see, their island, a few words, and whether they lend only to
 * borrowers with a checked ID (D5). Their own card goes to the office
 * through the Identity domain; until it is checked their books stay off the
 * public shelf.
 */
class RegisterLenderAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $userId, array $data): Lender
    {
        $text = fn (mixed $v, int $max) => is_string($v) && trim($v) !== '' ? mb_substr(trim($v), 0, $max) : null;

        $lender = Lender::query()->updateOrCreate(['user_id' => $userId], [
            'display_name' => (string) $text($data['display_name'] ?? null, 120),
            'island' => $text($data['island'] ?? null, 120),
            'about' => $text($data['about'] ?? null, 1000),
            'id_required' => (bool) ($data['id_required'] ?? false),
        ]);
        // L4: the person wears the `lender` role from here, so Manage users
        // shows it beside Vendor and Writer; the office may take it away.
        $userModel = config('auth.providers.users.model');
        $user = $userModel::query()->whereKey($userId)->first();
        if ($user !== null && ! $user->hasRole('lender')) {
            Role::findOrCreate('lender', 'web');
            $user->assignRole('lender');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return $lender;
    }

    /** L2: the lender pauses or resumes themselves — unless the office paused them. */
    public function setStatus(int $userId, string $status): Lender
    {
        $lender = Lender::query()->where('user_id', $userId)->firstOrFail();
        if ($lender->office_paused) {
            throw ValidationException::withMessages(['status' => __('lending.error_office_paused')]);
        }
        $lender->update(['status' => $status === Lender::PAUSED ? Lender::PAUSED : Lender::ACTIVE]);

        return $lender->refresh();
    }

    public function sendCard(int $userId, UploadedFile $front, UploadedFile $back): void
    {
        app(IdentityVerificationAction::class)->submit($userId, 'lender', $front, $back);
    }

    /** Whether this lender's books may be shown: the office has checked an ID card for lending. */
    public static function verified(int $userId): bool
    {
        return app(IdentityVerificationAction::class)->anyVerified([$userId], 'lender');
    }

    /**
     * Where the person stands as a lender, for My lending.
     *
     * @return array{lender: ?array<string, mixed>, id: array<string, mixed>}
     */
    public function status(int $userId): array
    {
        $lender = Lender::query()->where('user_id', $userId)->first();

        return [
            'lender' => $lender === null ? null : [
                'id' => $lender->id,
                'display_name' => $lender->display_name,
                'island' => $lender->island,
                'about' => $lender->about,
                'id_required' => (bool) $lender->id_required,
                'status' => $lender->status,
                'office_paused' => (bool) $lender->office_paused,
                'office_note' => $lender->office_note,
                'rating' => RateLendingAction::lenderSummary($lender),
            ],
            'id' => app(IdentityVerificationAction::class)->status($userId, 'lender') + ['verified' => self::verified($userId)],
        ];
    }
}
