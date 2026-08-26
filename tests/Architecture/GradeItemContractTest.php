<?php

it('defines a grade-item contract and keeps the gradebook subject-ignorant', function () {
    expect(interface_exists(\App\Domains\ExamsGrades\Contracts\GradeItemContract::class))->toBeTrue()
        ->and(interface_exists(\App\Domains\ExamsGrades\Contracts\GradeItemProvider::class))->toBeTrue();

    $src = file_get_contents(base_path('app/Domains/ExamsGrades/Actions/ListGradebookAction.php'));
    expect($src)->not->toContain('course_type')
        ->and($src)->not->toContain('App\\Domains\\Courses\\Models')
        ->and($src)->not->toContain('App\\Domains\\Progress\\Models');
});

it('does not import Courses or Progress models from ExamsGrades', function () {
    $root = base_path('app/Domains/ExamsGrades');
    $violations = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $contents = file_get_contents($file->getPathname());
        if (preg_match('/App\\\\Domains\\\\(Courses|Progress)\\\\Models\\\\/', $contents)) {
            $violations[] = str_replace(base_path().'/', '', $file->getPathname());
        }
    }

    expect($violations)->toBeEmpty();
});
