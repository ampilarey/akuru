<?php

/**
 * CLAUDE.md rule 3, Components clause — enforced from F0 (Phase F), when
 * Courses/Components/ first came to exist. Before F0 this clause guarded an
 * empty set (Phase 2 audit).
 *
 * Rules:
 *  1. Components never import each other (Arabic ↔ Quran).
 *  2. Components never import engine Models directly — engine access goes
 *     through Courses\Actions (the F0 seams: ListSkillTaggedActivitiesAction,
 *     ResolveLatestEnrollmentIdAction) or Support contracts.
 *
 * Known, permitted residue (recorded in ROADMAP §2a as-built note): the ENGINE
 * references component actions (SaveActivityAction validates Arabic/Quran
 * settings; ListCourseActivitiesAction resolves Quran passages). Inverting
 * that dependency is a Phase F follow-up, not part of relocation.
 */
$componentFiles = function (string $component): array {
    $base = dirname(__DIR__, 2).'/app/Domains/Courses/Components/'.$component;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
    $files = [];
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[str_replace(dirname(__DIR__, 2).'/', '', $file->getPathname())] = file_get_contents($file->getPathname());
        }
    }

    return $files;
};

it('components never import each other', function () use ($componentFiles) {
    $violations = [];

    foreach ($componentFiles('Arabic') as $path => $source) {
        if (str_contains($source, 'App\\Domains\\Courses\\Components\\Quran')) {
            $violations[] = $path.' -> Components\\Quran';
        }
    }
    foreach ($componentFiles('Quran') as $path => $source) {
        if (str_contains($source, 'App\\Domains\\Courses\\Components\\Arabic')) {
            $violations[] = $path.' -> Components\\Arabic';
        }
    }

    expect($violations)->toBeEmpty(
        "Components must stay isolated from each other:\n".implode("\n", $violations)
    );
});

it('components never import engine models directly', function () use ($componentFiles) {
    $violations = [];

    foreach (['Arabic', 'Quran'] as $component) {
        foreach ($componentFiles($component) as $path => $source) {
            if (preg_match_all('/App\\\\Domains\\\\Courses\\\\Models\\\\(\w+)/', $source, $matches)) {
                foreach (array_unique($matches[1]) as $model) {
                    $violations[] = $path.' -> Courses\\Models\\'.$model;
                }
            }
        }
    }

    expect($violations)->toBeEmpty(
        "Components must use engine Actions/contracts, never engine Models (rule 3):\n".implode("\n", $violations)
    );
});
