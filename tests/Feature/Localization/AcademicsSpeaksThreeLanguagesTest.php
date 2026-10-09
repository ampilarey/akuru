<?php

use App\Domains\Academics\Enums\AbsenceNoteStatus;
use App\Domains\Academics\Enums\AttendanceSource;
use App\Domains\Academics\Enums\AttendanceStatus;
use App\Domains\Academics\Enums\LessonLogStatus;
use App\Domains\Academics\Models\AbsenceNote;
use App\Domains\Academics\Models\AbsenceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The school office's Academics screens in Dhivehi and Arabic (BACKLOG C21,
 * slice OA1, STATUS §5qd).
 *
 * PT1a–PT4 put the family's and the staff's own portal pages in three
 * languages; the office's 31 Academics screens read no phrase book. The
 * first a teacher and the office meet are the registers and attendance: a
 * class register with its grid, today's registers, the unfilled list, the
 * attendance reports, daily attendance, who is not in today, absence notes,
 * the attendance policy and the absence reasons. Every word on them was
 * English; a register's and a mark's state, how a mark was recorded and a
 * note's state were printed as codes; and so was everything the server said
 * — why today is empty, what generating registers did, eight saved messages
 * and twenty-one refusals. A note's reason, which the school names itself,
 * was its code on the office's list and English on the family's form: the
 * five reasons the school started with had no Dhivehi or Arabic name.
 */
uses(RefreshDatabase::class);

/** The registers and attendance screens; every phrase on them is `t.key || 'English'`, from the `academics` book. */
function academicsScreens(): array
{
    return [
        'Academics/Registers/Show', 'Academics/Registers/Today', 'Academics/Registers/Unfilled',
        'Academics/Attendance/Index', 'Academics/Attendance/Daily', 'Academics/Attendance/AbsencesToday',
        'Academics/AbsenceNotes/Index', 'Academics/AttendancePolicy/Index', 'Academics/AbsenceTypes/Index',
    ];
}

/** Where the server writes what those screens say. */
function academicsServerFiles(): array
{
    return [
        'app/Domains/Academics/Http/Controllers/TeacherRegisterController.php',
        'app/Domains/Academics/Http/Controllers/RegisterReportController.php',
        'app/Domains/Academics/Http/Controllers/AttendanceReportController.php',
        'app/Domains/Academics/Http/Controllers/DailyAttendanceController.php',
        'app/Domains/Academics/Http/Controllers/AbsencesTodayController.php',
        'app/Domains/Academics/Http/Controllers/AbsenceNoteReviewController.php',
        'app/Domains/Academics/Http/Controllers/AttendancePolicyController.php',
        'app/Domains/Academics/Http/Controllers/AbsenceTypeController.php',
        'app/Domains/Academics/Actions/ExplainEmptyTodayRegistersAction.php',
        'app/Domains/Academics/Actions/GenerateExpectedRegistersAction.php',
        'app/Domains/Academics/Actions/SubmitRegisterAction.php',
        'app/Domains/Academics/Actions/RecordRegisterAttendanceAction.php',
        'app/Domains/Academics/Actions/UnlockRegisterAction.php',
        'app/Domains/Academics/Actions/RecordDailyAttendanceAction.php',
        'app/Domains/Academics/Actions/ApproveAbsenceNoteAction.php',
        'app/Domains/Academics/Actions/RejectAbsenceNoteAction.php',
        'app/Domains/Academics/Actions/SaveAbsenceTypeAction.php',
        'app/Domains/Academics/Actions/SaveAttendanceSettingsAction.php',
    ];
}

function academicsBook(string $locale): array
{
    return require base_path("resources/lang/{$locale}/academics.php");
}

it('keys every string on the registers and attendance screens in three languages', function () {
    [$en, $dv, $ar] = [academicsBook('en'), academicsBook('dv'), academicsBook('ar')];

    foreach (academicsScreens() as $screen) {
        $source = file_get_contents(resource_path("js/Pages/{$screen}.jsx"));
        preg_match_all("/(?<![\\w\$.])t\\.([a-z][a-z0-9_]+) \\|\\| '((?:[^'\\\\]|\\\\.)*)'/", $source, $uses, PREG_SET_ORDER);
        expect($uses)->not->toBeEmpty("{$screen} uses no phrases");

        foreach ($uses as [, $key, $fallback]) {
            expect(array_key_exists($key, $en))->toBeTrue("{$screen}: academics.{$key} is missing in English")
                ->and(array_key_exists($key, $dv))->toBeTrue("{$screen}: academics.{$key} is missing in Dhivehi")
                ->and(array_key_exists($key, $ar))->toBeTrue("{$screen}: academics.{$key} is missing in Arabic")
                ->and($en[$key])->toBe(stripslashes($fallback), "{$screen}: academics.{$key} says something else in English than the screen")
                ->and($dv[$key])->not->toBe($en[$key], "academics.{$key} is English in Dhivehi")
                ->and($ar[$key])->not->toBe($en[$key], "academics.{$key} is English in Arabic");
        }

        // No bare English: a text node, a written-out placeholder, label,
        // title or phone caption (`data-label`), and no field without a name.
        expect(preg_match_all('/>\s*[A-Z][A-Za-z]+[^<>{}]*</', $source, $text))->toBe(0, "{$screen} has English text nodes: ".implode(' | ', $text[0] ?? []))
            ->and(preg_match_all('/(placeholder|aria-label|title|data-label)="[A-Za-z][^"]*"/', $source, $attrs))->toBe(0, "{$screen} has English attributes: ".implode(' | ', $attrs[0] ?? []))
            ->and(unnamedFields($source))->toBe([], "{$screen} has fields with no name");
    }
});

it('names every code the registers and attendance screens show, in all three languages', function () {
    $codes = [
        ...array_map(fn ($case) => 'register_status_'.$case->value, LessonLogStatus::cases()),
        ...array_map(fn ($case) => 'attendance_status_'.$case->value, AttendanceStatus::cases()),
        ...array_map(fn ($case) => 'attendance_source_'.$case->value, AttendanceSource::cases()),
        ...array_map(fn ($case) => 'note_status_'.$case->value, AbsenceNoteStatus::cases()),
        // Why a teacher's day is empty names the weekday.
        ...array_map(fn ($day) => 'weekday_'.$day, ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']),
    ];

    foreach ($codes as $key) {
        expect(trans("academics.{$key}", [], 'en'))->not->toBe("academics.{$key}", "academics.{$key} has no English")
            ->and(trans("academics.{$key}", [], 'dv'))->toMatch('/\p{Thaana}/u', "academics.{$key} in Dhivehi")
            ->and(trans("academics.{$key}", [], 'ar'))->toMatch('/\p{Arabic}/u', "academics.{$key} in Arabic");
    }
});

it('leaves no English in what the server says on the registers and attendance screens, and says it in Dhivehi and Arabic', function () {
    $english = [];
    foreach (academicsServerFiles() as $file) {
        $english = [...$english, ...refusalEnglishIn($file)];
    }
    expect($english)->toBe([]);

    $keys = refusalKeysIn(academicsServerFiles());
    expect($keys)->toContain('academics.flash_register_submitted', 'academics.no_teacher_profile', 'academics.empty_no_slots', 'academics.generated_some_skipped', 'academics.error_register_locked', 'academics.error_mark_status', 'academics.error_note_approved', 'academics.error_type_code_exists', 'academics.error_policy_part_lesson');
    foreach ($keys as $key) {
        expect(trans($key, [], 'en'))->not->toBe($key, "{$key} has no English")
            ->and(trans($key, [], 'dv'))->toMatch('/\p{Thaana}/u', "{$key} in Dhivehi")
            ->and(trans($key, [], 'ar'))->toMatch('/\p{Arabic}/u', "{$key} in Arabic");
    }
    // A teacher's empty day names its weekday in the page's language, not
    // the code it is looked up by.
    expect(__('academics.empty_no_slots', ['weekday' => __('academics.weekday_sunday', [], 'dv')], 'dv'))->not->toMatch('/[A-Za-z]{3,}/');
});

it('gives the five reasons a school starts with a Dhivehi and an Arabic name, and keeps one the office typed', function () {
    expect(AbsenceType::query()->orderBy('sort_order')->get(['code', 'name_dhivehi', 'name_arabic'])->every(
        fn (AbsenceType $type) => preg_match('/\p{Thaana}/u', (string) $type->name_dhivehi) === 1 && preg_match('/\p{Arabic}/u', (string) $type->name_arabic) === 1,
    ))->toBeTrue();

    $illness = AbsenceType::query()->where('code', 'illness')->firstOrFail();
    $illness->update(['name_dhivehi' => 'ހުމު']);
    (require base_path('database/migrations/2026_10_09_000001_absence_reason_names_in_dhivehi_and_arabic.php'))->up();

    expect($illness->refresh()->name_dhivehi)->toBe('ހުމު');
});

it('serves the registers and attendance screens in Dhivehi, and says what was saved and refused in Dhivehi', function () {
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $office = actingPeopleAdmin(['registers.manage', 'registers.fill', 'manage_attendance', 'mark_attendance', 'requests.review']);
    $old = makeLessonLog(['year' => $year, 'classroom_id' => $class->id]);
    $today = makeLessonLog(['year' => $year, 'classroom_id' => $class->id, 'date' => now()->toDateString()]);
    $student = makeStudent(['first_name' => 'Aishath', 'last_name' => 'Naseem']);
    $note = AbsenceNote::query()->create([
        'student_id' => $student->id, 'created_by' => $office->id, 'date' => now()->toDateString(), 'reason' => 'Fever.',
        'type' => 'illness', 'absence_type_id' => AbsenceType::query()->where('code', 'illness')->value('id'),
        'status' => AbsenceNoteStatus::Submitted->value, 'affects_attendance' => true,
    ]);
    $dv = academicsBook('dv');

    app()->setLocale('dv');
    foreach ([
        ['academics.registers.index', [], 'Academics/Registers/Unfilled', 'unfilled_title'],
        ['academics.registers.show', [$old->id], 'Academics/Registers/Show', 'register_title'],
        ['academics.attendance.index', [], 'Academics/Attendance/Index', 'reports_title'],
        ['academics.attendance.daily', [], 'Academics/Attendance/Daily', 'daily_title'],
        ['academics.attendance.absences', [], 'Academics/Attendance/AbsencesToday', 'absences_title'],
        ['academics.attendance-policy.index', [], 'Academics/AttendancePolicy/Index', 'policy_title'],
        ['academics.absence-types.index', [], 'Academics/AbsenceTypes/Index', 'types_title'],
    ] as [$route, $parameters, $component, $key]) {
        $this->withoutLocalizationMiddleware()->actingAs($office)
            ->get(route($route, $parameters))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component)->where("t.{$key}", $dv[$key]));
    }

    // Why today is empty is the server's, in Dhivehi: this login has no
    // teachers row.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->get(route('academics.registers.today'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Academics/Registers/Today')
            ->where('t.today_title', $dv['today_title'])
            ->where('empty.code', 'no_teacher')
            ->where('empty.message', $dv['empty_no_teacher']));

    // The notes list carries the school's names for its reasons, so the
    // page can name a note's reason in Dhivehi rather than print its code.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->get(route('academics.absence-notes.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Academics/AbsenceNotes/Index')
            ->where('t.notes_title', $dv['notes_title'])
            ->where('notes.0.type', 'illness')
            ->where('types', fn ($types) => collect($types)->firstWhere('code', 'illness')['name_dhivehi'] === 'ބަލިވުން'));

    // What generating did, and the saved messages, in Dhivehi.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('academics.registers.generate'), ['academic_year_id' => $year->id, 'from' => '2026-08-24', 'to' => '2026-08-24'])
        ->assertSessionHas('success', $dv['generated_no_slots']);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('academics.absence-notes.approve', $note->id))
        ->assertSessionHas('success', $dv['flash_note_approved']);

    // And the refusals, beside the field they are about.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('academics.absence-notes.approve', $note->id))
        ->assertSessionHasErrors(['status' => $dv['error_note_approved']]);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->put(route('academics.registers.update', $old->id), ['taught_summary' => 'Letters.'])
        ->assertSessionHasErrors(['status' => $dv['error_register_locked']]);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->put(route('academics.registers.update', $today->id), [])
        ->assertSessionHasErrors(['taught_summary' => $dv['error_taught_needed']]);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('academics.attendance.daily.store'), ['class_id' => $class->id, 'date' => now()->toDateString(), 'attendance' => [['student_id' => $student->id, 'status' => 'present']]])
        ->assertSessionHasErrors(['mode' => $dv['error_daily_disabled']]);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->put(route('academics.attendance-policy.update'), ['mode' => 'weekly'])
        ->assertSessionHasErrors(['mode' => $dv['error_policy_mode']]);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('academics.absence-types.store'), ['name' => 'Illness'])
        ->assertSessionHasErrors(['code' => $dv['error_type_code_exists']]);
});
