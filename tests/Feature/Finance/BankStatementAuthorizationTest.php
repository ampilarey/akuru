<?php

use App\Domains\Finance\Actions\ImportBankStatementAction;
use App\Domains\Finance\Enums\InvoiceStatus;
use App\Domains\Finance\Enums\InvoiceType;
use App\Domains\Finance\Models\BankStatementLine;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Receipt;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The split that matters on this screen: looking at what the bank sent is
 * bookkeeping, deciding the school has been paid is money. A bursar's clerk
 * may reasonably do the first without the second, so `finance.manage` opens the
 * screen and `finance.record-manual-payment` — the same permission the cashier
 * screen demands — gates confirmation.
 *
 * Asserted through HTTP rather than against the action, because the split lives
 * in the controller and an action-level test would pass regardless.
 */
uses(RefreshDatabase::class);

function bookkeeperWithout(string $missing): User
{
    $role = Role::findOrCreate('bookkeeper-'.md5($missing), 'web');
    foreach (['finance.manage', 'finance.record-manual-payment'] as $name) {
        $permission = Permission::findOrCreate($name, 'web');
        if ($name !== $missing) {
            $role->givePermissionTo($permission);
        }
    }

    // The route group is role-gated as well; `admin` carries it.
    Role::findOrCreate('admin', 'web');
    $user = User::factory()->create(['name' => 'Bookkeeper']);
    $user->assignRole('admin');
    $user->assignRole($role->name);

    return $user->fresh();
}

function seedOneSuggestedLine(User $actor): BankStatementLine
{
    $studentId = DB::table('students')->value('id')
        ?? makeStudent(['first_name' => 'Billed', 'last_name' => 'Pupil'])->id;

    Invoice::query()->create([
        'invoice_number' => 'INV-9001',
        'student_id' => $studentId,
        'invoice_type' => InvoiceType::SchoolFees->value,
        'issue_date' => now()->subDays(5)->toDateString(),
        'due_date' => now()->addDays(5)->toDateString(),
        'currency' => 'MVR',
        'status' => InvoiceStatus::Sent->value,
        'subtotal' => 600,
        'total_amount' => 600,
        'paid_amount' => 0,
        'created_by' => $actor->id,
    ]);

    app(ImportBankStatementAction::class)->execute(
        "date,description,reference,amount\n2026-09-10,TRF INV-9001,,600.00\n",
        'auth.csv',
        $actor->id,
    );

    return BankStatementLine::query()->firstOrFail();
}

it('lets someone with finance.manage read the screen', function () {
    $actor = bookkeeperWithout('finance.record-manual-payment');
    seedOneSuggestedLine($actor);

    $this->withoutLocalizationMiddleware()
        ->actingAs($actor)
        ->get('/finance/bank-statements')
        ->assertOk();
});

it('refuses confirmation to someone who may only read', function () {
    $actor = bookkeeperWithout('finance.record-manual-payment');
    $line = seedOneSuggestedLine($actor);

    $this->withoutLocalizationMiddleware()
        ->actingAs($actor)
        ->post("/finance/bank-statements/lines/{$line->id}/confirm")
        ->assertForbidden();

    // The point of the test: no receipt exists, so no invoice moved.
    expect(Receipt::query()->count())->toBe(0)
        ->and((float) Invoice::query()->firstOrFail()->paid_amount)->toBe(0.0);
});

it('allows confirmation once the money permission is held', function () {
    $actor = bookkeeperWithout('none-missing');
    $line = seedOneSuggestedLine($actor);

    $this->withoutLocalizationMiddleware()
        ->actingAs($actor)
        ->post("/finance/bank-statements/lines/{$line->id}/confirm")
        ->assertRedirect();

    expect(Receipt::query()->count())->toBe(1);
});

it('keeps the whole screen away from someone with neither permission', function () {
    $actor = bookkeeperWithout('finance.manage');

    $this->withoutLocalizationMiddleware()
        ->actingAs($actor)
        ->get('/finance/bank-statements')
        ->assertForbidden();
});
