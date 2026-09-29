<?php

namespace App\Domains\Bookshop\Console;

use App\Domains\Bookshop\Actions\Money\LoyaltyRewardsAction;
use App\Domains\Bookshop\Actions\Money\ReferralCreditAction;
use Illuminate\Console\Command;

/** STATUS §5lm/§5ln, daily on the existing schedule: rewards and referral credit for orders past their return window, while the office has each on. */
class AwardRewardsCommand extends Command
{
    protected $signature = 'bookshop:award-rewards';

    protected $description = 'Pay Bookstore rewards and referral credit into customers\' wallets for orders past their return window';

    public function handle(): int
    {
        $count = app(LoyaltyRewardsAction::class)->awardDue();
        $this->line("Rewards paid: {$count}");
        $referrals = app(ReferralCreditAction::class)->awardDue();
        $this->line("Referrals paid: {$referrals}");

        return self::SUCCESS;
    }
}
