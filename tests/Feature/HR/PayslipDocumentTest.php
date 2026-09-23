<?php

use App\Domains\HR\Actions\ApprovePayrollPeriodAction;
use App\Domains\HR\Actions\MarkPayrollPaidAction;
use App\Domains\HR\Actions\RunPayrollAction;
use App\Domains\HR\Actions\SaveStaffContractAction;
use App\Domains\HR\Enums\StaffContractType;
use App\Domains\HR\Models\Payslip;
use App\Domains\Media\Enums\DocumentType;
use App\Domains\Media\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * S5.6: *"payslip PDF (trilingual)"*. Until this, no `documents/payslip`
 * view existed, so every payslip fell through to the renderer's generic
 * key/value fallback — `lang="en"`, column names as labels, no staff name —
 * and was stored as a `receipt`, the nearest type the enum had (S5 audit
 * D2, STATUS §5fe).
 */
it('renders the payslip in the request locale, named, and stores it as a payslip', function () {
    Storage::fake('local');
    config()->set('payroll.enabled', true);
    DB::table('settings')->where('key', 'payroll.enabled')->update(['value' => '1']);
    makeYear(['is_current' => true, 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $staff = makeStaffProfile(['first_name' => 'Aminath', 'last_name' => 'Rasheed']);
    app(SaveStaffContractAction::class)->execute([
        'staff_profile_id' => $staff->id,
        'contract_type' => StaffContractType::Permanent->value,
        'start_date' => '2026-01-01',
        'basic_salary' => 10000,
    ]);

    $runner = actingPeopleAdmin(['payroll.run']);
    $period = app(RunPayrollAction::class)->execute(2026, 9, $runner->id);
    app(ApprovePayrollPeriodAction::class)->execute($period->id, actingPeopleAdmin(['payroll.approve'])->id);

    app()->setLocale('dv');
    app(MarkPayrollPaidAction::class)->execute($period->id);

    $payslip = Payslip::query()->sole();
    $document = Document::query()->findOrFail($payslip->document_id);
    $html = Storage::disk('local')->get($document->media_path);

    expect($document->document_type)->toBe(DocumentType::Payslip)
        ->and($document->title)->toBe('Payslip 2026-09')
        ->and($html)->toContain('<html lang="dv" dir="rtl">')
        ->and($html)->toContain(__('documents.payslip.heading', [], 'dv'))
        ->and($html)->toContain('Aminath Rasheed')
        ->and($html)->toContain('2026-09')
        ->and($html)->toContain(number_format((float) $payslip->net_pay, 2, '.', ''))
        ->and($html)->not->toContain('net_pay');
});
