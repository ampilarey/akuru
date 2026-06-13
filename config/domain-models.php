<?php

/**
 * Cross-domain Eloquent class names for legacy relationships on Course.
 * Keeps FQCNs out of Courses\Models (architecture baseline rule 1).
 */
return [
    'admission_application' => \App\Domains\Admissions\Models\AdmissionApplication::class,
    'instructor' => \App\Domains\HR\Models\Instructor::class,
];
