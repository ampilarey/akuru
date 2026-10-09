<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Actions\RecordStudentMovementAction;
use App\Domains\Academics\Actions\SaveAnnouncementAction;
use App\Domains\Academics\Enums\AttendanceStatus;
use App\Domains\Academics\Enums\BehaviorType;
use App\Domains\Academics\Enums\CalendarDayType;
use App\Domains\Academics\Enums\MovementDirection;
use App\Domains\Academics\Enums\MovementSource;
use App\Domains\Academics\Models\CalendarDay;
use App\Domains\Finance\Enums\InvoiceStatus;
use App\Domains\Finance\Enums\PaymentPlanStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Actions\ResolveAttendanceNotificationStateAction;
use App\Domains\Notifications\Actions\ResolveNotificationPreferencesAction;
use App\Domains\Notifications\Actions\StartMessageThreadAction;
use App\Domains\People\Actions\AttachGuardianAction;
use App\Domains\People\Enums\GuardianRelationship;
use App\Domains\People\Enums\StudentStatus;
use App\Enums\Hifz\HifzEnrollmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

/**
 * The family portal in Dhivehi and Arabic (BACKLOG C21, slice PT1a, STATUS
 * §5pv).
 *
 * The courses, the Library and the public site already read in three
 * languages; the school's own screens did not. 112 of the 252 Inertia pages
 * read no phrase book, and the first a family opens are these: the home with
 * its tiles, the children, attendance, homework, the noticeboard and the
 * school calendar. Every word on them was English, and so was every word the
 * server wrote for them — the home's title, sections and tile lines, the
 * tick's saved message and its three refusals.
 *
 * Slice PT1b (STATUS §5pw) adds notifications and messages: the inbox, a
 * thread with its poll, a new message. The notifications page read the 72 KB
 * `admin` book for its eleven phone phrases, which now live in `portal`; the
 * saved messages and the thirteen refusals of the message actions were
 * English, and a thread's or a recipient's refusal had no place on the page.
 *
 * Slice PT2 (STATUS §5py) adds the family's records: report cards, exam
 * results, awards, behaviour, fees, a child's work, arrivals and departures,
 * and lost property. A behaviour record's type, a payment plan's state, and
 * whether a child arrived or left and how it was recorded were printed as
 * the server's codes or English labels; the fee payment's three refusals
 * were English, and a Pay button's refusal had no place on the page.
 */
uses(RefreshDatabase::class);

/** The family's day pages and its talk; every phrase on them is `t.key || 'English'`, from the `portal` book. */
function portalDayScreens(): array
{
    return [
        'Portal/Home', 'Portal/Children', 'Portal/Attendance', 'Portal/Homework', 'Portal/Announcements', 'Portal/SchoolCalendar',
        // Slice PT1b.
        'Portal/Notifications', 'Portal/Messages/Index', 'Portal/Messages/Show', 'Portal/Messages/Create',
        // Slice PT2.
        'Portal/ReportCards', 'Portal/Exams', 'Portal/Awards', 'Portal/Behavior', 'Portal/Invoices', 'Portal/Work', 'Portal/Movements', 'Portal/FoundItems',
    ];
}

/** Where the server writes what those pages say. */
function portalDayServerFiles(): array
{
    return [
        'app/Domains/Portal/Actions/ComposePortalHomeAction.php',
        'app/Domains/Portal/Http/Controllers/PortalHomeController.php',
        'app/Domains/Portal/Http/Controllers/GuardianChildrenController.php',
        'app/Domains/Portal/Http/Controllers/PortalAttendanceController.php',
        'app/Domains/Portal/Http/Controllers/PortalHomeworkController.php',
        'app/Domains/Portal/Http/Controllers/PortalAnnouncementController.php',
        'app/Domains/Portal/Http/Controllers/PortalHolidayController.php',
        'app/Domains/Academics/Actions/TickHomeworkAction.php',
        // Slice PT1b.
        'app/Domains/Portal/Http/Controllers/PortalNotificationController.php',
        'app/Domains/Portal/Http/Controllers/PortalMessageController.php',
        'app/Domains/Notifications/Actions/AttachPollToThreadAction.php',
        'app/Domains/Notifications/Actions/ReplyToMessageThreadAction.php',
        'app/Domains/Notifications/Actions/RespondToMessagePollAction.php',
        'app/Domains/Notifications/Actions/StartClassMessageThreadAction.php',
        'app/Domains/Notifications/Actions/StartMessageThreadAction.php',
        'app/Domains/Notifications/Actions/ShowMessageThreadAction.php',
        'app/Domains/Notifications/Actions/ListMessageInboxAction.php',
        // Slice PT2.
        'app/Domains/Portal/Http/Controllers/PortalReportCardController.php',
        'app/Domains/Portal/Http/Controllers/PortalExamController.php',
        'app/Domains/Portal/Http/Controllers/PortalAwardController.php',
        'app/Domains/Portal/Http/Controllers/PortalBehaviorController.php',
        'app/Domains/Portal/Http/Controllers/PortalInvoiceController.php',
        'app/Domains/Portal/Http/Controllers/PortalStudentWorkController.php',
        'app/Domains/Portal/Http/Controllers/PortalMovementController.php',
        'app/Domains/Portal/Http/Controllers/PortalFoundItemController.php',
        'app/Domains/Academics/Actions/ListMovementsForGuardianAction.php',
        'app/Domains/Finance/Actions/PayPortalInvoiceAction.php',
        'app/Domains/Finance/Actions/InitiateInvoicePaymentAction.php',
    ];
}

function portalBook(string $locale, string $book = 'portal'): array
{
    return require base_path("resources/lang/{$locale}/{$book}.php");
}

it('keys every string on the family’s day pages in three languages', function () {
    [$en, $dv, $ar] = [portalBook('en'), portalBook('dv'), portalBook('ar')];

    foreach (portalDayScreens() as $screen) {
        $source = file_get_contents(resource_path("js/Pages/{$screen}.jsx"));
        preg_match_all("/(?<![\\w\$.])t\\.([a-z][a-z0-9_]+) \\|\\| '((?:[^'\\\\]|\\\\.)*)'/", $source, $uses, PREG_SET_ORDER);
        expect($uses)->not->toBeEmpty("{$screen} uses no phrases");

        foreach ($uses as [, $key, $fallback]) {
            expect(array_key_exists($key, $en))->toBeTrue("{$screen}: portal.{$key} is missing in English")
                ->and(array_key_exists($key, $dv))->toBeTrue("{$screen}: portal.{$key} is missing in Dhivehi")
                ->and(array_key_exists($key, $ar))->toBeTrue("{$screen}: portal.{$key} is missing in Arabic")
                ->and($en[$key])->toBe(stripslashes($fallback), "{$screen}: portal.{$key} says something else in English than the screen")
                ->and($dv[$key])->not->toBe($en[$key], "portal.{$key} is English in Dhivehi")
                ->and($ar[$key])->not->toBe($en[$key], "portal.{$key} is English in Arabic");
        }

        // No bare English: a text node, a written-out placeholder, label,
        // title or phone caption (`data-label`), and no field without a name.
        expect(preg_match_all('/>\s*[A-Z][A-Za-z]+[^<>{}]*</', $source, $text))->toBe(0, "{$screen} has English text nodes: ".implode(' | ', $text[0] ?? []))
            ->and(preg_match_all('/(placeholder|aria-label|title|data-label)="[A-Za-z][^"]*"/', $source, $attrs))->toBe(0, "{$screen} has English attributes: ".implode(' | ', $attrs[0] ?? []))
            ->and(unnamedFields($source))->toBe([], "{$screen} has fields with no name");
    }
});

it('names every code the family’s day pages show, in all three languages', function () {
    $portal = [
        ...array_map(fn ($case) => 'attendance_status_'.$case->value, AttendanceStatus::cases()),
        ...array_map(fn ($case) => 'invoice_status_'.$case->value, InvoiceStatus::cases()),
        // A Hifz row is an enrolment, or progress with no enrolment yet.
        ...array_map(fn ($case) => 'hifz_status_'.$case->value, HifzEnrollmentStatus::cases()),
        'hifz_status_in_progress', 'hifz_status_needs_revision',
        ...array_map(fn ($case) => 'student_status_'.$case->value, StudentStatus::cases()),
        ...array_map(fn ($type) => 'notice_type_'.$type, SaveAnnouncementAction::TYPES),
        ...array_map(fn ($priority) => 'notice_priority_'.$priority, SaveAnnouncementAction::PRIORITIES),
        ...array_map(fn ($case) => 'calendar_type_'.$case->value, CalendarDayType::cases()),
        ...array_map(fn ($state) => 'notify_state_'.$state, [
            ResolveAttendanceNotificationStateAction::NOTIFIED,
            ResolveAttendanceNotificationStateAction::NOT_SENT,
            ResolveAttendanceNotificationStateAction::NOT_APPLICABLE,
        ]),
        ...array_map(fn ($day) => 'weekday_'.$day, ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']),
        ...array_map(fn ($prayer) => 'prayer_'.$prayer, ['fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha']),
        // Slice PT1b: what reaches a person, the categories a notification is
        // written with, the audiences of a class message, a phone's kind.
        ...array_map(fn ($category) => 'notify_pref_'.$category, array_keys(ResolveNotificationPreferencesAction::CATEGORIES)),
        ...array_map(fn ($category) => 'notify_category_'.$category, [
            ...array_keys(ResolveNotificationPreferencesAction::CATEGORIES),
            'system', 'payment', 'lending', 'event', 'course', 'courses', 'assignment', 'account',
        ]),
        ...array_map(fn ($audience) => 'messages_audience_'.$audience, ['guardians', 'students', 'both']),
        ...array_map(fn ($platform) => 'devices_platform_'.$platform, ['android', 'ios', 'web']),
        // Slice PT2: a behaviour record's type, a payment plan's state, and an
        // arrival or departure and how it was recorded.
        ...array_map(fn ($case) => 'behavior_type_'.$case->value, BehaviorType::cases()),
        ...array_map(fn ($case) => 'plan_status_'.$case->value, PaymentPlanStatus::cases()),
        ...array_map(fn ($case) => 'movement_direction_'.$case->value, MovementDirection::cases()),
        ...array_map(fn ($case) => 'movement_source_'.$case->value, MovementSource::cases()),
    ];
    // A relationship is the `learn` book's, which the shell shares.
    $learn = [
        'relationship_self', 'relationship_child',
        ...array_map(fn ($case) => 'relationship_'.$case->value, GuardianRelationship::cases()),
    ];

    foreach ([...array_map(fn ($key) => "portal.{$key}", $portal), ...array_map(fn ($key) => "learn.{$key}", $learn)] as $key) {
        expect(trans($key, [], 'en'))->not->toBe($key, "{$key} has no English")
            ->and(trans($key, [], 'dv'))->toMatch('/\p{Thaana}/u', "{$key} in Dhivehi")
            ->and(trans($key, [], 'ar'))->toMatch('/\p{Arabic}/u', "{$key} in Arabic");
    }
});

it('leaves no English in what the server says on the family’s day pages, and says it in Dhivehi and Arabic', function () {
    $english = [];
    foreach (portalDayServerFiles() as $file) {
        $english = [...$english, ...refusalEnglishIn($file)];
    }
    expect($english)->toBe([]);

    $keys = refusalKeysIn(portalDayServerFiles());
    expect($keys)->toContain('portal.home_title_parent', 'portal.tile_invoices_unpaid', 'nav.absence_notes', 'portal.error_homework_none', 'portal.flash_message_sent', 'portal.error_thread_not_yours', 'portal.unknown_person', 'portal.error_invoice_paid', 'portal.error_payment_failed');
    foreach ($keys as $key) {
        expect(trans($key, [], 'en'))->not->toBe($key, "{$key} has no English")
            ->and(trans($key, [], 'dv'))->toMatch('/\p{Thaana}/u', "{$key} in Dhivehi")
            ->and(trans($key, [], 'ar'))->toMatch('/\p{Arabic}/u', "{$key} in Arabic");
    }
});

/** A parent with one verified child on a class roster, signed in. */
function pt1aParent(): array
{
    Role::findOrCreate('parent', 'web');
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $student = makeStudent(['first_name' => 'Aishath', 'last_name' => 'Naseem']);
    app(AssignStudentToClassAction::class)->execute($class, $student->id, '2026-01-01');
    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, GuardianRelationship::Mother, true);
    $parent = User::query()->findOrFail($guardian->user_id);
    $parent->assignRole('parent');

    return [$parent, $student, $year];
}

it('serves the family’s home, children, attendance, homework, noticeboard and calendar in Dhivehi', function () {
    [$parent, , $year] = pt1aParent();
    makeNotice(['title' => 'Sports day', 'title_dhivehi' => 'ކުޅިވަރު ދުވަސް', 'priority' => 'urgent']);
    CalendarDay::query()->create([
        'academic_year_id' => $year->id, 'date' => now()->addDays(3)->toDateString(), 'type' => CalendarDayType::Holiday->value,
        'title' => 'Independence Day', 'title_dhivehi' => 'ޖުމްހޫރީ ދުވަސް', 'affects_timetable' => true, 'is_public' => true,
    ]);
    $dv = portalBook('dv');

    app()->setLocale('dv');
    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->get(route('portal.home'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Portal/Home')
            ->where('title', $dv['home_title_parent'])
            ->where('t.home_intro', $dv['home_intro'])
            ->where('sections.0.label', portalBook('dv', 'nav')['attendance'])
            ->where('sections.1.label', $dv['label_exams'])
            ->where('students.0.relationship', 'mother')
            ->where('tiles', fn ($tiles) => collect($tiles)->every(fn (array $tile) => preg_match('/\p{Thaana}/u', $tile['label'].$tile['status']) === 1)
                && collect($tiles)->firstWhere('key', 'announcements')['status'] === trans_choice('portal.tile_notices_count', 1, ['count' => 1])));

    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->get(route('portal.children'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Portal/Children')->where('t.children_title', $dv['children_title']));
    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->get(route('portal.attendance'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Portal/Attendance')->where('t.attendance_summary', $dv['attendance_summary']));
    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->get(route('portal.homework'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Portal/Homework')->where('t.homework_intro', $dv['homework_intro']));
    // The notice the office wrote in Dhivehi reads in Dhivehi.
    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->get(route('portal.announcements'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Portal/Announcements')
            ->where('t.notice_priority_urgent', $dv['notice_priority_urgent'])
            ->where('announcements.0.title', 'ކުޅިވަރު ދުވަސް'));
    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->get(route('portal.holidays'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Portal/SchoolCalendar')
            ->where('t.calendar_no_school', $dv['calendar_no_school'])
            ->where('upcoming.0.title_dhivehi', 'ޖުމްހޫރީ ދުވަސް'));
});

it('tells a pupil in Dhivehi that a homework tick was saved, and why one was refused', function () {
    Role::findOrCreate('student', 'web');
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $pupil = User::factory()->create();
    $pupil->assignRole('student');
    $student = makeStudent(['user_id' => $pupil->id]);
    app(AssignStudentToClassAction::class)->execute($class, $student->id, '2026-01-01');
    $set = makeLessonLog(['year' => $year, 'classroom_id' => $class->id, 'homework' => 'Read page 4.']);
    $none = makeLessonLog(['year' => $year, 'classroom_id' => $class->id, 'homework' => null]);
    $dv = portalBook('dv');

    app()->setLocale('dv');
    $this->withoutLocalizationMiddleware()->actingAs($pupil)
        ->post(route('portal.homework.tick', $set->id), ['student_id' => $student->id, 'done' => true])
        ->assertRedirect(route('portal.homework'))
        ->assertSessionHas('success', $dv['flash_homework_ticked']);
    $this->withoutLocalizationMiddleware()->actingAs($pupil)
        ->post(route('portal.homework.tick', $none->id), ['student_id' => $student->id, 'done' => true])
        ->assertSessionHasErrors(['lesson_log_id' => $dv['error_homework_none']]);
});

it('serves notifications and messages in Dhivehi, and says what was sent and refused in Dhivehi', function () {
    [$parent] = pt1aParent();
    $teacher = User::factory()->create(['name' => 'Ustaz Ahmed']);
    $dv = portalBook('dv');

    $threadId = (int) app(StartMessageThreadAction::class)->execute((int) $teacher->id, [(int) $parent->id], 'Trip', 'Bring a hat.')->id;

    app()->setLocale('dv');
    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->get(route('portal.notifications'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Portal/Notifications')
            ->where('t.notifications_title', $dv['notifications_title'])
            ->where('t.devices_title', $dv['devices_title']));
    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->get(route('portal.messages'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Portal/Messages/Index')->where('t.messages_title', $dv['messages_title']));
    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->get(route('portal.messages.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Portal/Messages/Create')->where('t.messages_send', $dv['messages_send']));
    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->get(route('portal.messages.show', $threadId))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Portal/Messages/Show')->where('t.messages_send_reply', $dv['messages_send_reply']));

    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->post(route('portal.messages.reply', $threadId), ['body' => 'Thank you.'])
        ->assertSessionHas('success', $dv['flash_reply_sent']);
    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->post(route('portal.messages.poll', $threadId), ['choice' => 0])
        ->assertSessionHasErrors(['choice' => $dv['error_poll_none']]);
    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->post(route('portal.notifications.preferences'), ['preferences' => ['message' => true]])
        ->assertSessionHas('success', $dv['flash_notification_prefs_saved']);
});

it('serves the family’s records in Dhivehi, names how an arrival was recorded, and refuses a payment in Dhivehi', function () {
    [$parent, $student, $year] = pt1aParent();
    $staff = User::factory()->create();
    app(RecordStudentMovementAction::class)->execute((int) $student->id, MovementDirection::In, (int) $staff->id, MovementSource::Card);
    $paid = makeSchoolInvoice((int) $staff->id, (int) $student->id, (int) $year->id, 500);
    $paid->forceFill(['paid_amount' => 500])->save();
    $notTheirs = makeSchoolInvoice((int) $staff->id, (int) makeStudent()->id, (int) $year->id, 300);
    $dv = portalBook('dv');

    app()->setLocale('dv');
    foreach ([
        'portal.report-cards' => ['Portal/ReportCards', 'report_cards_title'],
        'portal.exams' => ['Portal/Exams', 'exams_title'],
        'portal.awards' => ['Portal/Awards', 'awards_title'],
        'portal.behavior' => ['Portal/Behavior', 'behavior_title'],
        'portal.invoices' => ['Portal/Invoices', 'fees_title'],
        'portal.work' => ['Portal/Work', 'work_title'],
        'portal.movements' => ['Portal/Movements', 'movements_title'],
        'portal.found-items' => ['Portal/FoundItems', 'found_title'],
    ] as $route => [$component, $key]) {
        $this->withoutLocalizationMiddleware()->actingAs($parent)
            ->get(route($route))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component)->where("t.{$key}", $dv[$key]));
    }

    // How an arrival was recorded travels as a code the page names.
    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->get(route('portal.movements'))
        ->assertInertia(fn (Assert $page) => $page->where('movements.0.source', 'card')->where('movements.0.direction', 'in'));

    // A paid invoice, and one that is not theirs, are refused in Dhivehi.
    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->post(route('portal.invoices.pay', $paid->id), ['mode' => 'full'])
        ->assertSessionHasErrors(['invoice_id' => $dv['error_invoice_paid']]);
    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->post(route('portal.invoices.pay', $notTheirs->id), ['mode' => 'full'])
        ->assertSessionHasErrors(['invoice_id' => $dv['error_invoice_unavailable']]);
});
