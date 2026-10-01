<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderComplaint;
use App\Domains\Bookshop\Models\ShopCustomerNote;
use App\Domains\Bookshop\Models\ShopCustomerProfile;
use App\Domains\Bookshop\Models\ShopSmsOptin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * COMMERCE_PARITY_PLAN P7c: the office's view of the Bookstore's customers
 * — who bought, how often, how much, when last — with the office's own tags
 * and notes, each note with an optional follow-up date ticked off when done
 * (Bake & Grill's AdminCustomerController). A customer is anyone with a
 * paid order; the account itself stays Identity's, read through the
 * configured user model (rule 3).
 */
class ShopCustomersAction
{
    public const MAX_TAGS = 10;

    /**
     * @return list<array<string, mixed>> biggest spenders first
     */
    public function list(?string $search = null, ?string $tag = null, bool $followUps = false, int $limit = 200): array
    {
        $userModel = config('auth.providers.users.model');
        $stats = Order::query()->whereNotNull('paid_at')
            ->selectRaw('user_id, count(*) as orders, sum(total) as spent, max(created_at) as last_order')
            ->groupBy('user_id');
        $query = DB::query()->fromSub($stats, 's')
            ->join((new $userModel)->getTable().' as u', 'u.id', '=', 's.user_id')
            ->leftJoin('shop_customer_profiles as p', 'p.user_id', '=', 's.user_id')
            ->select('s.*', 'u.name', 'u.email', 'u.phone', 'p.tags');
        if ($search !== null && trim($search) !== '') {
            $like = '%'.trim($search).'%';
            $query->where(fn ($q) => $q->where('u.name', 'like', $like)->orWhere('u.email', 'like', $like)->orWhere('u.phone', 'like', $like)
                ->orWhereIn('s.user_id', Order::query()->select('user_id')->where('number', 'like', $like)));
        }
        if ($tag !== null && $tag !== '') {
            $query->whereJsonContains('p.tags', self::tag($tag));
        }
        $due = ShopCustomerNote::query()->whereNull('done_at')->whereNotNull('follow_up_on')->where('follow_up_on', '<=', now()->toDateString())
            ->selectRaw('user_id, count(*) as n')->groupBy('user_id')->pluck('n', 'user_id');
        if ($followUps) {
            $query->whereIn('s.user_id', $due->keys()->all() ?: [0]);
        }

        return $query->orderByDesc('spent')->limit($limit)->get()->map(fn ($r) => [
            'id' => (int) $r->user_id,
            'name' => $r->name,
            'email' => $r->email,
            'phone' => $r->phone,
            'orders' => (int) $r->orders,
            'spent' => number_format((float) $r->spent, 2, '.', ''),
            'last_order' => $r->last_order !== null ? substr((string) $r->last_order, 0, 10) : null,
            'tags' => $r->tags !== null ? (array) json_decode((string) $r->tags, true) : [],
            'follow_ups_due' => (int) ($due[$r->user_id] ?? 0),
        ])->values()->all();
    }

    /** @return list<string> every tag in use, for the filter */
    public function allTags(): array
    {
        return ShopCustomerProfile::query()->whereNotNull('tags')->pluck('tags')->flatten()->filter()->unique()->sort()->values()->all();
    }

    /** @return array<string, mixed>|null one customer, everything the office needs on one page */
    public function show(int $userId): ?array
    {
        $userModel = config('auth.providers.users.model');
        $user = $userModel::query()->find($userId, ['id', 'name', 'email', 'phone', 'created_at']);
        $orders = Order::query()->with('vendor:id,name')->where('user_id', $userId)->orderByDesc('id')->limit(100)->get();
        if ($user === null || $orders->isEmpty()) {
            return null;
        }
        $paid = $orders->whereNotNull('paid_at');
        $userNames = $userModel::query()->whereIn('id', ShopCustomerNote::query()->where('user_id', $userId)->pluck('author_id')->unique()->all())->pluck('name', 'id');

        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'joined' => $user->created_at?->format('Y-m-d'),
            'orders_count' => $paid->count(),
            'spent' => number_format((float) $paid->sum('total'), 2, '.', ''),
            'last_order' => $orders->first()->created_at?->format('Y-m-d'),
            'sms_offers' => ShopSmsOptin::query()->where('user_id', $userId)->whereNull('opted_out_at')->whereNotNull('opted_in_at')->exists(),
            'tags' => (array) (ShopCustomerProfile::query()->where('user_id', $userId)->value('tags') ?? []),
            'orders' => $orders->map(fn (Order $o) => [
                'number' => $o->number, 'shop' => $o->vendor?->name, 'status' => $o->status->value,
                'total' => (string) $o->total, 'placed_at' => $o->created_at?->format('Y-m-d'),
            ])->values()->all(),
            'complaints' => OrderComplaint::query()->with('order:id,number')->where('user_id', $userId)->orderByDesc('id')->limit(50)->get()->map(fn (OrderComplaint $c) => [
                'number' => $c->order?->number, 'kind' => $c->kind, 'status' => $c->status, 'created_at' => $c->created_at?->format('Y-m-d'),
            ])->values()->all(),
            'notes' => ShopCustomerNote::query()->where('user_id', $userId)->orderByDesc('id')->get()->map(fn (ShopCustomerNote $n) => [
                'id' => $n->id, 'body' => $n->body, 'author' => $userNames[$n->author_id] ?? null,
                'created_at' => $n->created_at?->format('Y-m-d H:i'), 'follow_up_on' => $n->follow_up_on?->format('Y-m-d'),
                'due' => $n->done_at === null && $n->follow_up_on !== null && $n->follow_up_on->lte(now()->startOfDay()),
                'done_at' => $n->done_at?->format('Y-m-d H:i'),
            ])->values()->all(),
        ];
    }

    /** @param  list<string>  $tags */
    public function saveTags(int $userId, array $tags, int $officeUserId): array
    {
        $this->mustBeCustomer($userId);
        $clean = array_values(array_unique(array_filter(array_map(fn ($t) => self::tag((string) $t), $tags))));
        if (count($clean) > self::MAX_TAGS) {
            throw ValidationException::withMessages(['tags' => __('shop.error_customer_tags', ['max' => self::MAX_TAGS])]);
        }
        ShopCustomerProfile::query()->updateOrCreate(['user_id' => $userId], ['tags' => $clean, 'updated_by' => $officeUserId]);

        return $clean;
    }

    public function addNote(int $userId, int $officeUserId, string $body, ?string $followUpOn = null): ShopCustomerNote
    {
        $this->mustBeCustomer($userId);

        return ShopCustomerNote::query()->create(['user_id' => $userId, 'author_id' => $officeUserId, 'body' => trim($body), 'follow_up_on' => $followUpOn ?: null]);
    }

    /** A follow-up is done; the note stays, with who did it and when. */
    public function done(int $userId, int $noteId, int $officeUserId): void
    {
        ShopCustomerNote::query()->where('user_id', $userId)->whereKey($noteId)->whereNull('done_at')->firstOrFail()
            ->update(['done_at' => now(), 'done_by' => $officeUserId]);
    }

    private function mustBeCustomer(int $userId): void
    {
        abort_unless(Order::query()->where('user_id', $userId)->exists(), 404);
    }

    /** Lower case, single spaces, at most 30 characters. */
    private static function tag(string $tag): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', mb_strtolower($tag)) ?? ''), 0, 30);
    }
}
