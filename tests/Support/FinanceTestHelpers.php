<?php

use App\Domains\Finance\Actions\SaveFeeItemAction;
use App\Domains\Finance\Enums\FeeFrequency;
use App\Domains\Finance\Enums\FeeItemType;
use App\Domains\Finance\Models\FeeItem;

function makeCatalogFeeItem(array $overrides = []): FeeItem
{
    return app(SaveFeeItemAction::class)->execute(array_merge([
        'name' => 'Tuition',
        'default_amount' => 1500,
        'type' => FeeItemType::Tuition->value,
        'frequency' => FeeFrequency::Monthly->value,
    ], $overrides));
}
