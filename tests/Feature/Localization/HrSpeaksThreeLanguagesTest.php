<?php

use App\Domains\HR\Enums\LeaveTypeCode;
use App\Domains\HR\Enums\PayslipStatus;
use App\Domains\HR\Enums\StaffAttendanceSource;
use App\Domains\HR\Enums\StaffAttendanceStatus;
use App\Domains\HR\Enums\StaffContractStatus;
use App\Domains\HR\Enums\StaffContractType;
use App\Domains\HR\Models\LeaveType;
use App\Domains\HR\Models\StaffAttendance;
use App\Domains\Media\Enums\DocumentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The school office's HR screens in Dhivehi and Arabic (BACKLOG C21, slice
 * HR1, STATUS §5ql).
 *
 * The leave types, the expiring documents, the contracts, staff attendance
 * and its reports, the leave balances, payroll and the HR settings read no
 * phrase book. Every word on them was English; a leave type's code, a
 * contract's type and state, a day's attendance and how it was recorded, a
 * document's type and a payslip's state were printed as codes (*ON_LEAVE*,
 * *fixed_term*); a leave type read by its English name though the school has
 * a place for three; and so was everything the server said — sixteen saved
 * messages and twenty-five refusals, among them a row of an import refused
 * by its number (*Unknown staff on row 3.*).
 *
 * Four defects went with them. Saving a leave type could only fail: the
 * form only ever made a new one, every code is seeded, and the code is
 * unique — a 500. A day of attendance with no academic year to put it in
 * opened a bare 422 page; it is refused under the form. A refused import
 * kept the rows above the one it refused. And an import with no remarks
 * column was a 500.
 */
uses(RefreshDatabase::class);

/** The HR screens in three languages; every phrase on them is `t.key || 'English'`, from the `hr` book. */
function hrScreens(): array
{
    return [
        'HR/Leave/Types', 'HR/Compliance/Index', 'HR/Contracts/Index', 'HR/Attendance/Reports',
        'HR/Attendance/Index', 'HR/Leave/Balances', 'HR/Payroll/Index', 'HR/Settings/Index',
    ];
}

/** Where the server writes what those screens say. */
function hrServerFiles(): array
{
    return [
        'app/Domains/HR/Http/Controllers/LeaveTypeController.php',
        'app/Domains/HR/Http/Controllers/ComplianceController.php',
        'app/Domains/HR/Http/Controllers/StaffContractController.php',
        'app/Domains/HR/Http/Controllers/StaffAttendanceReportController.php',
        'app/Domains/HR/Http/Controllers/StaffAttendanceController.php',
        'app/Domains/HR/Http/Controllers/LeaveBalanceController.php',
        'app/Domains/HR/Http/Controllers/PayrollPeriodController.php',
        'app/Domains/HR/Http/Controllers/HrSettingsController.php',
        'app/Domains/HR/Actions/SaveLeaveTypeAction.php',
        'app/Domains/HR/Actions/SaveStaffContractAction.php',
        'app/Domains/HR/Actions/ImportStaffAttendanceCsvAction.php',
        'app/Domains/HR/Actions/RecordStaffAttendanceAction.php',
        'app/Domains/HR/Actions/AdjustLeaveBalanceAction.php',
        'app/Domains/HR/Actions/ResolvePayrollSettingsAction.php',
        'app/Domains/HR/Actions/ApprovePayrollPeriodAction.php',
        'app/Domains/HR/Actions/LockPayrollPeriodAction.php',
        'app/Domains/HR/Actions/MarkPayrollPaidAction.php',
        'app/Domains/HR/Actions/RunPayrollAction.php',
        'app/Domains/HR/Actions/SaveHrSettingsAction.php',
        'app/Domains/HR/Actions/SavePayrollSettingsAction.php',
    ];
}

function hrBook(string $locale): array
{
    return require base_path("resources/lang/{$locale}/hr.php");
}

it('keys every string on the HR screens in three languages', function () {
    [$en, $dv, $ar] = [hrBook('en'), hrBook('dv'), hrBook('ar')];

    foreach (hrScreens() as $screen) {
        $source = file_get_contents(resource_path("js/Pages/{$screen}.jsx"));
        preg_match_all("/(?<![\\w\$.])t\\.([a-z][a-z0-9_]+) \\|\\| '((?:[^'\\\\]|\\\\.)*)'/", $source, $uses, PREG_SET_ORDER);
        expect($uses)->not->toBeEmpty("{$screen} uses no phrases");

        foreach ($uses as [, $key, $fallback]) {
            expect(array_key_exists($key, $en))->toBeTrue("{$screen}: hr.{$key} is missing in English")
                ->and(array_key_exists($key, $dv))->toBeTrue("{$screen}: hr.{$key} is missing in Dhivehi")
                ->and(array_key_exists($key, $ar))->toBeTrue("{$screen}: hr.{$key} is missing in Arabic")
                ->and($en[$key])->toBe(stripslashes($fallback), "{$screen}: hr.{$key} says something else in English than the screen")
                ->and($dv[$key])->not->toBe($en[$key], "hr.{$key} is English in Dhivehi")
                ->and($ar[$key])->not->toBe($en[$key], "hr.{$key} is English in Arabic");
        }

        // No bare English: a text node, a written-out placeholder, label,
        // title or phone caption (`data-label`), and no field without a name.
        expect(preg_match_all('/>\s*[A-Z][A-Za-z]+[^<>{}]*</', $source, $text))->toBe(0, "{$screen} has English text nodes: ".implode(' | ', $text[0] ?? []))
            ->and(preg_match_all('/(placeholder|aria-label|title|data-label)="[A-Za-z][^"]*"/', $source, $attrs))->toBe(0, "{$screen} has English attributes: ".implode(' | ', $attrs[0] ?? []))
            ->and(unnamedFields($source))->toBe([], "{$screen} has fields with no name")
            ->and(routerVisitsWithoutRow("resources/js/Pages/{$screen}.jsx"))->toBe([], "{$screen} posts with nowhere to say a refusal");
    }
});

it('names every field the HR screens post, so a refusal by Laravel’s own rules reads whole in Dhivehi and Arabic', function () {
    $dhivehi = (require resource_path('lang/dv/validation.php'))['attributes'];
    $arabic = (require resource_path('lang/ar/validation.php'))['attributes'];

    $fields = [];
    foreach (array_filter(hrServerFiles(), fn (string $file) => str_contains($file, '/Controllers/')) as $file) {
        preg_match_all("/'([a-z_]+(?:\\.\\*(?:\\.[a-z_]+)?)?)' => \\[(?=[^\\]]*'(?:required|nullable|sometimes|integer|string|array|boolean|date|numeric)')/", file_get_contents(base_path($file)), $found);
        $fields = [...$fields, ...$found[1]];
    }
    expect($fields)->toContain('staff_profile_id', 'minutes_late', 'days_per_year', 'basic_salary', 'to_year_id');

    foreach (array_unique($fields) as $field) {
        expect(array_key_exists($field, $dhivehi))->toBeTrue("{$field} has no Dhivehi name")
            ->and(array_key_exists($field, $arabic))->toBeTrue("{$field} has no Arabic name");
    }
});

it('names every code the HR screens show, in all three languages', function () {
    $codes = [
        ...array_map(fn ($case) => 'leave_code_'.$case->value, LeaveTypeCode::cases()),
        ...array_map(fn ($case) => 'contract_type_'.$case->value, StaffContractType::cases()),
        ...array_map(fn ($case) => 'contract_status_'.$case->value, StaffContractStatus::cases()),
        ...array_map(fn ($case) => 'attendance_status_'.$case->value, StaffAttendanceStatus::cases()),
        ...array_map(fn ($case) => 'attendance_source_'.$case->value, StaffAttendanceSource::cases()),
        ...array_map(fn ($case) => 'payslip_status_'.$case->value, PayslipStatus::cases()),
        ...array_map(fn ($case) => 'document_type_'.$case->value, DocumentType::cases()),
    ];

    foreach ($codes as $key) {
        expect(trans("hr.{$key}", [], 'en'))->not->toBe("hr.{$key}", "hr.{$key} has no English")
            ->and(trans("hr.{$key}", [], 'dv'))->toMatch('/\p{Thaana}/u', "hr.{$key} in Dhivehi")
            ->and(trans("hr.{$key}", [], 'ar'))->toMatch('/\p{Arabic}/u', "hr.{$key} in Arabic");
    }
});

it('leaves no English in what the server says on the HR screens, and says it in Dhivehi and Arabic', function () {
    $english = [];
    foreach (hrServerFiles() as $file) {
        $english = [...$english, ...refusalEnglishIn($file)];
    }
    expect($english)->toBe([]);

    $keys = refusalKeysIn(hrServerFiles());
    expect($keys)->toContain('hr.flash_attendance_imported', 'hr.flash_notices_sent', 'hr.flash_entitlements_carried', 'hr.error_csv_unknown_staff', 'hr.error_no_year_for_date', 'hr.error_leave_code_taken', 'hr.error_brackets_rise', 'hr.error_payroll_disabled');
    foreach ($keys as $key) {
        expect(trans($key, [], 'en'))->not->toBe($key, "{$key} has no English")
            ->and(trans($key, [], 'dv'))->toMatch('/\p{Thaana}/u', "{$key} in Dhivehi")
            ->and(trans($key, [], 'ar'))->toMatch('/\p{Arabic}/u', "{$key} in Arabic");
    }

    // The rows imported, the notices sent and the entitlements carried are
    // counted: one, two and many in Arabic.
    expect(trans_choice('hr.flash_attendance_imported', 1, ['count' => 1], 'ar'))->not->toBe(trans_choice('hr.flash_attendance_imported', 2, ['count' => 2], 'ar'))
        ->and(trans_choice('hr.flash_attendance_imported', 2, ['count' => 2], 'ar'))->not->toBe(trans_choice('hr.flash_attendance_imported', 5, ['count' => 5], 'ar'))
        ->and(trans_choice('hr.flash_notices_sent', 1, ['count' => 1], 'en'))->toBe('1 expiry notice sent.')
        ->and(trans_choice('hr.flash_entitlements_carried', 3, ['count' => 3], 'en'))->toBe('3 entitlements carried over.');
});

it('gives the eight leave types a school starts with a Dhivehi and an Arabic name, and keeps one the office typed', function () {
    expect(LeaveType::query()->count())->toBe(count(LeaveTypeCode::cases()))
        ->and(LeaveType::query()->get(['code', 'name_dhivehi', 'name_arabic'])->every(
            fn (LeaveType $type) => preg_match('/\p{Thaana}/u', (string) $type->name_dhivehi) === 1 && preg_match('/\p{Arabic}/u', (string) $type->name_arabic) === 1,
        ))->toBeTrue();

    $sick = LeaveType::query()->where('code', LeaveTypeCode::Sick)->sole();
    $sick->update(['name_dhivehi' => 'ބަލިވުމުގެ ޗުއްޓީ']);
    (require base_path('database/migrations/2026_10_10_000002_leave_type_names_in_dhivehi_and_arabic.php'))->up();

    expect($sick->refresh()->name_dhivehi)->toBe('ބަލިވުމުގެ ޗުއްޓީ');
});

it('refuses a day of attendance under the form, in Dhivehi, when the school has no academic year — it was a bare 422 page', function () {
    $staff = makeStaffProfile();
    $office = actingPeopleAdmin(['hr.manage']);

    app()->setLocale('dv');
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('hr.attendance.store'), ['staff_profile_id' => $staff->id, 'date' => '2026-08-26', 'status' => 'present'])
        ->assertSessionHasErrors(['date' => hrBook('dv')['error_no_year_for_date']]);
    expect(StaffAttendance::query()->count())->toBe(0);
});

it('serves the HR screens in Dhivehi, and says what was saved and refused in Dhivehi', function () {
    makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $staff = makeStaffProfile(['staff_number' => 'STF-DV']);
    $office = actingPeopleAdmin(['hr.manage', 'payroll.run']);
    $dv = hrBook('dv');

    app()->setLocale('dv');
    foreach ([
        ['hr.leave-types.index', 'HR/Leave/Types', 'types_title'],
        ['hr.compliance.index', 'HR/Compliance/Index', 'compliance_title'],
        ['hr.contracts.index', 'HR/Contracts/Index', 'contracts_title'],
        ['hr.attendance.reports', 'HR/Attendance/Reports', 'reports_title'],
        ['hr.attendance.index', 'HR/Attendance/Index', 'attendance_title'],
        ['hr.leave-balances.index', 'HR/Leave/Balances', 'balances_title'],
        ['hr.payroll.index', 'HR/Payroll/Index', 'payroll_title'],
        ['hr.settings.index', 'HR/Settings/Index', 'settings_title'],
    ] as [$route, $component, $key]) {
        $this->withoutLocalizationMiddleware()->actingAs($office)
            ->get(route($route))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component)->where("t.{$key}", $dv[$key]));
    }

    // A leave type's code the school has is refused — it was a 500 — and the
    // type is changed in place, said in Dhivehi.
    $annual = LeaveType::query()->where('code', LeaveTypeCode::Annual)->sole();
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('hr.leave-types.store'), ['code' => 'annual', 'name' => 'Annual', 'days_per_year' => 20])
        ->assertSessionHasErrors(['code' => $dv['error_leave_code_taken']]);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->put(route('hr.leave-types.update', $annual), ['code' => 'annual', 'name' => 'Annual', 'name_dhivehi' => 'އަހަރީ ޗުއްޓީ', 'days_per_year' => 22, 'requires_document' => false, 'paid' => true, 'active' => true])
        ->assertSessionHas('success', $dv['flash_leave_type_updated']);
    expect((float) $annual->refresh()->days_per_year)->toBe(22.0)
        ->and(LeaveType::query()->count())->toBe(count(LeaveTypeCode::cases()));

    // An import's unknown row is refused by its number, in Dhivehi, and the
    // row above it is not kept — it was, though the file was refused. The
    // file has no remarks column, which was a 500.
    $upload = UploadedFile::fake()->createWithContent('attendance.csv', implode("\n", [
        'staff_number,date,status',
        'STF-DV,2026-08-26,present',
        'STF-NOBODY,2026-08-26,present',
    ]));
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('hr.attendance.import'), ['file' => $upload])
        ->assertSessionHasErrors(['file' => str_replace(':row', '3', $dv['error_csv_unknown_staff'])]);
    expect(StaffAttendance::query()->count())->toBe(0);

    // An HR checklist with no item is refused, in Dhivehi.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->put(route('hr.settings.update'), ['onboarding_items' => "\n", 'offboarding_items' => 'Return laptop'])
        ->assertSessionHasErrors(['onboarding_items' => $dv['error_checklist_empty']]);
});
