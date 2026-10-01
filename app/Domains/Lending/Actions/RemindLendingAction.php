<?php

namespace App\Domains\Lending\Actions;

use App\Domains\Lending\Enums\LoanStatus;
use App\Domains\Lending\Models\LendingLoan;
use Illuminate\Support\Carbon;

/**
 * The daily reminders (L2), to both sides of every book out on loan: two
 * days before it is due (once), on the day (once), and every day it is
 * overdue (once a day). Marks on the loan row make a re-run harmless.
 */
class RemindLendingAction
{
    /** @return int notices sent */
    public function execute(?Carbon $today = null): int
    {
        $today = ($today ?? now())->startOfDay();
        $before = (int) config('lending.reminders.days_before', 2);
        $sent = 0;

        $loans = LendingLoan::query()->where('status', LoanStatus::Out->value)->whereNotNull('due_on')
            ->whereDate('due_on', '<=', $today->copy()->addDays($before)->toDateString())
            ->with(['book', 'lender'])->orderBy('id')->get();

        foreach ($loans as $loan) {
            $due = $loan->due_on->copy()->startOfDay();
            $days = (int) round($today->diffInDays($due, false));
            if ($days > 0 && $days <= $before && $loan->reminded_before_at === null) {
                $sent += $this->both($loan, 'before', ['days' => (int) $days]);
                $loan->forceFill(['reminded_before_at' => now()])->save();
            } elseif ($days === 0 && $loan->reminded_due_at === null) {
                $sent += $this->both($loan, 'due', []);
                $loan->forceFill(['reminded_due_at' => now()])->save();
            } elseif ($days < 0 && $loan->last_overdue_reminder_on?->toDateString() !== $today->toDateString()) {
                $sent += $this->both($loan, 'overdue', ['days' => (int) abs($days)]);
                $loan->forceFill(['last_overdue_reminder_on' => $today->toDateString()])->save();
            }
        }

        return $sent;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function both(LendingLoan $loan, string $kind, array $extra): int
    {
        $notify = app(NotifyLendingUserAction::class);
        $params = $extra + ['title' => $loan->book->title, 'due' => $loan->due_on->toDateString()];
        $title = __('lending.remind_'.$kind.'_title');
        $notify->execute((int) $loan->borrower_user_id, $title, __('lending.remind_'.$kind.'_borrower', $params + ['name' => $loan->lender->display_name]), '/my-lending#borrowing', 'remind_'.$kind);
        $notify->execute((int) $loan->lender->user_id, $title, __('lending.remind_'.$kind.'_lender', $params), '/my-lending#lending', 'remind_'.$kind);

        return 2;
    }
}
