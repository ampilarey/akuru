<?php

namespace App\Support\Authorization;

/**
 * The permission set of every school role, as a decision rather than a
 * seeder's convenience (ADR-040 slice 2 for the educational admin, STATUS
 * §5ie; the whole matrix in §5ij).
 *
 * The owner, 2026-09-27: "system admin: everything related to website,
 * system, shop, bookstore, library; dean: everything related to education;
 * supervisor: related to education; educational admin: everything related
 * to administration of education like fees, students, parents, teachers,
 * supervisors; teacher; parent; student", with Finance and HR as school
 * administration and payroll with the educational admin.
 *
 * `RoleSeeder` and the migration that ships the matrix
 * (`2026_09_27_000004_role_matrix_by_migration`) both read these lists, so
 * a set can only be changed in one place — and changing it needs a new
 * migration, because deployments run `migrate` and never `db:seed`. Each
 * list is *synced*, not added to: what a role stops holding is as much a
 * decision as what it gains. The system admin holds every permission that
 * exists and is not listed here.
 */
final class RoleGrants
{
    /**
     * The **educational admin** (`admin`) runs the school's office:
     * admissions and their payments, the people (students, families, staff),
     * fees, HR and payroll, the noticeboard and messages, the calendar,
     * rooms, meetings and events, the requests families send in, the
     * registers' oversight, and the reports. They *see* the academics
     * (classes, subjects, timetables, grades, attendance, Qur'an progress)
     * and do not run them; Hifz is the dean's, the supervisor's and the
     * teacher's, and the office holds nothing of it. Nothing of the
     * Institute — the website, the shops, the library office, the system —
     * is here; those routes admit `super_admin` alone.
     *
     * @return list<string>
     */
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

    /**
     * The **dean** (`headmaster`): everything education. The people and
     * their records, classes, subjects, timetables and rooms, the registers,
     * behaviour, exams and grades, the course catalogue, the Hifz programmes
     * end to end, the noticeboard and messages, events, meetings, requests,
     * reports — and the fees ("school fees, principal can see") and HR, with
     * payroll run but not approved. Not the website's daily content or
     * prayer times: those are the system admin's since ADR-040 slice 2, and
     * the grants that no route admitted any more are gone (BACKLOG C10).
     *
     * @return list<string>
     */
    public static function dean(): array
    {
        return [
            'view_school',
            'manage_users', 'view_users', 'create_users', 'edit_users',
            'manage_students', 'view_students', 'create_students', 'edit_students',
            'manage_teachers', 'view_teachers', 'create_teachers', 'edit_teachers',
            'manage_classes', 'view_classes', 'create_classes', 'edit_classes',
            'manage_subjects', 'view_subjects', 'create_subjects', 'edit_subjects',
            'manage_timetables', 'view_timetables', 'create_timetables', 'edit_timetables', 'timetables.allow_conflict',
            'view_grades', 'view_attendance', 'view_quran_progress',
            'rooms.manage', 'meetings.manage', 'calendar.manage', 'events.manage',
            'registers.fill', 'registers.manage',
            'messages.broadcast', 'forms.manage',
            'behavior.record', 'behavior.manage',
            'requests.submit', 'requests.review',
            'exams.manage', 'exams.enter-any',
            'finance.manage', 'finance.record-manual-payment',
            'hr.manage', 'payroll.run',
            'courses.manage', 'courses.review',
            'manage_announcements', 'view_announcements', 'create_announcements', 'edit_announcements',
            'view_reports', 'generate_reports',
            'view_hifz_programs', 'manage_hifz_programs', 'assign_hifz_supervisors',
            'create_hifz_sessions', 'update_hifz_sessions', 'review_hifz_sessions', 'lock_hifz_sessions',
            'create_hifz_session_records', 'update_hifz_session_records', 'review_hifz_session_records', 'override_hifz_records',
            'create_hifz_mistakes', 'view_hifz_mistakes',
            'recommend_hifz_milestones', 'review_hifz_milestones', 'approve_hifz_milestones',
            'manage_quran_mushaf', 'approve_quran_mushaf', 'review_quran_mapping',
            'view_hifz_reports', 'export_hifz_reports', 'record_hifz_as_substitute',
        ];
    }

    /**
     * The **supervisor**: academic monitoring — the registers and exams
     * oversight, course reviews and approvals (SPEC §8.4: approving *is*
     * publishing), events, meetings, rooms, requests, the Hifz reviews and
     * reports. Reads the people and the academics. Not the website's daily
     * content or prayer times (as for the dean).
     *
     * @return list<string>
     */
    public static function supervisor(): array
    {
        return [
            'view_school',
            'view_users', 'view_students', 'view_teachers',
            'view_classes', 'view_subjects', 'view_grades', 'view_attendance', 'view_quran_progress', 'view_timetables',
            'rooms.manage', 'meetings.manage', 'calendar.manage', 'events.manage',
            'registers.fill', 'registers.manage',
            'requests.submit', 'requests.review',
            'exams.manage', 'exams.enter-any',
            'courses.manage', 'courses.publish', 'courses.review',
            'view_announcements', 'view_reports',
            'view_hifz_programs',
            'review_hifz_sessions', 'review_hifz_session_records',
            'view_hifz_mistakes',
            'recommend_hifz_milestones', 'review_hifz_milestones',
            'view_hifz_reports', 'export_hifz_reports',
        ];
    }

    /**
     * The **teacher**: their own classes — registers, attendance, grades,
     * Qur'an progress, behaviour notes, broadcasts and forms to their
     * classes, requests, the Hifz sessions and records they teach — and,
     * since C16 slice N6 (OWNER_ACTIONS 16: "teachers mark only their own
     * courses"), the review queue of the courses they are assigned to:
     * `courses.review` without `courses.manage` is that narrowing.
     *
     * @return list<string>
     */
    public static function teacher(): array
    {
        return [
            'view_school',
            'view_students', 'view_classes', 'view_subjects',
            'manage_grades', 'view_grades', 'create_grades', 'edit_grades',
            'manage_attendance', 'view_attendance', 'mark_attendance',
            'manage_quran_progress', 'view_quran_progress', 'update_quran_progress',
            'view_timetables',
            'courses.review',
            'registers.fill',
            'messages.broadcast', 'forms.manage',
            'behavior.record',
            'requests.submit',
            'view_announcements',
            'view_hifz_programs',
            'create_hifz_sessions', 'update_hifz_sessions',
            'create_hifz_session_records', 'update_hifz_session_records',
            'create_hifz_mistakes', 'view_hifz_mistakes',
            'recommend_hifz_milestones',
        ];
    }

    /** The **student**: their own record. @return list<string> */
    public static function student(): array
    {
        return [
            'view_school',
            'view_grades', 'view_attendance', 'view_quran_progress', 'view_timetables',
            'view_announcements',
            'view_hifz_programs', 'view_hifz_mistakes',
        ];
    }

    /** The **parent**: their children's records, and the requests they send in. @return list<string> */
    public static function parent(): array
    {
        return [
            'view_school',
            'view_grades', 'view_attendance', 'view_quran_progress', 'view_timetables',
            'view_announcements',
            'view_hifz_programs', 'view_hifz_mistakes',
            'requests.submit',
        ];
    }

    /**
     * Every role this class decides, keyed by role name.
     *
     * @return array<string, list<string>>
     */
    public static function matrix(): array
    {
        return [
            'admin' => self::educationalAdmin(),
            'headmaster' => self::dean(),
            'supervisor' => self::supervisor(),
            'teacher' => self::teacher(),
            'student' => self::student(),
            'parent' => self::parent(),
        ];
    }
}
