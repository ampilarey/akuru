<?php

use App\Domains\Academics\Actions\SaveAnnouncementAction;
use App\Domains\Academics\Actions\SaveRoomBookingAction;
use App\Domains\Academics\Actions\SaveStudentWorkAction;
use App\Domains\Academics\Actions\SaveTeachingMaterialAction;
use App\Domains\Academics\Actions\SaveTimetableEntryAction;
use App\Domains\Academics\Enums\AbsenceNoteStatus;
use App\Domains\Academics\Enums\AcademicYearStatus;
use App\Domains\Academics\Enums\AttendanceSource;
use App\Domains\Academics\Enums\AttendanceStatus;
use App\Domains\Academics\Enums\BehaviorType;
use App\Domains\Academics\Enums\CalendarDayType;
use App\Domains\Academics\Enums\CoursePlanStatus;
use App\Domains\Academics\Enums\LessonLogStatus;
use App\Domains\Academics\Enums\MeetingSlotStatus;
use App\Domains\Academics\Enums\PromotionOutcome;
use App\Domains\Academics\Enums\RoomType;
use App\Domains\Academics\Enums\TermStatus;
use App\Domains\Academics\Models\AbsenceNote;
use App\Domains\Academics\Models\AbsenceType;
use App\Domains\Courses\Enums\AssessmentStatus;
use App\Domains\Courses\Enums\AssessmentType;
use App\Domains\Identity\Models\User;
use App\Domains\People\Enums\StudentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
 *
 * OA2 (STATUS §5qf) adds the school's structure and time: the academic
 * years, the periods, the classes and a class's roster, the school calendar,
 * the timetable, the promotion wizard, the rooms and their bookings. A year's
 * and a term's state, a calendar day's and a room's type, a pupil's state, a
 * promotion's outcome and the kind of a clash were printed as codes; a clash
 * was *Timetable conflicts: teacher#12*; a year the server refused to
 * activate was flashed green, in English, as if it had worked; and a
 * promotion nobody had previewed, or one with a class mapped nowhere, was a
 * 500 page.
 *
 * OA3 (STATUS §5qh) adds teaching: the teaching materials, the teaching
 * plans, the pupils' work, a teacher's own meetings and the office's, the
 * staff noticeboard and the behaviour records. A plan's, a slot's and a
 * notice's state, type, priority and audience, and a behaviour record's type
 * and the categories a school starts with were printed as codes; a refused
 * move or hide of a pupil's work, and a refused file removal, were said
 * nowhere on the page.
 */
uses(RefreshDatabase::class);

/** The Academics screens in three languages; every phrase on them is `t.key || 'English'`, from the `academics` book. */
function academicsScreens(): array
{
    return [
        // OA1: registers and attendance.
        'Academics/Registers/Show', 'Academics/Registers/Today', 'Academics/Registers/Unfilled',
        'Academics/Attendance/Index', 'Academics/Attendance/Daily', 'Academics/Attendance/AbsencesToday',
        'Academics/AbsenceNotes/Index', 'Academics/AttendancePolicy/Index', 'Academics/AbsenceTypes/Index',
        // OA2: the school's structure and time.
        'Academics/Years/Index', 'Academics/Periods/Index', 'Academics/Classes/Index', 'Academics/Classes/Show',
        'Academics/Calendar/Index', 'Academics/Timetable/Builder', 'Academics/Promotion/Wizard',
        'Academics/Rooms/Index', 'Academics/Bookings/Index',
        // OA3: teaching.
        'Academics/Materials/Index', 'Academics/Plans/Index', 'Academics/Work/Index', 'Academics/Teach/Meetings',
        'Academics/Meetings/Index', 'Academics/Announcements/Index', 'Academics/Behavior/Index',
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
        // OA2.
        'app/Domains/Academics/Http/Controllers/AcademicYearController.php',
        'app/Domains/Academics/Http/Controllers/PeriodDirectoryController.php',
        'app/Domains/Academics/Http/Controllers/ClassDirectoryController.php',
        'app/Domains/Academics/Http/Controllers/CalendarDayController.php',
        'app/Domains/Academics/Http/Controllers/TimetableBuilderController.php',
        'app/Domains/Academics/Http/Controllers/PromotionController.php',
        'app/Domains/Academics/Http/Controllers/RoomDirectoryController.php',
        'app/Domains/Academics/Http/Controllers/RoomBookingController.php',
        'app/Domains/Academics/Actions/ActivateAcademicYearAction.php',
        'app/Domains/Academics/Actions/CloseAcademicYearAction.php',
        'app/Domains/Academics/Actions/SavePeriodAction.php',
        'app/Domains/Academics/Actions/AssignClassTeacherAction.php',
        'app/Domains/Academics/Actions/SaveCalendarDayAction.php',
        'app/Domains/Academics/Actions/SaveTimetableEntryAction.php',
        'app/Domains/Academics/Actions/CopyTimetableEntriesAction.php',
        'app/Domains/Academics/Actions/PromoteStudentsAction.php',
        'app/Domains/Academics/Actions/DescribePromotionReportAction.php',
        'app/Domains/Academics/Actions/SaveRoomAction.php',
        'app/Domains/Academics/Actions/SaveRoomBookingAction.php',
        'app/Domains/Academics/Exceptions/TimetableConflictException.php',
        'app/Domains/Academics/Exceptions/RoomBookingClashException.php',
        // OA3.
        'app/Domains/Academics/Http/Controllers/TeachingMaterialController.php',
        'app/Domains/Academics/Http/Controllers/CoursePlanController.php',
        'app/Domains/Academics/Http/Controllers/StudentWorkController.php',
        'app/Domains/Academics/Http/Controllers/TeachMeetingController.php',
        'app/Domains/Academics/Http/Controllers/MeetingSlotController.php',
        'app/Domains/Academics/Http/Controllers/AnnouncementController.php',
        'app/Domains/Academics/Http/Controllers/BehaviorRecordController.php',
        'app/Domains/Academics/Actions/SaveTeachingMaterialAction.php',
        'app/Domains/Academics/Actions/AttachFileToMaterialAction.php',
        'app/Domains/Academics/Actions/RemoveMaterialFileAction.php',
        'app/Domains/Academics/Actions/SaveCoursePlanAction.php',
        'app/Domains/Academics/Actions/SavePlanTopicAction.php',
        'app/Domains/Academics/Actions/CopyPlanAction.php',
        'app/Domains/Academics/Actions/SaveStudentWorkAction.php',
        'app/Domains/Academics/Actions/ReassignStudentWorkAction.php',
        'app/Domains/Academics/Actions/HideStudentWorkAction.php',
        'app/Domains/Academics/Actions/GenerateMeetingSlotsAction.php',
        'app/Domains/Academics/Actions/SaveMeetingSlotAction.php',
        'app/Domains/Academics/Actions/SaveBehaviorRecordAction.php',
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
        // OA2. The calendar's months and weekday initials are the book's: a
        // browser names no month in Dhivehi.
        ...array_map(fn ($case) => 'year_status_'.$case->value, AcademicYearStatus::cases()),
        ...array_map(fn ($case) => 'term_status_'.$case->value, TermStatus::cases()),
        ...array_map(fn ($case) => 'calendar_type_'.$case->value, CalendarDayType::cases()),
        ...array_map(fn ($case) => 'room_type_'.$case->value, RoomType::cases()),
        ...array_map(fn ($case) => 'student_status_'.$case->value, StudentStatus::cases()),
        ...array_map(fn ($case) => 'assessment_status_'.$case->value, AssessmentStatus::cases()),
        ...array_map(fn ($case) => 'assessment_type_'.$case->value, AssessmentType::cases()),
        ...array_map(fn ($case) => 'promotion_outcome_'.$case->value, PromotionOutcome::cases()),
        // What a slot or a booking can clash with (TimetableConflictChecker,
        // SaveTimetableEntryAction, RoomBookingClashChecker).
        ...array_map(fn ($type) => 'conflict_'.$type, ['teacher', 'room', 'class', 'booking', 'timetable']),
        ...array_map(fn ($month) => 'month_'.$month, range(1, 12)),
        ...array_map(fn ($day) => 'weekday_initial_'.$day, ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']),
        // OA3. A behaviour category is the school's own word; the three a
        // school starts with are named.
        ...array_map(fn ($case) => 'plan_status_'.$case->value, CoursePlanStatus::cases()),
        ...array_map(fn ($case) => 'behavior_type_'.$case->value, BehaviorType::cases()),
        ...array_map(fn ($case) => 'meeting_status_'.$case->value, MeetingSlotStatus::cases()),
        ...array_map(fn ($type) => 'notice_type_'.$type, SaveAnnouncementAction::TYPES),
        ...array_map(fn ($priority) => 'notice_priority_'.$priority, SaveAnnouncementAction::PRIORITIES),
        ...array_map(fn ($audience) => 'notice_audience_'.$audience, SaveAnnouncementAction::AUDIENCES),
        ...array_map(fn ($category) => 'behavior_category_'.$category, ['conduct', 'homework', 'other']),
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
    expect($keys)->toContain('academics.flash_register_submitted', 'academics.no_teacher_profile', 'academics.empty_no_slots', 'academics.generated_some_skipped', 'academics.error_register_locked', 'academics.error_mark_status', 'academics.error_note_approved', 'academics.error_type_code_exists', 'academics.error_policy_part_lesson')
        ->and($keys)->toContain('academics.flash_year_created', 'academics.error_year_another_active', 'academics.error_class_exists', 'academics.flash_copied_week', 'academics.copied_conflict_reason', 'academics.error_timetable_conflicts', 'academics.error_booking_clashes', 'academics.error_promotion_dry_run', 'academics.error_promotion_unmapped', 'academics.error_room_not_bookable')
        ->and($keys)->toContain('academics.flash_notice_published', 'academics.flash_meeting_slots_saved', 'academics.error_slot_length', 'academics.error_slot_overlap', 'academics.error_material_not_yours', 'academics.error_work_same_pupil', 'academics.error_work_photo', 'academics.error_topic_title_required', 'academics.error_category_required');
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

it('serves the school structure and time screens in Dhivehi, and says what was saved and refused in Dhivehi', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $next = makeYear(['name' => '2027-2028', 'start_date' => '2027-08-01', 'end_date' => '2028-06-30', 'status' => 'upcoming']);
    $classA = makeClass($year, 'Grade 5', 'A');
    $classB = makeClass($year, 'Grade 5', 'B');
    $office = actingPeopleAdmin(['manage_timetables', 'timetables.allow_conflict', 'rooms.manage', 'calendar.manage']);
    $dv = academicsBook('dv');

    app()->setLocale('dv');
    foreach ([
        ['academics.years.index', [], 'Academics/Years/Index', 'years_title'],
        ['academics.periods.index', [], 'Academics/Periods/Index', 'periods_title'],
        ['academics.classes.index', [], 'Academics/Classes/Index', 'classes_title'],
        ['academics.classes.show', [$classA->id], 'Academics/Classes/Show', 'classes_teacher'],
        ['academics.calendar.index', [], 'Academics/Calendar/Index', 'calendar_title'],
        ['academics.timetable.index', [], 'Academics/Timetable/Builder', 'timetable_title'],
        ['academics.promotion.create', [], 'Academics/Promotion/Wizard', 'promotion_title'],
        ['academics.rooms.index', [], 'Academics/Rooms/Index', 'rooms_title'],
        ['academics.bookings.index', [], 'Academics/Bookings/Index', 'bookings_title'],
    ] as [$route, $parameters, $component, $key]) {
        $this->withoutLocalizationMiddleware()->actingAs($office)
            ->get(route($route, $parameters))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component)->where("t.{$key}", $dv[$key]));
    }

    // A year the server will not activate is a refusal, in red and in
    // Dhivehi; it was flashed as a success, in English.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('academics.years.activate', $next->id))
        ->assertSessionHas('error', $dv['error_year_another_active'])
        ->assertSessionMissing('success');
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('academics.years.store'), ['name' => '2028-2029', 'start_date' => '2028-08-01', 'end_date' => '2029-06-30'])
        ->assertSessionHas('success', $dv['flash_year_created']);

    // A slot that clashes names what it clashes with; it read
    // "Timetable conflicts: teacher#1".
    $period = makePeriodRow();
    $teacher = makeTeacherRow();
    app(SaveTimetableEntryAction::class)->execute([
        'class_id' => $classA->id, 'subject_id' => makeSubject()->id, 'teacher_id' => $teacher->id,
        'academic_year_id' => $year->id, 'day_of_week' => 'monday', 'period_id' => $period->id,
    ]);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('academics.timetable.store'), [
            'class_id' => $classB->id, 'subject_id' => makeSubject()->id, 'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id, 'day_of_week' => 'monday', 'period_id' => $period->id,
        ])
        ->assertSessionHasErrors(['conflicts' => __('academics.error_timetable_conflicts', ['list' => $dv['conflict_teacher']], 'dv')]);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('academics.timetable.store'), [
            'class_id' => $classB->id, 'subject_id' => makeSubject()->id, 'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id, 'day_of_week' => 'monday', 'period_id' => $period->id, 'allow_conflict' => true,
        ])
        ->assertSessionHasErrors(['conflict_reason' => $dv['error_override_reason']]);

    // And so does a booking.
    $room = makeRoomRow('Hall');
    app(SaveRoomBookingAction::class)->execute([
        'academic_year_id' => $year->id, 'room_id' => $room->id, 'title' => 'Assembly',
        'date' => '2026-08-24', 'start_time' => '10:00', 'end_time' => '11:00',
    ]);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('academics.bookings.store'), [
            'academic_year_id' => $year->id, 'room_id' => $room->id, 'title' => 'Rehearsal',
            'date' => '2026-08-24', 'start_time' => '10:30', 'end_time' => '11:30',
        ])
        ->assertSessionHasErrors(['conflicts' => __('academics.error_booking_clashes', ['list' => $dv['conflict_booking']], 'dv')]);

    // The rest of what the office meets: saved messages and refusals.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('academics.rooms.store'), ['name' => 'Library', 'type' => 'other'])
        ->assertSessionHas('success', $dv['flash_room_created']);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('academics.periods.store'), ['name' => 'Period 2', 'start_time' => '09:00', 'end_time' => '08:30', 'order' => 2])
        ->assertSessionHasErrors(['end_time' => $dv['error_end_after_start']]);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('academics.classes.store'), ['academic_year_id' => $year->id, 'name' => 'Grade 5', 'section' => 'A', 'level' => 'Primary'])
        ->assertSessionHasErrors(['name' => $dv['error_class_exists']]);
    $day = ['academic_year_id' => $year->id, 'date' => '2026-09-01', 'type' => 'holiday', 'title' => 'Holiday'];
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('academics.calendar.store'), $day)
        ->assertSessionHas('success', $dv['flash_calendar_saved']);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('academics.calendar.store'), $day)
        ->assertSessionHasErrors(['date' => $dv['error_calendar_date_taken']]);

    // A promotion nobody previewed is a refusal the wizard shows; it was a
    // 500 page.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('academics.promotion.commit'), ['source_year_id' => $year->id, 'target_year_id' => $next->id])
        ->assertSessionHasErrors(['promotion' => $dv['error_promotion_dry_run']]);
});

it('serves the teaching screens in Dhivehi, and says what was saved and refused in Dhivehi', function () {
    Storage::fake('local');
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year, 'Grade 4', 'A');
    $office = actingPeopleAdmin(['registers.fill', 'registers.manage', 'behavior.record', 'behavior.manage', 'meetings.manage']);
    $teacher = makeTeacherRow();
    $dv = academicsBook('dv');

    app()->setLocale('dv');
    foreach ([
        ['academics.materials.index', 'Academics/Materials/Index', 'materials_title'],
        ['academics.plans.index', 'Academics/Plans/Index', 'plans_title'],
        ['academics.work.index', 'Academics/Work/Index', 'work_title'],
        ['academics.meetings.index', 'Academics/Meetings/Index', 'meetings_title'],
        ['announcements.index', 'Academics/Announcements/Index', 'notices_title'],
        ['academics.behavior.index', 'Academics/Behavior/Index', 'behavior_title'],
    ] as [$route, $component, $key]) {
        $this->withoutLocalizationMiddleware()->actingAs($office)
            ->get(route($route))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component)->where("t.{$key}", $dv[$key]));
    }
    // A teacher's own meetings.
    $this->withoutLocalizationMiddleware()->actingAs(User::query()->findOrFail($teacher->user_id))
        ->get(route('teach.meetings'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Academics/Teach/Meetings')->where('t.teach_meetings_title', $dv['teach_meetings_title']));

    // Saved, in Dhivehi: a plan and a behaviour record.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('academics.plans.store'), [
            'title' => 'Reading', 'teacher_id' => $teacher->id, 'subject_id' => makeSubject()->id,
            'classroom_id' => $class->id, 'academic_year_id' => $year->id,
        ])
        ->assertSessionHas('success', $dv['flash_plan_saved']);
    $pupil = makeStudent(['first_name' => 'Aishath', 'last_name' => 'Naseem']);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('academics.behavior.store'), [
            'student_id' => $pupil->id, 'academic_year_id' => $year->id, 'type' => 'compliment',
            'category' => 'conduct', 'description' => 'Helped a friend.', 'date' => '2026-09-01',
        ])
        ->assertSessionHas('success', $dv['flash_behavior_saved']);

    // Refused, in Dhivehi, beside the field: slots too long, a material
    // somebody else wrote, and work moved to the pupil it already belongs to.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('academics.meetings.store'), [
            'academic_year_id' => $year->id, 'teacher_id' => $teacher->id, 'title' => 'Parent-teacher meeting',
            'date' => '2026-09-02', 'start_time' => '18:00', 'end_time' => '19:00', 'slot_minutes' => 200,
        ])
        ->assertSessionHasErrors(['slot_minutes' => $dv['error_slot_length']]);
    $theirs = app(SaveTeachingMaterialAction::class)->execute(['title' => 'Worksheet'], (int) User::factory()->create()->id);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->put(route('academics.materials.update', $theirs->id), ['title' => 'Mine now'])
        ->assertSessionHasErrors(['title' => $dv['error_material_not_yours']]);
    $work = app(SaveStudentWorkAction::class)->execute(['student_id' => $pupil->id], (int) $office->id, UploadedFile::fake()->image('work.jpg', 800, 600));
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('academics.work.reassign', $work->id), ['student_id' => $pupil->id])
        ->assertSessionHasErrors(['student_id' => $dv['error_work_same_pupil']]);
});
