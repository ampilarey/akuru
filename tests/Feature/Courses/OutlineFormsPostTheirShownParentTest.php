<?php

/**
 * The outline editor's "Add lesson" and "Add draft block" forms each carry a
 * parent select whose *displayed* value falls back to the first option, while
 * the form's state was captured at mount — on a new course, before that
 * option existed. The block form learned to send what it shows; the lesson
 * form had not, so the first lesson of every new course failed `required`
 * with no error rendered, and with one module there was nothing to
 * re-select. The author walk found it (1A audit D1, STATUS §5fg).
 *
 * A feature test cannot see this — the controller is right — so the guard
 * reads the source: both submits must resolve the parent id the way the
 * select displays it, and the lesson form must render its errors.
 */
it('sends the parent the select shows, for the lesson form as well as the block form', function () {
    $source = file_get_contents(resource_path('js/Pages/Courses/Catalog/Outline.jsx'));

    expect($source)->toContain("course_module_id: data.course_module_id || modules[0]?.id || ''")
        ->and($source)->toContain("lesson_id: data.lesson_id || modules.flatMap((module) => module.lessons)[0]?.id || ''")
        ->and($source)->toContain('lessonForm.errors.course_module_id');
});
