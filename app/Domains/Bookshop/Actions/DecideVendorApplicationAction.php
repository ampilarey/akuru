<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Models\VendorApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The office decides a shop application (BOOKSHOP_PLAN §3 and §7, slice
 * B9a). Approving creates the shop through `CreateVendorAction` — the same
 * path as the office's own invitation — with the applicant's account as
 * its owner and the Vendor Agreement dated from the application; the
 * office may set the commission rate and the three-letter code there and
 * then. Declining needs a note, which the applicant reads. Only a waiting
 * application can be decided.
 */
class DecideVendorApplicationAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(int $limit = 200): array
    {
        $apps = VendorApplication::query()->with('vendor:id,name,slug')
            ->orderByRaw('case when status = ? then 0 else 1 end', [VendorApplication::PENDING])
            ->orderByDesc('id')->limit($limit)->get();
        $userModel = config('auth.providers.users.model');
        $people = $userModel::query()->whereIn('id', $apps->pluck('user_id')->unique()->all())->get(['id', 'name', 'email'])->keyBy('id');

        return $apps->map(fn (VendorApplication $a) => [
            'id' => $a->id,
            'status' => $a->status,
            'shop_name' => $a->shop_name,
            'legal_name' => $a->legal_name,
            'tin' => $a->tin,
            'contact_email' => $a->contact_email,
            'contact_phone' => $a->contact_phone,
            'island' => $a->island,
            'what_they_sell' => $a->what_they_sell,
            'link' => $a->link,
            'applicant' => $people->get($a->user_id)?->name,
            'applicant_email' => $people->get($a->user_id)?->email,
            'submitted_at' => $a->created_at?->toDateTimeString(),
            'decided_at' => $a->decided_at?->toDateTimeString(),
            'decision_note' => $a->decision_note,
            'vendor' => $a->vendor ? ['name' => $a->vendor->name, 'slug' => $a->vendor->slug] : null,
        ])->values()->all();
    }

    /**
     * @param  array{commission_rate?: mixed, code?: ?string}  $terms
     */
    public function execute(int $applicationId, int $officeUserId, bool $approve, ?string $note = null, array $terms = []): VendorApplication
    {
        $note = trim((string) $note) !== '' ? trim((string) $note) : null;
        if (! $approve && $note === null) {
            throw ValidationException::withMessages(['note' => __('shop.error_decline_note')]);
        }

        $application = DB::transaction(function () use ($applicationId, $officeUserId, $approve, $note, $terms) {
            $application = VendorApplication::query()->whereKey($applicationId)->lockForUpdate()->firstOrFail();
            if ($application->status !== VendorApplication::PENDING) {
                throw ValidationException::withMessages(['application' => __('shop.error_application_decided')]);
            }
            $vendorId = null;
            if ($approve) {
                $userModel = config('auth.providers.users.model');
                $owner = $userModel::query()->findOrFail($application->user_id);
                $created = app(CreateVendorAction::class)->execute([
                    'name' => $application->shop_name,
                    'code' => $terms['code'] ?? null,
                    'legal_name' => $application->legal_name,
                    'tin' => $application->tin,
                    'commission_rate' => ($terms['commission_rate'] ?? null) !== null && $terms['commission_rate'] !== '' ? $terms['commission_rate'] : null,
                    'contact_email' => $application->contact_email,
                    'contact_phone' => $application->contact_phone,
                    'address' => $application->island,
                    'owner_user_id' => $application->user_id,
                    'owner_name' => $owner->name,
                    'agreement_accepted_at' => $application->agreement_accepted_at,
                ], $officeUserId);
                $vendorId = $created['vendor_id'];
            }
            $application->fill([
                'status' => $approve ? VendorApplication::APPROVED : VendorApplication::DECLINED,
                'decided_by' => $officeUserId,
                'decided_at' => now(),
                'decision_note' => $note,
                'vendor_id' => $vendorId,
            ])->save();

            return $application->refresh();
        });

        app(NotifyBookshopUserAction::class)->execute(
            (int) $application->user_id,
            __($approve ? 'shop.notice_application_approved_title' : 'shop.notice_application_declined_title'),
            $approve ? __('shop.notice_application_approved_body', ['shop' => $application->shop_name]) : __('shop.notice_application_declined_body', ['note' => (string) $note]),
            $approve ? '/vendor' : '/vendor/apply',
        );

        return $application;
    }
}
