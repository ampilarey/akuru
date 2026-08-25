<?php

return [
    /*
     * Kill-switch. Default off. The settings row `payroll.enabled` must
     * ALSO be true (AND). Two parallel cycles must match the manual
     * process before either goes on in production (ADR-016 / S5.6).
     */
    'enabled' => filter_var(env('PAYROLL_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
];
