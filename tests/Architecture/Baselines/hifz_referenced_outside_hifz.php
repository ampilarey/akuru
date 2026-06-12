<?php

// PHASE_0_CHECKLIST §0.5 rule 5: Hifz referenced outside Hifz domain.
// Baseline may only shrink when violations are fixed — never grow.
// Baseline count: 3

return [
    'app/Domains/People/Models/Student.php',
    'app/Domains/People/Models/Teacher.php',
    'app/Domains/Portal/Http/Controllers/DashboardController.php',
];
