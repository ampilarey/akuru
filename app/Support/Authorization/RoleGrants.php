<?php

namespace App\Support\Authorization;

/**
 * The permission sets that are a product decision rather than a seeder's
 * convenience (ADR-040 slice 2, STATUS §5ie).
 *
 * The owner, 2026-09-27: "system admin: everything related to website,
 * system, shop, bookstore, library; dean: everything related to education;
 * supervisor: related to education; educational admin: everything related
 * to administration of education like fees, students, parents, teachers,
 * supervisors; teacher; parent; student", with Finance and HR as school
 * administration and payroll with the educational admin.
 *
 * The **educational admin** (`admin`) runs the school's office: admissions
 * and their payments, the people (students, families, staff), fees, HR and
 * payroll, the noticeboard and messages, the calendar, rooms, meetings and
 * events, the requests families send in, the registers' oversight, and the
 * reports. They *see* the academics (classes, subjects, timetables, grades,
 * attendance, Qur'an progress, Hifz) and do not run them: marking, exams,
 * the course catalogue, the Hifz programmes and academic setup are the
 * dean's, the supervisor's and the teacher's. Nothing of the Institute —
 * the website, the shops, the library office, the system — is here; those
 * routes admit `super_admin` alone.
 *
 * `RoleSeeder` and the migration that ships the set both read this list, so
 * it can only be changed in one place — and changing it needs a new
 * migration, because deployments run `migrate` and never `db:seed`.
 */
final class RoleGrants
{
    /** @return list<string> */
    public static function educationalAdmin(): array
    {
        return [
            // The school, and its people.
            'view_school',
            'manage_users', 'view_users', 'create_users', 'edit_users',
            'manage_students', 'view_students', 'create_students', 'edit_students', 'delete_students',
            'students.view-sensitive', 'custom_fields.manage',
            'manage_teachers', 'view_teachers', 'create_teachers', 'edit_teachers', 'delete_teachers',

            // The academics, read only: the dean and the supervisor run them.
            'view_classes', 'view_subjects', 'view_timetables',
            'view_grades', 'view_attendance', 'view_quran_progress',
            'view_hifz_programs', 'view_hifz_reports', 'export_hifz_reports',
            'view_reports', 'generate_reports',

            // The day: registers' oversight, the noticeboard, messages, forms,
            // the requests families send in.
            'registers.manage',
            'manage_announcements', 'view_announcements', 'create_announcements', 'edit_announcements', 'delete_announcements',
            'messages.broadcast', 'forms.manage',
            'requests.submit', 'requests.review',

            // The office: calendar, events, rooms, meetings.
            'calendar.manage', 'events.manage', 'rooms.manage', 'meetings.manage',

            // Fees and admissions money (the money endpoints keep their own
            // `can:` gates on top of the role).
            'finance.manage', 'finance.record-manual-payment',
            'payments.record', 'payments.refund',

            // HR and payroll (the owner: payroll with the educational admin).
            'hr.manage', 'payroll.run', 'payroll.approve',
        ];
    }
}
