<?php

use Illuminate\Support\Facades\Route;

/**
 * Each EduPage parity row (E1–E22) still has the code the plan says it has.
 *
 * ## Why a test and not another audit
 *
 * `docs/EDUPAGE_FEATURES_PLAN.md` has recorded shipped work as missing
 * **seventeen times** across three audits now. Each audit fixed the rows and
 * left nothing behind to stop the next drift, so the next reader had to redo
 * it. This is that check, committed.
 *
 * ## What it proves, and what it does not
 *
 * It proves the **artifacts exist**: the routes are registered and the classes
 * are defined. That is enough to catch the failure this document actually
 * keeps having — a row described as unbuilt while its controller sits in the
 * tree — and enough to notice a feature being deleted.
 *
 * It does **not** prove a row works end to end, or meets its acceptance
 * criteria, or that a family can complete the task in a browser. Those live in
 * each slice's own feature tests and in the walk that CLAUDE.md's definition
 * of done requires. **Do not read a pass here as "E-whatever is done"** — that
 * is the same shape of overclaim the plan has made seventeen times, and this
 * test exists to end that habit rather than to continue it in a new place.
 *
 * ## Keeping it honest
 *
 * A row whose feature is genuinely removed should fail here, and the fix is to
 * change the plan **and** this list together — never this list alone.
 */
it('still has the code behind every EduPage parity row', function () {
    /** @var array<string, array{routes: list<string>, classes: list<string>}> $rows */
    $rows = [
        'E1 tiles and home' => ['routes' => ['portal.home', 'portal.teacher'], 'classes' => ['ComposePortalHomeAction']],
        'E2 message threads' => ['routes' => ['portal.messages.show', 'portal.messages.reply'], 'classes' => ['Message']],
        'E3 homework reader' => ['routes' => ['portal.homework', 'portal.homework.tick'], 'classes' => []],
        'E4 noticeboard' => ['routes' => [], 'classes' => ['Announcement', 'AnnouncementController']],
        'E5 requests and approvals' => ['routes' => [], 'classes' => ['SchoolRequest', 'ReviewSchoolRequestAction']],
        'E6 forms' => ['routes' => [], 'classes' => ['FormAdminController']],
        'E7 landing and identity' => ['routes' => [], 'classes' => ['ResolveDashboardLandingAction', 'SwitchAccountAction']],
        'E8 timetable' => ['routes' => [], 'classes' => ['Timetable']],
        'E9 absence to substitution' => ['routes' => [], 'classes' => ['RecordApprovedTeacherLeaveAction', 'SubstitutionRequest']],
        'E10 attendance reporting' => ['routes' => [], 'classes' => ['ListClassAttendanceAction']],
        'E11 rooms and calendar' => ['routes' => [], 'classes' => ['RoomBookingController', 'CalendarDayType']],
        'E12 consultation slots' => ['routes' => [], 'classes' => ['MeetingSlotController']],
        'E13 gradebook' => ['routes' => [], 'classes' => ['GradebookController']],
        'E14 report cards' => ['routes' => [], 'classes' => ['ReportCard', 'ReportCardTemplate']],
        'E15 found items' => ['routes' => [], 'classes' => ['FoundItemController']],
        'E16 circulation' => ['routes' => ['circulation.index'], 'classes' => ['CirculationController']],
        'E17 pickup' => ['routes' => ['academics.pickup.index'], 'classes' => ['PickupConsoleController']],
        'E18 gate and movement' => ['routes' => [], 'classes' => ['MovementSource']],
        'E20 competencies' => ['routes' => [], 'classes' => ['CompetencyController']],
        'E21 student work' => ['routes' => ['academics.work.index'], 'classes' => ['StudentWorkController']],
        'E22 notification centre' => ['routes' => [], 'classes' => ['UserNotification', 'NotificationController']],
    ];

    $broken = [];

    foreach ($rows as $row => $needs) {
        foreach ($needs['routes'] as $name) {
            if (! Route::has($name)) {
                $broken[] = $row.' — route '.$name.' is gone';
            }
        }

        foreach ($needs['classes'] as $class) {
            if (! eduPageClassExists($class)) {
                $broken[] = $row.' — class '.$class.' is gone';
            }
        }
    }

    expect($broken)->toBeEmpty(
        "The code behind these parity rows has gone:\n  "
        .implode("\n  ", $broken)
        ."\n\nIf a feature was genuinely removed, update docs/EDUPAGE_FEATURES_PLAN.md and "
        .'this list together — never this list alone. The plan has recorded shipped work '
        .'as missing seventeen times; this test is what stops the eighteenth.'
    );
});

/**
 * Whether a class or enum of this short name is defined anywhere under `app/`.
 *
 * Deliberately name-based rather than FQCN-based: these rows span domains and
 * have been moved between them more than once, and a test that breaks on a
 * legitimate namespace change would train people to edit the list without
 * reading it.
 */
function eduPageClassExists(string $basename): bool
{
    static $sources = null;

    if ($sources === null) {
        $sources = '';
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(base_path('app'), FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $sources .= "\n".file_get_contents($file->getPathname());
            }
        }
    }

    return (bool) preg_match('/\b(?:class|enum|interface)\s+'.preg_quote($basename, '/').'\b/', $sources);
}
