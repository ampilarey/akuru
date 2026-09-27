<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * The office's payments list (SPEC §49 payment reports; C9 slice 4, STATUS
 * §5jf): every gateway and manual payment, newest first, with the search
 * and the status filter. One query serves the screen and its CSV. The
 * refundable balance is worked out here so the screen only shows a refund
 * form where money can still come back.
 */
class ListAdminPaymentsAction
{
    public const PER_PAGE = 20;

    public const STATUSES = ['confirmed', 'pending', 'failed', 'expired', 'refunded'];

    /**
     * @param  array<string, mixed>  $filters  status, search
     */
    public function query(array $filters): Builder
    {
        $query = Payment::with(['user', 'student', 'items.course', 'refunds'])->latest()->orderByDesc('id');

        if (in_array($status = (string) ($filters['status'] ?? ''), self::STATUSES, true)) {
            $query->where('status', $status);
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('merchant_reference', 'like', "%{$search}%")
                    ->orWhere('local_id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($u) use ($search) {
                        $u->where('name', 'like', "%{$search}%")
                            ->orWhereHas('contacts', fn ($c) => $c->where('value', 'like', "%{$search}%"));
                    })
                    ->orWhereHas('student', function ($s) use ($search) {
                        $s->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{payments: list<array<string, mixed>>, pagination: array<string, mixed>, total: int}
     */
    public function execute(array $filters): array
    {
        $page = $this->query($filters)->paginate(self::PER_PAGE)->withQueryString();

        return [
            'payments' => collect($page->items())->map(function (Payment $p) {
                $refunded = round((float) $p->refunds->sum('amount'), 2);
                $refundable = round((float) $p->amount - $refunded, 2);

                return [
                    'id' => $p->id,
                    'reference' => Str::limit((string) $p->merchant_reference, 30),
                    'payer' => $p->user?->name,
                    'student' => $p->student?->full_name,
                    'amount' => number_format((float) $p->amount, 2),
                    'currency' => (string) $p->currency,
                    'status' => (string) $p->status,
                    'date' => $p->created_at?->format('d M Y'),
                    'refunded' => $refunded > 0 ? number_format($refunded, 2) : null,
                    // A refund form is offered only where money can still come back;
                    // a string with two decimals, so the form's max and default agree.
                    'refundable' => in_array($p->status, ['confirmed', 'paid'], true) && $refundable > 0 ? number_format($refundable, 2, '.', '') : null,
                ];
            })->values()->all(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'prev' => $page->previousPageUrl(),
                'next' => $page->nextPageUrl(),
            ],
            'total' => $page->total(),
        ];
    }
}
