<?php

// What each role is called where a person reads it (ADR-040 slice 3, STATUS
// §5if). The keys are the role keys in the database, which do not change;
// `App\Support\Authorization\RoleLabels` reads this file and falls back to
// the humanised key for a role it does not know.
return [
    'super_admin' => 'System admin',
    'admin' => 'Educational admin',
    'headmaster' => 'Dean',
    'supervisor' => 'Supervisor',
    'teacher' => 'Teacher',
    'student' => 'Student',
    'parent' => 'Parent',
    'course_creator' => 'Course creator',
    'writer' => 'Writer',
    'reviewer' => 'Reviewer',
    'vendor' => 'Vendor',
    'bookshop_manager' => 'Bookstore admin',
    'none' => 'No role',
];
