<?php

it('hifz route names are registered', function () {
    $names = [
        'hifz.hub',
        'hifz.teacher.dashboard',
        'hifz.student.dashboard',
        'hifz.programs.index',
        'hifz.sessions.index',
    ];

    foreach ($names as $name) {
        expect(route($name, absolute: false))->not->toBeEmpty();
    }
});
