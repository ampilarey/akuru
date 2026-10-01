<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Jobs\SendShopSmsCampaignJob;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\ShopSmsCampaign;
use App\Domains\Bookshop\Models\ShopSmsCampaignRecipient;
use App\Domains\Bookshop\Models\ShopSmsOptin;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Support\SmsSegments;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\Settings\Actions\SetSettingAction;
use App\Domains\Settings\Contracts\SettingsRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * COMMERCE_PARITY_PLAN P7b: the Bookstore's SMS offers, the way the
 * prayer-times broadcasts work (recipients counted first, sent off the
 * request, one row per phone) with the rules marketing needs:
 *
 *  - **Only those who asked.** A customer opts in at checkout; nobody else
 *    is ever sent an offer.
 *  - **Stop is one step.** Every message ends with its own opt-out link,
 *    and a STOP reply (through the gateway's keyword hook, as prayer
 *    reminders) does the same. Either is final until they opt in again.
 *  - **The cost is shown before sending** — recipients × messages each ×
 *    the rate — and a month's campaigns cannot pass the office's budget.
 *
 * The office sends, to everyone who opted in or to those of them who bought
 * from one shop (at a shop's request; shops do not send on their own).
 */
class ShopSmsCampaignAction
{
    /** A customer asks for offers (at checkout). Saying it again after a stop opts back in. */
    public function optIn(?int $userId, string $phone, string $source = 'checkout'): ?ShopSmsOptin
    {
        $phone = self::phone($phone);
        if ($phone === '') {
            return null;
        }
        $optin = ShopSmsOptin::query()->firstOrNew(['phone' => $phone]);
        $optin->fill([
            'user_id' => $userId ?? $optin->user_id,
            'token' => $optin->token ?: $this->token(),
            'source' => $source,
            'opted_in_at' => now(),
            'opted_out_at' => null,
        ])->save();

        return $optin;
    }

    /** @return array{phone: string, subscribed: bool}|null for the opt-out page */
    public function find(string $token): ?array
    {
        $optin = ShopSmsOptin::query()->where('token', $token)->first();

        return $optin === null ? null : ['phone' => self::mask($optin->phone), 'subscribed' => $optin->opted_out_at === null];
    }

    public function optOut(string $token): bool
    {
        return ShopSmsOptin::query()->where('token', $token)->whereNull('opted_out_at')->update(['opted_out_at' => now(), 'updated_at' => now()]) > 0;
    }

    /** A reply of STOP (or UNSUBSCRIBE) from the gateway's keyword hook. */
    public function optOutByKeyword(string $phone, string $keyword): bool
    {
        $first = strtoupper((string) (preg_split('/\s+/', trim($keyword)) ?: [''])[0]);
        if (! in_array($first, ['STOP', 'UNSUBSCRIBE'], true)) {
            return false;
        }

        return ShopSmsOptin::query()->where('phone', self::phone($phone))->whereNull('opted_out_at')->update(['opted_out_at' => now(), 'updated_at' => now()]) > 0;
    }

    /**
     * Everything the campaigns page needs to show the cost before sending.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $settings = $this->settings();
        $spent = $this->spentThisMonth();
        $byShop = $this->optedIn()->join('orders', 'orders.user_id', '=', 'shop_sms_optins.user_id')
            ->whereNotNull('orders.paid_at')->selectRaw('orders.vendor_id, count(distinct shop_sms_optins.id) as n')->groupBy('orders.vendor_id')->pluck('n', 'vendor_id');

        return [
            'opted_in' => $this->optedIn()->count(),
            'shops' => Vendor::query()->orderBy('name')->get(['id', 'name'])->map(fn (Vendor $v) => ['id' => $v->id, 'name' => $v->name, 'opted_in_buyers' => (int) ($byShop[$v->id] ?? 0)])->values()->all(),
            'rate' => $settings['rate'],
            'budget' => $settings['budget'],
            'spent' => number_format($spent, 2, '.', ''),
            'left' => number_format(max(0, (float) $settings['budget'] - $spent), 2, '.', ''),
            // What every message carries at its end — counted in its length.
            'suffix_sample' => $this->suffix(str_repeat('x', 12)),
        ];
    }

    /**
     * The office sends. The audience is counted now, the cost fixed now, the
     * budget checked now; the messages go from the queue.
     */
    public function send(int $officeUserId, string $audience, ?int $vendorId, string $message): ShopSmsCampaign
    {
        $message = trim($message);
        if (! in_array($audience, ShopSmsCampaign::AUDIENCES, true) || ($audience === 'shop_buyers' && $vendorId === null)) {
            throw ValidationException::withMessages(['audience' => __('shop.error_campaign_audience')]);
        }
        if ($message === '') {
            throw ValidationException::withMessages(['message' => __('shop.error_campaign_message')]);
        }
        $vendorId = $audience === 'shop_buyers' ? $vendorId : null;

        return DB::transaction(function () use ($officeUserId, $audience, $vendorId, $message) {
            // One campaign at a time against the month's budget.
            DB::table('shop_sms_campaigns')->lockForUpdate()->where('created_at', '>=', now()->startOfMonth())->get(['id']);
            $optins = $this->audience($vendorId);
            if ($optins->isEmpty()) {
                throw ValidationException::withMessages(['audience' => __('shop.error_campaign_nobody')]);
            }
            $settings = $this->settings();
            $segments = max(array_map(fn (ShopSmsOptin $o) => SmsSegments::count($message.$this->suffix($o->token)), $optins->all()));
            $cost = round($optins->count() * $segments * (float) $settings['rate'], 2);
            if ($this->spentThisMonth() + $cost > (float) $settings['budget'] + 0.0001) {
                throw ValidationException::withMessages(['message' => __('shop.error_campaign_budget', ['cost' => number_format($cost, 2), 'left' => number_format(max(0, (float) $settings['budget'] - $this->spentThisMonth()), 2)])]);
            }

            $campaign = ShopSmsCampaign::query()->create([
                'audience' => $audience, 'vendor_id' => $vendorId, 'message' => $message, 'recipients' => $optins->count(),
                'segments' => $segments, 'rate' => $settings['rate'], 'cost' => $cost, 'status' => 'queued', 'created_by' => $officeUserId,
            ]);
            foreach ($optins as $optin) {
                ShopSmsCampaignRecipient::query()->create([
                    'shop_sms_campaign_id' => $campaign->id, 'shop_sms_optin_id' => $optin->id, 'phone' => $optin->phone,
                    'body' => $message.$this->suffix($optin->token), 'status' => 'pending',
                ]);
            }
            DB::afterCommit(fn () => SendShopSmsCampaignJob::dispatch($campaign->id));

            return $campaign;
        });
    }

    /** The queue's half: each pending phone, unless it said stop in the meantime. */
    public function deliver(int $campaignId): void
    {
        $campaign = ShopSmsCampaign::query()->find($campaignId);
        if ($campaign === null || in_array($campaign->status, ['sent', 'failed'], true)) {
            return;
        }
        $campaign->update(['status' => 'sending']);
        $sender = app(SmsSenderInterface::class);
        $rows = ShopSmsCampaignRecipient::query()->where('shop_sms_campaign_id', $campaign->id)->where('status', 'pending')->get();
        $stopped = ShopSmsOptin::query()->whereIn('id', $rows->pluck('shop_sms_optin_id')->filter()->all())->whereNotNull('opted_out_at')->pluck('id')->all();
        foreach ($rows as $row) {
            if (in_array($row->shop_sms_optin_id, $stopped, true)) {
                $row->update(['status' => 'skipped', 'error' => 'opted_out']);

                continue;
            }
            try {
                $result = $sender->sendSms($row->phone, $row->body, ['type' => 'bookshop_campaign', 'reference' => 'shop-campaign-'.$campaign->id.'-'.$row->id]);
                $ok = (bool) ($result['success'] ?? false);
                $row->update(['status' => $ok ? 'sent' : 'failed', 'sent_at' => $ok ? now() : null, 'error' => $ok ? null : (string) ($result['error'] ?? 'send_failed')]);
            } catch (\Throwable $e) {
                $row->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 250)]);
            }
        }
        $counts = ShopSmsCampaignRecipient::query()->where('shop_sms_campaign_id', $campaign->id)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $sent = (int) ($counts['sent'] ?? 0);
        $failed = (int) ($counts['failed'] ?? 0);
        $campaign->update(['status' => $sent === 0 && $failed > 0 ? 'failed' : 'sent', 'sent_count' => $sent, 'failed_count' => $failed, 'finished_at' => now()]);
    }

    /** @return list<array<string, mixed>> newest first */
    public function list(int $limit = 100): array
    {
        return ShopSmsCampaign::query()->with('vendor:id,name')->orderByDesc('id')->limit($limit)->get()->map(fn (ShopSmsCampaign $c) => [
            'id' => $c->id, 'audience' => $c->audience, 'shop' => $c->vendor?->name, 'message' => $c->message,
            'recipients' => (int) $c->recipients, 'segments' => (int) $c->segments, 'cost' => (string) $c->cost,
            'status' => $c->status, 'sent' => (int) $c->sent_count, 'failed' => (int) $c->failed_count,
            'created_at' => $c->created_at?->format('Y-m-d H:i'),
        ])->values()->all();
    }

    /** @return array{rate: string, budget: string} */
    public function settings(): array
    {
        try {
            $stored = app(SettingsRepositoryInterface::class)->many(['bookshop_sms_rate' => null, 'bookshop_sms_budget' => null]);
        } catch (\Throwable) {
            $stored = [];
        }
        $rate = $stored['bookshop_sms_rate'] ?? null;
        $budget = $stored['bookshop_sms_budget'] ?? null;

        return [
            'rate' => number_format((float) ($rate !== null && $rate !== '' ? $rate : config('bookshop.campaigns.sms_rate', 0.25)), 2, '.', ''),
            'budget' => number_format((float) ($budget !== null && $budget !== '' ? $budget : config('bookshop.campaigns.monthly_budget', 500)), 2, '.', ''),
        ];
    }

    public function saveSettings(float $rate, float $budget): void
    {
        app(SetSettingAction::class)->execute('bookshop_sms_rate', number_format($rate, 2, '.', ''), 'string', 'bookshop', 'Bookstore SMS offers: cost of one message (MVR)');
        app(SetSettingAction::class)->execute('bookshop_sms_budget', number_format($budget, 2, '.', ''), 'string', 'bookshop', 'Bookstore SMS offers: monthly budget (MVR)');
    }

    /** Opted in and not stopped; for one shop, those of them with a paid order there. */
    private function audience(?int $vendorId)
    {
        $query = $this->optedIn()->select('shop_sms_optins.*');
        if ($vendorId !== null) {
            $query->whereIn('shop_sms_optins.user_id', Order::query()->select('user_id')->where('vendor_id', $vendorId)->whereNotNull('paid_at'));
        }

        return $query->orderBy('shop_sms_optins.id')->get();
    }

    private function optedIn()
    {
        return ShopSmsOptin::query()->whereNotNull('shop_sms_optins.opted_in_at')->whereNull('shop_sms_optins.opted_out_at');
    }

    private function spentThisMonth(): float
    {
        return (float) ShopSmsCampaign::query()->where('created_at', '>=', now()->startOfMonth())->where('status', '!=', 'failed')->sum('cost');
    }

    private function suffix(string $token): string
    {
        return ' '.__('shop.campaign_stop_suffix', ['url' => route('public.shop.sms.stop', $token)]);
    }

    private function token(): string
    {
        do {
            $token = Str::lower(Str::random(12));
        } while (ShopSmsOptin::query()->where('token', $token)->exists());

        return $token;
    }

    /** Digits only; a Maldivian number without its +960. */
    public static function phone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return strlen($digits) === 10 && str_starts_with($digits, '960') ? substr($digits, 3) : $digits;
    }

    private static function mask(string $phone): string
    {
        return strlen($phone) > 3 ? str_repeat('•', strlen($phone) - 3).substr($phone, -3) : $phone;
    }
}
