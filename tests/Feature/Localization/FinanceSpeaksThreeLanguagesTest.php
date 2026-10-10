<?php

use App\Domains\Finance\Enums\FeeAdjustmentAppliesTo;
use App\Domains\Finance\Enums\FeeAdjustmentBasis;
use App\Domains\Finance\Enums\FeeAdjustmentStatus;
use App\Domains\Finance\Enums\FeeAdjustmentType;
use App\Domains\Finance\Enums\FeeFrequency;
use App\Domains\Finance\Enums\FeeItemType;
use App\Domains\Finance\Enums\FeeStructureAppliesTo;
use App\Domains\Finance\Enums\FeeStructureStatus;
use App\Domains\Finance\Enums\InvoiceStatus;
use App\Domains\Finance\Enums\PaymentPlanStatus;
use App\Domains\Finance\Models\FeeAdjustment;
use App\Domains\Finance\Models\FeeStructure;
use App\Domains\Finance\Models\PaymentPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The school office's finance screens in Dhivehi and Arabic (BACKLOG C21,
 * slice FN1, STATUS §5qn).
 *
 * The fee items, the fee structures, the invoices, the fee adjustments, the
 * payment plans and the finance settings read no phrase book. Every word on
 * them was English; a fee's kind and how often it falls due, a structure's
 * reach and state, an invoice's, a plan's and an adjustment's state and an
 * adjustment's kind, basis and reach were printed as codes (*one_time*,
 * *sibling_discount*); a year's period printed *year-2026*; a fee item read
 * by its English name though the school names it in three; the structures
 * list printed its classes' ids; and so was everything the server said —
 * ten saved messages and thirty-three refusals.
 *
 * The defects that went with them: an adjustment for some kinds of fee could
 * not say which, so it came off nothing; a structure had room for one fee,
 * and choosing a line's fee kept the first fee's amount; the payment plan
 * form proposed installments due in February and March 2026, and left the
 * second's amount empty, so the plan it proposed was refused with nothing
 * on the page; and a refused copy, issue, run or plan was said nowhere.
 */
uses(RefreshDatabase::class);

/** The finance screens in three languages; every phrase on them is `t.key || 'English'`, from the `finance` book. */
function financeScreens(): array
{
    return [
        'Finance/FeeItems/Index', 'Finance/FeeStructures/Index', 'Finance/Invoices/Index',
        'Finance/Adjustments/Index', 'Finance/PaymentPlans/Index', 'Finance/Settings/Index',
    ];
}

/** Where the server writes what those screens say. */
function financeServerFiles(): array
{
    return [
        'app/Domains/Finance/Http/Controllers/FeeItemController.php',
        'app/Domains/Finance/Http/Controllers/FeeStructureController.php',
        'app/Domains/Finance/Http/Controllers/InvoiceController.php',
        'app/Domains/Finance/Http/Controllers/FeeAdjustmentController.php',
        'app/Domains/Finance/Http/Controllers/PaymentPlanController.php',
        'app/Domains/Finance/Http/Controllers/FinanceSettingsController.php',
        'app/Domains/Finance/Actions/SaveFeeItemAction.php',
        'app/Domains/Finance/Actions/SaveFeeStructureAction.php',
        'app/Domains/Finance/Actions/CopyFeeStructuresFromLastYearAction.php',
        'app/Domains/Finance/Actions/GenerateInvoicesAction.php',
        'app/Domains/Finance/Actions/SaveFeeAdjustmentAction.php',
        'app/Domains/Finance/Actions/SuggestSiblingFeeAdjustmentsAction.php',
        'app/Domains/Finance/Actions/CreatePaymentPlanAction.php',
        'app/Domains/Finance/Actions/SaveFinanceSettingsAction.php',
    ];
}

function financeBook(string $locale): array
{
    return require base_path("resources/lang/{$locale}/finance.php");
}

it('keys every string on the finance screens in three languages', function () {
    [$en, $dv, $ar] = [financeBook('en'), financeBook('dv'), financeBook('ar')];

    foreach (financeScreens() as $screen) {
        $source = file_get_contents(resource_path("js/Pages/{$screen}.jsx"));
        preg_match_all("/(?<![\\w\$.])t\\.([a-z][a-z0-9_]+) \\|\\| '((?:[^'\\\\]|\\\\.)*)'/", $source, $uses, PREG_SET_ORDER);
        expect($uses)->not->toBeEmpty("{$screen} uses no phrases");

        foreach ($uses as [, $key, $fallback]) {
            expect(array_key_exists($key, $en))->toBeTrue("{$screen}: finance.{$key} is missing in English")
                ->and(array_key_exists($key, $dv))->toBeTrue("{$screen}: finance.{$key} is missing in Dhivehi")
                ->and(array_key_exists($key, $ar))->toBeTrue("{$screen}: finance.{$key} is missing in Arabic")
                ->and($en[$key])->toBe(stripslashes($fallback), "{$screen}: finance.{$key} says something else in English than the screen")
                ->and($dv[$key])->not->toBe($en[$key], "finance.{$key} is English in Dhivehi")
                ->and($ar[$key])->not->toBe($en[$key], "finance.{$key} is English in Arabic");
        }

        // No bare English: a text node, a written-out placeholder, label,
        // title or phone caption (`data-label`), and no field without a name.
        expect(preg_match_all('/>\s*[A-Z][A-Za-z]+[^<>{}]*</', $source, $text))->toBe(0, "{$screen} has English text nodes: ".implode(' | ', $text[0] ?? []))
            ->and(preg_match_all('/(placeholder|aria-label|title|data-label)="[A-Za-z][^"]*"/', $source, $attrs))->toBe(0, "{$screen} has English attributes: ".implode(' | ', $attrs[0] ?? []))
            ->and(unnamedFields($source))->toBe([], "{$screen} has fields with no name")
            ->and(routerVisitsWithoutRow("resources/js/Pages/{$screen}.jsx"))->toBe([], "{$screen} posts with nowhere to say a refusal");
    }
});

it('names every field the finance screens post, so a refusal by Laravel’s own rules reads whole in Dhivehi and Arabic', function () {
    $dhivehi = (require resource_path('lang/dv/validation.php'))['attributes'];
    $arabic = (require resource_path('lang/ar/validation.php'))['attributes'];

    $fields = [];
    foreach (array_filter(financeServerFiles(), fn (string $file) => str_contains($file, '/Controllers/')) as $file) {
        preg_match_all("/'([a-z_]+(?:\\.\\*(?:\\.[a-z_]+)?)?)' => \\[(?=[^\\]]*'(?:required|nullable|sometimes|integer|string|array|boolean|date|numeric)')/", file_get_contents(base_path($file)), $found);
        $fields = [...$fields, ...$found[1]];
    }
    expect($fields)->toContain('default_amount', 'items.*.amount', 'period_end', 'item_types', 'installments.*.due_date');

    foreach (array_unique($fields) as $field) {
        expect(array_key_exists($field, $dhivehi))->toBeTrue("{$field} has no Dhivehi name")
            ->and(array_key_exists($field, $arabic))->toBeTrue("{$field} has no Arabic name");
    }
});

it('names every code the finance screens show, in all three languages', function () {
    $codes = [
        ...array_map(fn ($case) => 'fee_type_'.$case->value, FeeItemType::cases()),
        ...array_map(fn ($case) => 'frequency_'.$case->value, FeeFrequency::cases()),
        ...array_map(fn ($case) => 'structure_status_'.$case->value, FeeStructureStatus::cases()),
        ...array_map(fn ($case) => 'applies_'.$case->value, FeeStructureAppliesTo::cases()),
        ...array_map(fn ($case) => 'invoice_status_'.$case->value, InvoiceStatus::cases()),
        ...array_map(fn ($case) => 'plan_status_'.$case->value, PaymentPlanStatus::cases()),
        ...array_map(fn ($case) => 'adjustment_type_'.$case->value, FeeAdjustmentType::cases()),
        ...array_map(fn ($case) => 'basis_'.$case->value, FeeAdjustmentBasis::cases()),
        ...array_map(fn ($case) => 'adjustment_applies_'.$case->value, FeeAdjustmentAppliesTo::cases()),
        ...array_map(fn ($case) => 'adjustment_status_'.$case->value, FeeAdjustmentStatus::cases()),
    ];

    foreach ($codes as $key) {
        expect(trans("finance.{$key}", [], 'en'))->not->toBe("finance.{$key}", "finance.{$key} has no English")
            ->and(trans("finance.{$key}", [], 'dv'))->toMatch('/\p{Thaana}/u', "finance.{$key} in Dhivehi")
            ->and(trans("finance.{$key}", [], 'ar'))->toMatch('/\p{Arabic}/u', "finance.{$key} in Arabic");
    }
});

it('leaves no English in what the server says on the finance screens, and says it in Dhivehi and Arabic', function () {
    $english = [];
    foreach (financeServerFiles() as $file) {
        $english = [...$english, ...refusalEnglishIn($file)];
    }
    expect($english)->toBe([]);

    $keys = refusalKeysIn(financeServerFiles());
    expect($keys)->toContain('finance.flash_invoices_generated', 'finance.flash_invoices_issued', 'finance.flash_structures_copied',
        'finance.error_period_order', 'finance.error_adjustment_item_types', 'finance.error_installments_sum', 'finance.suggest_sibling_reason', 'finance.error_reminder_days');
    foreach ($keys as $key) {
        expect(trans($key, [], 'en'))->not->toBe($key, "{$key} has no English")
            ->and(trans($key, [], 'dv'))->toMatch('/\p{Thaana}/u', "{$key} in Dhivehi")
            ->and(trans($key, [], 'ar'))->toMatch('/\p{Arabic}/u', "{$key} in Arabic");
    }

    // The drafts generated, the invoices issued and the structures copied are
    // counted: none, one, two and many in Arabic — and none says why.
    expect(trans_choice('finance.flash_invoices_generated', 1, ['count' => 1], 'ar'))->not->toBe(trans_choice('finance.flash_invoices_generated', 2, ['count' => 2], 'ar'))
        ->and(trans_choice('finance.flash_invoices_generated', 2, ['count' => 2], 'ar'))->not->toBe(trans_choice('finance.flash_invoices_generated', 5, ['count' => 5], 'ar'))
        ->and(trans_choice('finance.flash_invoices_generated', 1, ['count' => 1], 'en'))->toBe('1 draft invoice generated.')
        ->and(trans_choice('finance.flash_invoices_generated', 0, ['count' => 0], 'en'))->toStartWith('No draft invoices were generated: ')
        ->and(trans_choice('finance.flash_invoices_issued', 3, ['count' => 3], 'en'))->toBe('3 invoices issued.')
        ->and(trans_choice('finance.flash_structures_copied', 1, ['count' => 1], 'en'))->toBe('1 structure copied from last year as a draft.');
});

it('serves the finance screens in Dhivehi, and says what was saved and refused in Dhivehi', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $class = makeClass($year);
    $office = actingPeopleAdmin(['finance.manage']);
    $dv = financeBook('dv');

    app()->setLocale('dv');
    foreach ([
        ['finance.fee-items.index', 'Finance/FeeItems/Index', 'fee_items_title'],
        ['finance.fee-structures.index', 'Finance/FeeStructures/Index', 'structures_title'],
        ['finance.invoices.index', 'Finance/Invoices/Index', 'invoices_title'],
        ['finance.adjustments.index', 'Finance/Adjustments/Index', 'adjustments_title'],
        ['finance.payment-plans.index', 'Finance/PaymentPlans/Index', 'plans_title'],
        ['finance.settings.index', 'Finance/Settings/Index', 'settings_title'],
    ] as [$route, $component, $key]) {
        $this->withoutLocalizationMiddleware()->actingAs($office)
            ->get(route($route))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component)->where("t.{$key}", $dv[$key]));
    }

    // A fee item is saved, said in Dhivehi; an amount that is no number is
    // refused, the field named in Dhivehi.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('finance.fee-items.store'), ['name' => 'Transport', 'name_dhivehi' => 'ދަތުރުފަތުރު', 'default_amount' => 'lots', 'type' => 'transport', 'frequency' => 'monthly'])
        ->assertSessionHasErrors('default_amount');
    expect(session('errors')->first('default_amount'))->toMatch('/\p{Thaana}/u')->not->toMatch('/[A-Za-z]/');
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('finance.fee-items.store'), ['name' => 'Transport', 'name_dhivehi' => 'ދަތުރުފަތުރު', 'default_amount' => 200, 'type' => 'transport', 'frequency' => 'monthly'])
        ->assertSessionHas('success', $dv['flash_fee_item_saved']);

    // A structure's fee items read by their Dhivehi names; one for selected
    // classes with none chosen is refused in Dhivehi.
    $transport = makeCatalogFeeItem(['name' => 'Bus', 'name_dhivehi' => 'ބަސް', 'type' => 'transport', 'default_amount' => 100]);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('finance.fee-structures.store'), ['academic_year_id' => $year->id, 'name' => 'Grade 1 fees', 'applies_to' => 'class', 'class_ids' => [], 'status' => 'draft', 'items' => [['fee_item_id' => $transport->id, 'amount' => 100, 'frequency' => 'monthly']]])
        ->assertSessionHasErrors(['class_ids' => $dv['error_select_class']]);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('finance.fee-structures.store'), ['academic_year_id' => $year->id, 'name' => 'Grade 1 fees', 'applies_to' => 'class', 'class_ids' => [$class->id], 'status' => 'active', 'items' => [['fee_item_id' => $transport->id, 'amount' => 100, 'frequency' => 'monthly']]])
        ->assertSessionHas('success', $dv['flash_structure_saved']);
    $structure = FeeStructure::query()->where('name', 'Grade 1 fees')->sole();
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->get(route('finance.fee-structures.index'))
        ->assertInertia(fn (Assert $page) => $page->where('structures.0.items.0.name_dhivehi', 'ބަސް'));

    // Last year is copied from nothing: refused in Dhivehi, said beside the button.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('finance.fee-structures.copy-last-year'), ['academic_year_id' => $year->id])
        ->assertSessionHasErrors(['academic_year_id' => $dv['error_no_previous_year']]);

    // A period that ends before it starts is refused in Dhivehi; a class with
    // nobody in it bills nobody, and says why.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('finance.invoices.generate'), ['academic_year_id' => $year->id, 'fee_structure_id' => $structure->id, 'period_start' => '2026-03-31', 'period_end' => '2026-03-01'])
        ->assertSessionHasErrors(['period_end' => $dv['error_period_order']]);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('finance.invoices.generate'), ['academic_year_id' => $year->id, 'fee_structure_id' => $structure->id, 'period_start' => '2026-03-01', 'period_end' => '2026-03-31'])
        ->assertSessionHas('success', trans_choice('finance.flash_invoices_generated', 0, ['count' => 0], 'dv'));

    // An adjustment for some kinds of fee is refused without them — it came
    // off nothing — and kept with them.
    $student = makeStudent();
    $adjustment = ['student_id' => $student->id, 'academic_year_id' => $year->id, 'type' => 'scholarship', 'basis' => 'percent', 'value' => 50, 'applies_to' => 'item_types', 'status' => 'approved'];
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('finance.adjustments.store'), $adjustment)
        ->assertSessionHasErrors(['item_types' => $dv['error_adjustment_item_types']]);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('finance.adjustments.store'), [...$adjustment, 'item_types' => ['transport', 'not-a-kind']])
        ->assertSessionHas('success', $dv['flash_adjustment_saved']);
    expect(FeeAdjustment::query()->sole()->item_types)->toBe(['transport']);

    // A plan that does not add up to the balance is refused in Dhivehi, the
    // balance in the sentence; the open invoice says whose it is.
    $invoice = makeSchoolInvoice($office->id, $student->id, $year->id, 900);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->get(route('finance.payment-plans.index'))
        ->assertInertia(fn (Assert $page) => $page->where('openInvoices.0.student_name', fn ($name) => filled($name)));
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('finance.payment-plans.store'), ['invoice_id' => $invoice->id, 'installments' => [['amount' => 400, 'due_date' => '2026-11-01'], ['amount' => 400, 'due_date' => '2026-12-01']]])
        ->assertSessionHasErrors(['installments' => __('finance.error_installments_sum', ['balance' => '900.00'], 'dv')]);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('finance.payment-plans.store'), ['invoice_id' => $invoice->id, 'installments' => [['amount' => 450, 'due_date' => '2026-11-01'], ['amount' => 450, 'due_date' => '2026-12-01']]])
        ->assertSessionHas('success', $dv['flash_plan_created']);
    expect(PaymentPlan::query()->count())->toBe(1);

    // A setting out of its bounds is refused in Dhivehi.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->put(route('finance.settings.update'), ['invoice_monthly_mode' => 'per_month', 'invoice_reminder_days' => 200, 'plan_default_days' => 14])
        ->assertSessionHasErrors(['invoice_reminder_days' => $dv['error_reminder_days']]);
});
