<?php

namespace App\Domains\Finance\Providers;

use App\Domains\Finance\Contracts\BankStatementParserInterface;
use App\Domains\Finance\Contracts\PaymentProviderInterface;
use App\Domains\Finance\Events\PaymentConfirmed;
use App\Domains\Finance\Listeners\SendPaymentConfirmationNotices;
use App\Domains\Finance\Services\ConfiguredCsvBankStatementParser;
use App\Domains\Finance\Services\Payment\PaymentService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class FinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentProviderInterface::class, function ($app) {
            $driver = config('payments.providers.'.config('payments.default').'.driver');

            return $app->make($driver);
        });

        $this->app->singleton(PaymentService::class);

        // Rule 4: the bank's file format never reaches domain logic. Only
        // `csv` ships; a bank that exports anything else becomes a new
        // implementation and a driver change, not an edit to the importer.
        $this->app->bind(BankStatementParserInterface::class, function () {
            return match ((string) config('finance.bank_statement.driver', 'csv')) {
                default => new ConfiguredCsvBankStatementParser,
            };
        });
    }

    public function boot(): void
    {
        // SPEC §41: "Cross-domain side effects must use events/listeners …
        // must not directly call notification implementation classes." These
        // four notices used to be private methods on PaymentService, called by
        // hand after it fired the event — so `RecordManualPaymentAction`, which
        // fires the same event, activated enrollments and told nobody.
        Event::listen(PaymentConfirmed::class, SendPaymentConfirmationNotices::class);
    }
}
