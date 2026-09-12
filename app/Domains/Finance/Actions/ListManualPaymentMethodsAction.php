<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\PaymentMethod;

/**
 * SPEC §38's "Payment method" vocabulary, for the screens that have to offer
 * it and the validators that have to accept it.
 *
 * It exists as an Action rather than the enum being imported directly because
 * rule 3 allows cross-domain traffic only through Contracts/DTOs/Events/
 * Actions — an enum is none of those, and `BaselineArchitectureTest` says so
 * out loud (it caught the first version of this slice importing
 * `Finance\Enums\PaymentMethod` into Admissions). The enum stays inside
 * Finance; everyone else asks for the list.
 *
 * Only the methods an admin can assert by hand are returned. Wallet and gift
 * card are spent inside the product and already recorded by the Commerce
 * ledger, so offering them here would invite a second record of the same
 * money (rule 11).
 */
class ListManualPaymentMethodsAction
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public function execute(): array
    {
        return array_map(
            static fn (PaymentMethod $method): array => [
                'value' => $method->value,
                'label' => $method->label(),
            ],
            PaymentMethod::manualCases(),
        );
    }

    /**
     * @return list<string>
     */
    public function values(): array
    {
        return array_column($this->execute(), 'value');
    }
}
