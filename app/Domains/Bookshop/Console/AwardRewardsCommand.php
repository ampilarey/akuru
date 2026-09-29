<?php

namespace App\Domains\Bookshop\Console;

use App\Domains\Bookshop\Actions\Money\LoyaltyRewardsAction;
use Illuminate\Console\Command;

/** STATUS §5lm, daily on the existing schedule: rewards for orders past their return window, while the office has them on. */
class AwardRewardsCommand extends Command
{
    protected $signature = 'bookshop:award-rewards';

    protected $description = 'Pay Bookstore rewards into customers\' wallets for orders past their return window';

    public function handle(): int
    {
        $count = app(LoyaltyRewardsAction::class)->awardDue();
        $this->line("Rewards paid: {$count}");

        return self::SUCCESS;
    }
}
