<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Finance\Actions\GenerateInvoicesAction;
use App\Domains\Finance\Actions\IssueInvoicesAction;
use App\Domains\Finance\Actions\ListArrearsAction;
use App\Domains\Finance\Actions\MarkOverdueInvoicesAction;
use App\Domains\Finance\Actions\SaveFeeStructureAction;
use App\Domains\Finance\Actions\SendInvoiceRemindersAction;
use App\Domains\Finance\Enums\FeeFrequency;
use App\Domains\Finance\Enums\FeeItemType;
use App\Domains\Finance\Enums\FeeStructureAppliesTo;
use App\Domains\Finance\Enums\FeeStructureStatus;
use App\Domains\Finance\Enums\InvoiceStatus;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\InvoiceGenerationLog;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\People\Actions\AttachGuardianAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function fakeFinanceSms(): object
{
    $fake = new class implements SmsSenderInterface
    {
        /** @var list<array{phone: string, message: string}> */
        public array $sent = [];

        public function sendSms(string $phoneNumber, string $message, array $options = []): array
        {
            $this->sent[] = ['phone' => $phoneNumber, 'message' => $message];

            return ['success' => true];
        }
    };
    app()->instance(SmsSenderInterface::class, $fake);

    return $fake;
}

it('generates invoices idempotently, expands monthly items, and notifies the financially responsible guardian', function () {
    expect(Schema::hasTable('invoice_generation_logs'))->toBeTrue();

    $sms = fakeFinanceSms();
    $admin = actingPeopleAdmin(['finance.manage']);
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $term = makeTerm($year);
    $class = makeClass($year);
    $tuition = makeCatalogFeeItem();
    $trip = makeCatalogFeeItem([
        'name' => 'Trip',
        'default_amount' => 200,
        'type' => FeeItemType::Activity->value,
        'frequency' => FeeFrequency::Monthly->value,
        'is_mandatory' => false,
    ]);

    $structure = app(SaveFeeStructureAction::class)->execute([
        'academic_year_id' => $year->id,
        'name' => 'Primary fees',
        'applies_to' => FeeStructureAppliesTo::SelectedClasses->value,
        'class_ids' => [$class->id],
        'status' => FeeStructureStatus::Active->value,
        'items' => [
            [
                'fee_item_id' => $tuition->id,
                'amount' => 1500,
                'frequency' => FeeFrequency::Monthly->value,
                'due_day' => 10,
                'is_mandatory' => true,
            ],
            [
                'fee_item_id' => $trip->id,
                'amount' => 200,
                'frequency' => FeeFrequency::Monthly->value,
                'is_mandatory' => false,
            ],
        ],
    ]);

    $studentA = makeStudent(['first_name' => 'Aisha']);
    $studentB = makeStudent(['first_name' => 'Yusuf', 'last_name' => 'Manik']);
    app(AssignStudentToClassAction::class)->execute($class, $studentA->id);
    app(AssignStudentToClassAction::class)->execute($class, $studentB->id);

    $payer = makeGuardian();
    $other = makeGuardian();
    $other->update(['phone' => '7972434']);
    app(AttachGuardianAction::class)->execute($studentA, $payer, 'father', true, true, true);
    app(AttachGuardianAction::class)->execute($studentA, $other, 'mother', false, true, false);

    $perMonth = app(GenerateInvoicesAction::class)->execute([
        'fee_structure_id' => $structure->id,
        'class_id' => $class->id,
        'period_start' => '2026-01-01',
        'period_end' => '2026-03-31',
        'term_id' => $term->id,
        'monthly_mode' => 'per_month',
        'created_by' => $admin->id,
    ]);

    expect($perMonth)->toHaveCount(6)
        ->and($perMonth->every(fn ($invoice) => $invoice->lines->count() === 1))->toBeTrue()
        ->and((float) $perMonth->first()->total_amount)->toBe(1500.0);

    $again = app(GenerateInvoicesAction::class)->execute([
        'fee_structure_id' => $structure->id,
        'class_id' => $class->id,
        'period_start' => '2026-01-01',
        'period_end' => '2026-03-31',
        'term_id' => $term->id,
        'monthly_mode' => 'per_month',
        'created_by' => $admin->id,
    ]);
    expect($again)->toHaveCount(0)
        ->and(InvoiceGenerationLog::query()->count())->toBe(6);

    $studentC = makeStudent(['first_name' => 'Mariyam']);
    app(AssignStudentToClassAction::class)->execute($class, $studentC->id);

    $consolidated = app(GenerateInvoicesAction::class)->execute([
        'fee_structure_id' => $structure->id,
        'class_id' => $class->id,
        'period_start' => '2026-01-01',
        'period_end' => '2026-03-31',
        'term_id' => $term->id,
        'period_key' => 'term-'.$term->id,
        'monthly_mode' => 'consolidated',
        'include_optional' => true,
        'created_by' => $admin->id,
    ]);

    $cInvoice = $consolidated->firstWhere('student_id', $studentC->id);
    expect($consolidated)->toHaveCount(3)
        ->and($cInvoice)->not->toBeNull()
        ->and($cInvoice->lines)->toHaveCount(2)
        ->and((float) $cInvoice->total_amount)->toBe(5100.0);

    $issued = app(IssueInvoicesAction::class)->execute($perMonth->where('student_id', $studentA->id)->pluck('id')->all());
    expect($issued->every(fn ($invoice) => $invoice->status === InvoiceStatus::Sent))->toBeTrue()
        ->and($sms->sent)->toHaveCount(3)
        ->and(collect($sms->sent)->every(fn ($row) => $row['phone'] === '7820288'))->toBeTrue();

    $late = $issued->first();
    $late->due_date = '2025-12-01';
    $late->save();

    expect(app(MarkOverdueInvoicesAction::class)->execute('2026-01-05'))->toBe(1)
        ->and($late->fresh()->status)->toBe(InvoiceStatus::Overdue);

    $arrears = app(ListArrearsAction::class)->execute($year->id, '2026-02-01');
    expect($arrears->firstWhere('id', $late->id)['aging_bucket'])->toBe('60')
        ->and($arrears->firstWhere('id', $late->id)['guardian_name'])->toContain('Hassan');

    expect(app(SendInvoiceRemindersAction::class)->execute('2026-01-10'))->toBe(1)
        ->and(app(SendInvoiceRemindersAction::class)->execute('2026-01-10'))->toBe(0)
        ->and(count($sms->sent))->toBe(4);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('finance.invoices.index', ['academic_year_id' => $year->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Finance/Invoices/Index')
            ->where('period_start', $term->start_date->toDateString())
            ->where('period_end', $term->end_date->toDateString())
            ->has('invoices', 9)
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('finance.arrears.export', ['academic_year_id' => $year->id]))
        ->assertOk()
        ->streamedContent();
    expect($csv)->toContain($late->invoice_number);

    expect(Invoice::query()->count())->toBe(9);
});
