<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\Actions\EnsureVendorAccountAction;
use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Enums\VendorMemberRole;
use App\Domains\Bookshop\Models\VendorMember;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * A vendor's people (BOOKSHOP_PLAN §5 "Members: invite by email, roles").
 * Everyone in the vendor may see the list; only the owner adds staff. A new
 * person gets the same one-time password as an invited owner (the portal
 * shows it once), an existing account is simply linked.
 */
class ManageVendorMembersAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(VendorScope $scope): array
    {
        $members = VendorMember::query()->where('vendor_id', $scope->vendorId)->orderBy('id')->get();

        $userModel = config('auth.providers.users.model');
        $people = $userModel::query()->whereIn('id', $members->pluck('user_id')->all())->get(['id', 'name', 'email'])->keyBy('id');

        return $members->map(fn (VendorMember $m) => [
            'id' => $m->id,
            'name' => $people->get($m->user_id)?->name ?? ('#'.$m->user_id),
            'email' => $people->get($m->user_id)?->email,
            'role' => $m->role->value,
            'agreement_accepted_at' => $m->agreement_accepted_at?->toDateTimeString(),
            'is_me' => (int) $m->user_id === $scope->userId,
        ])->values()->all();
    }

    /**
     * @param  array{name: string, email: string, phone?: ?string}  $data
     * @return array{created: bool, temporary_password: ?string}
     */
    public function addStaff(VendorScope $scope, array $data): array
    {
        if (! $scope->isOwner()) {
            throw new AuthorizationException(__('shop.owner_only'));
        }

        $account = app(EnsureVendorAccountAction::class)->execute($data['name'], $data['email'], $data['phone'] ?? null);

        $already = VendorMember::query()
            ->where('vendor_id', $scope->vendorId)
            ->where('user_id', $account['user_id'])
            ->exists();
        if ($already) {
            throw ValidationException::withMessages(['email' => __('shop.error_member_exists')]);
        }

        VendorMember::query()->create([
            'vendor_id' => $scope->vendorId,
            'user_id' => $account['user_id'],
            'role' => VendorMemberRole::Staff->value,
            'added_by' => $scope->userId,
        ]);

        return ['created' => $account['created'], 'temporary_password' => $account['temporary_password']];
    }
}
