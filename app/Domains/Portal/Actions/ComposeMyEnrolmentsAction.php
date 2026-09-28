<?php

namespace App\Domains\Portal\Actions;

use App\Domains\Courses\Actions\ListEnrolmentsMadeByAction;
use App\Domains\Finance\Actions\ListCoursePaymentsForUserAction;

/**
 * *My enrolments* (docs/SIGN_IN_PLAN.md ID2b): every enrolment a login made,
 * a child's included, and the course payments beside them — one screen for
 * what the old course portal spread over four Blade pages (its dashboard,
 * enrolments, payments and certificates) and the old My enrolments page
 * repeated. An enrolment links its receipt when its payment is this login's
 * and the money is confirmed; the receipt page applies the same rule.
 */
class ComposeMyEnrolmentsAction
{
    /**
     * @return array{enrolments: list<array<string, mixed>>, payments: list<array<string, mixed>>}
     */
    public function execute(int $userId): array
    {
        $payments = app(ListCoursePaymentsForUserAction::class)->execute($userId);
        $receipts = array_column($payments, 'receipt_href', 'id');

        $enrolments = array_map(
            fn (array $row): array => $row + ['receipt_href' => $row['payment_id'] !== null ? ($receipts[$row['payment_id']] ?? null) : null],
            app(ListEnrolmentsMadeByAction::class)->execute($userId),
        );

        return ['enrolments' => $enrolments, 'payments' => $payments];
    }
}
