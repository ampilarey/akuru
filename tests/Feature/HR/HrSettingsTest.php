<?php

use App\Domains\HR\Actions\ResolveHrChecklistSettingsAction;
use App\Domains\HR\Actions\ResolveHrSettingsAction;
use App\Domains\HR\Actions\ResolvePayrollSettingsAction;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * S5 audit D3 (STATUS §5fe): five HR and payroll settings were read on every
 * request and editable from no screen. Now they have one, and the resolvers
 * the engine reads see the change.
 */
it('shows the HR settings and saves them where the resolvers read', function () {
    $admin = actingPeopleAdmin(['hr.manage']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('hr.settings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('HR/Settings/Index')
            ->where('hr.staff_self_checkin', false)
            ->where('hr.onboarding.0', 'Contract signed')
            ->where('payroll.rules.working_days', 22)
            ->where('payroll.environment_on', false)
            ->where('canApprovePayroll', false));

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->put(route('hr.settings.update'), [
            'staff_self_checkin' => true,
            'onboarding_items' => "Contract signed\n\nLaptop issued  \nInduction completed",
            'offboarding_items' => ['Revoke roles', 'Return laptop'],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(app(ResolveHrSettingsAction::class)->execute()['staff_self_checkin'])->toBeTrue()
        ->and(app(ResolveHrChecklistSettingsAction::class)->execute())->toBe([
            'onboarding' => ['Contract signed', 'Laptop issued', 'Induction completed'],
            'offboarding' => ['Revoke roles', 'Return laptop'],
        ]);
});

it('refuses an empty checklist and an over-long item', function () {
    $admin = actingPeopleAdmin(['hr.manage']);

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->put(route('hr.settings.update'), ['onboarding_items' => "\n \n", 'offboarding_items' => 'Revoke roles'])
        ->assertSessionHasErrors('onboarding_items');
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->put(route('hr.settings.update'), ['onboarding_items' => 'Contract', 'offboarding_items' => str_repeat('x', 121)])
        ->assertSessionHasErrors('offboarding_items');
});

it('saves the payroll rules and switch for the approver only, and checks every bound', function () {
    $runner = actingPeopleAdmin(['hr.manage', 'payroll.run']);
    $good = [
        'enabled' => true,
        'employee_pension_rate' => 0.08,
        'employer_pension_rate' => 0.07,
        'working_days' => 20,
        'tax_brackets' => [
            ['up_to' => 60000, 'rate' => 0],
            ['up_to' => 100000, 'rate' => 0.08],
            ['up_to' => '', 'rate' => 0.15],
        ],
    ];

    // The runner cannot move the rules — maker-checker.
    $this->withoutLocalizationMiddleware()->actingAs($runner)
        ->put(route('hr.settings.payroll'), $good)
        ->assertForbidden();

    $approver = actingPeopleAdmin(['hr.manage', 'payroll.approve']);
    $this->withoutLocalizationMiddleware()->actingAs($approver)
        ->put(route('hr.settings.payroll'), $good)
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $resolved = app(ResolvePayrollSettingsAction::class)->execute();
    expect($resolved['rules']['employee_pension_rate'])->toBe(0.08)
        ->and($resolved['rules']['working_days'])->toBe(20)
        ->and($resolved['rules']['rounding'])->toBe('half_up_2')
        ->and($resolved['rules']['tax_brackets'])->toEqual([
            ['up_to' => 60000.0, 'rate' => 0.0],
            ['up_to' => 100000.0, 'rate' => 0.08],
            ['up_to' => null, 'rate' => 0.15],
        ])
        // The settings half is on; the environment half is not, so payroll stays off.
        ->and($resolved['enabled'])->toBeFalse();

    config()->set('payroll.enabled', true);
    expect(app(ResolvePayrollSettingsAction::class)->execute()['enabled'])->toBeTrue();

    foreach ([
        ['employee_pension_rate' => 0.6],
        ['working_days' => 0],
        ['tax_brackets' => []],
        ['tax_brackets' => [['up_to' => 100000, 'rate' => 0], ['up_to' => 60000, 'rate' => 0.1], ['up_to' => '', 'rate' => 0.2]]],
        ['tax_brackets' => [['up_to' => 60000, 'rate' => 0], ['up_to' => 100000, 'rate' => 0.1]]],
        ['tax_brackets' => [['up_to' => '', 'rate' => 1.5]]],
    ] as $bad) {
        $this->withoutLocalizationMiddleware()->actingAs($approver)
            ->put(route('hr.settings.payroll'), array_merge($good, $bad))
            ->assertSessionHasErrors(array_key_first($bad));
    }

    // Nothing bad got through.
    expect(app(ResolvePayrollSettingsAction::class)->execute()['rules']['working_days'])->toBe(20);
});

it('keeps the screen away from an account without hr.manage', function () {
    $this->withoutLocalizationMiddleware()
        ->actingAs(User::factory()->create())
        ->get(route('hr.settings.index'))
        ->assertForbidden();
});
