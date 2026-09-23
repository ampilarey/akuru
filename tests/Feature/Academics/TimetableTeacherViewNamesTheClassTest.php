<?php

use App\Domains\Academics\Actions\SaveTimetableEntryAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The teacher and room views of the builder list one person's or one room's
 * week across every class. Until the timetable walk (STATUS §5fs) a cell
 * there showed the subject and the teacher and no class at all, so a teacher
 * double-booked across two classes read as the same lesson twice. The entry
 * carries its class; the two views now print it.
 */
it('serves each entry with its class, and the builder prints it outside the class view', function () {
    $admin = actingPeopleAdmin(['manage_timetables']);
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year, 'Grade 3', 'B');
    $teacher = makeTeacherRow();

    app(SaveTimetableEntryAction::class)->execute([
        'class_id' => $class->id,
        'subject_id' => makeSubject()->id,
        'teacher_id' => $teacher->id,
        'academic_year_id' => $year->id,
        'day_of_week' => 'monday',
        'period_id' => makePeriodRow()->id,
        'room_id' => makeRoomRow()->id,
        'is_active' => true,
    ]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get('/academics/timetable?academic_year_id='.$year->id.'&view=teacher&teacher_id='.$teacher->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Academics/Timetable/Builder')
            ->where('view', 'teacher')
            ->where('entries.0.class_id', $class->id));

    $source = file_get_contents(resource_path('js/Pages/Academics/Timetable/Builder.jsx'));

    expect($source)->toContain("{view !== 'class' && <div>{className(entry.class_id)}</div>}");
});
