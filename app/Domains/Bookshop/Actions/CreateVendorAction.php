<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Enums\VendorMemberRole;
use App\Domains\Bookshop\Enums\VendorStatus;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The office invites a vendor (BOOKSHOP_PLAN §3, §7): the shop and its
 * owner in one step. The slug — the storefront's address — is fixed here
 * and never edited afterwards; the three-letter code goes on every order
 * number (audit finding 16).
 */
class CreateVendorAction
{
    public const RESERVED_SLUGS = ['products', 'c', 'export', 'cart', 'checkout', 'orders', 'search', 'slips'];

    /**
     * @param  array<string, mixed>  $data
     * @return array{vendor_id: int, slug: string, owner_user_id: int, owner_created: bool, temporary_password: ?string}
     */
    public function execute(array $data, int $byUserId): array
    {
        return DB::transaction(function () use ($data, $byUserId) {
            $slug = $this->uniqueSlug((string) ($data['slug'] ?? '') ?: (string) $data['name']);
            $code = $this->uniqueCode((string) ($data['code'] ?? ''), (string) $data['name']);

            $vendor = Vendor::query()->create([
                'name' => $data['name'],
                'slug' => $slug,
                'code' => $code,
                'tagline' => $data['tagline'] ?? null,
                'legal_name' => $data['legal_name'] ?? null,
                'tin' => $data['tin'] ?? null,
                'gst_registered' => (bool) ($data['gst_registered'] ?? false),
                'status' => VendorStatus::Active->value,
                'commission_rate' => $data['commission_rate'] ?? null,
                'contact_email' => $data['contact_email'] ?? $data['owner_email'],
                'contact_phone' => $data['contact_phone'] ?? $data['owner_phone'] ?? null,
                'address' => $data['address'] ?? null,
                'opening_hours' => $data['opening_hours'] ?? null,
                'created_by' => $byUserId,
            ]);

            $account = app(EnsureVendorAccountAction::class)->execute(
                (string) $data['owner_name'],
                (string) $data['owner_email'],
                $data['owner_phone'] ?? null,
            );

            VendorMember::query()->create([
                'vendor_id' => $vendor->id,
                'user_id' => $account['user_id'],
                'role' => VendorMemberRole::Owner->value,
                'added_by' => $byUserId,
            ]);

            return [
                'vendor_id' => (int) $vendor->id,
                'slug' => $slug,
                'owner_user_id' => $account['user_id'],
                'owner_created' => $account['created'],
                'temporary_password' => $account['temporary_password'],
            ];
        });
    }

    private function uniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'vendor';
        $base = Str::limit($base, 70, '');
        // The shop's own addresses (`/shop/products`, `/shop/c`, …) are not
        // vendor names (B1b routes).
        if (in_array($base, self::RESERVED_SLUGS, true)) {
            $base .= '-shop';
        }
        $slug = $base;
        for ($n = 2; Vendor::query()->where('slug', $slug)->exists(); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }

    /** Three letters, unique: the office's choice, else the name's first letters. */
    private function uniqueCode(string $requested, string $name): string
    {
        $requested = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $requested) ?? '');
        if ($requested !== '') {
            if (strlen($requested) !== 3 || Vendor::query()->where('code', $requested)->exists()) {
                throw ValidationException::withMessages(['code' => __('shop.error_code_taken')]);
            }

            return $requested;
        }

        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', Str::ascii($name)) ?? '');
        $base = str_pad(substr($letters, 0, 3), 3, 'X');
        if (! Vendor::query()->where('code', $base)->exists()) {
            return $base;
        }
        for ($n = 2; $n <= 99; $n++) {
            $candidate = substr($base, 0, 3 - strlen((string) $n)).$n;
            if (! Vendor::query()->where('code', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw ValidationException::withMessages(['code' => __('shop.error_code_needed')]);
    }
}
