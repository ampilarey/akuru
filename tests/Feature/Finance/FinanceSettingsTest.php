<?php

use App\Domains\Finance\Actions\ResolveFinanceSettingsAction;
use App\Domains\Finance\Enums\InvoiceMonthlyMode;
use App\Domains\Identity\Models\User;
use App\Domains\Settings\Contracts\SettingsRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * S4 audit D3 (STATUS §5fb): three billing settings were seeded, enforced
 * on every generation, reminder and defaulting run, and editable from no
 * screen. Now they have one, and the actions that read them see the change.
 */
it('shows the three billing settings to finance and saves them where the actions read', function () {
    $admin = actingPeopleAdmin(['finance.manage']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('finance.settings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Finance/Settings/Index')
            ->where('settings.invoice_monthly_mode', 'per_month')
            ->where('settings.invoice_reminder_days', 3)
            ->where('settings.plan_default_days', 14));

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->put(route('finance.settings.update'), [
            'invoice_monthly_mode' => 'consolidated',
            'invoice_reminder_days' => 7,
            'plan_default_days' => 30,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $resolved = app(ResolveFinanceSettingsAction::class)->execute();
    expect($resolved['monthly_mode'])->toBe(InvoiceMonthlyMode::Consolidated)
        ->and($resolved['reminder_days'])->toBe(7)
        ->and((int) app(SettingsRepositoryInterface::class)->get('finance.plan_default_days'))->toBe(30);

    // The Invoices screen starts on the saved mode.
    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('finance.invoices.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('monthlyMode', 'consolidated'));
});

it('refuses a mode it does not know and days out of range, and keeps zero reachable', function () {
    $admin = actingPeopleAdmin(['finance.manage']);

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->put(route('finance.settings.update'), ['invoice_monthly_mode' => 'weekly', 'invoice_reminder_days' => 3, 'plan_default_days' => 14])
        ->assertSessionHasErrors('invoice_monthly_mode');
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->put(route('finance.settings.update'), ['invoice_monthly_mode' => 'per_month', 'invoice_reminder_days' => 91, 'plan_default_days' => 14])
        ->assertSessionHasErrors('invoice_reminder_days');
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->put(route('finance.settings.update'), ['invoice_monthly_mode' => 'per_month', 'invoice_reminder_days' => 3, 'plan_default_days' => -1])
        ->assertSessionHasErrors('plan_default_days');

    // Zero is the day it falls due, and must stay a valid answer.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->put(route('finance.settings.update'), ['invoice_monthly_mode' => 'per_month', 'invoice_reminder_days' => 0, 'plan_default_days' => 0])
        ->assertSessionHasNoErrors();
    expect(app(ResolveFinanceSettingsAction::class)->execute()['reminder_days'])->toBe(0);
});

it('keeps the screen away from an account without finance.manage', function () {
    $this->withoutLocalizationMiddleware()
        ->actingAs(User::factory()->create())
        ->get(route('finance.settings.index'))
        ->assertForbidden();
});
