<?php

// PHASE_0_CHECKLIST §0.5 rule 4: DB:: in domain controllers.
// Baseline may only shrink when violations are fixed — never grow.
// Baseline count: 4

return [
    'app/Domains/Admissions/Http/Controllers/CheckoutController.php',
    'app/Domains/Finance/Http/Controllers/PaymentController.php',
    'app/Domains/Identity/Http/Controllers/AdminUserController.php',
    'app/Domains/Portal/Http/Controllers/DashboardController.php',
];
