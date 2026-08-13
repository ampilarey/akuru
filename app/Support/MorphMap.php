<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy polymorphic / class-name column rewrites after the Phase 0 domain move.
 *
 * Two distinct tables:
 * - config/morph-map.php — alias → current FQCN (registered with Eloquent)
 * - legacyMorphRewrites() — old App\Models\* FQCN → alias (backfill only)
 * - legacyNotificationRewrites() — old App\Notifications\* → new FQCN
 *   (notifications.type is NOT a morph column; Laravel stores the class name)
 */
class MorphMap
{
    public const CHUNK_SIZE = 500;

    /**
     * @return array<string, string> old App\Models\* FQCN => morph alias
     */
    public static function legacyMorphRewrites(): array
    {
        return [
            'App\\Models\\AbsenceNote' => 'absence_note',
            'App\\Models\\AcademicYear' => 'academic_year',
            'App\\Models\\AdmissionApplication' => 'admission_application',
            'App\\Models\\Announcement' => 'announcement',
            'App\\Models\\Assignment' => 'assignment',
            'App\\Models\\AssignmentSubmission' => 'assignment_submission',
            'App\\Models\\Attendance' => 'attendance',
            'App\\Models\\ClassRoom' => 'class_room',
            'App\\Models\\ContactInquiry' => 'contact_inquiry',
            'App\\Models\\ContactMessage' => 'contact_message',
            'App\\Models\\Course' => 'course',
            'App\\Models\\CourseCategory' => 'course_category',
            'App\\Models\\CourseEnrollment' => 'course_enrollment',
            'App\\Models\\CoursePlan' => 'course_plan',
            'App\\Models\\DashboardAnalytics' => 'dashboard_analytics',
            'App\\Models\\Device' => 'device',
            'App\\Models\\Event' => 'event',
            'App\\Models\\EventRegistration' => 'event_registration',
            'App\\Models\\Faq' => 'faq',
            'App\\Models\\FeeItem' => 'fee_item',
            'App\\Models\\GalleryAlbum' => 'gallery_album',
            'App\\Models\\GalleryItem' => 'gallery_item',
            'App\\Models\\Grade' => 'grade',
            'App\\Models\\HeroBanner' => 'hero_banner',
            'App\\Models\\HifzAssignment' => 'hifz_assignment',
            'App\\Models\\HifzEnrollment' => 'hifz_enrollment',
            'App\\Models\\HifzMilestone' => 'hifz_milestone',
            'App\\Models\\HifzMistake' => 'hifz_mistake',
            'App\\Models\\HifzProgram' => 'hifz_program',
            'App\\Models\\HifzSession' => 'hifz_session',
            'App\\Models\\HifzSessionRecord' => 'hifz_session_record',
            'App\\Models\\InquiryType' => 'inquiry_type',
            'App\\Models\\Instructor' => 'instructor',
            'App\\Models\\Invoice' => 'invoice',
            'App\\Models\\InvoiceLine' => 'invoice_line',
            'App\\Models\\LessonLog' => 'lesson_log',
            'App\\Models\\MediaGallery' => 'media_gallery',
            'App\\Models\\MediaItem' => 'media_item',
            'App\\Models\\Message' => 'message',
            'App\\Models\\Notification' => 'notification',
            'App\\Models\\NotificationTemplate' => 'notification_template',
            'App\\Models\\Otp' => 'otp',
            'App\\Models\\Page' => 'page',
            'App\\Models\\ParentGuardian' => 'parent_guardian',
            'App\\Models\\Payment' => 'payment',
            'App\\Models\\PaymentItem' => 'payment_item',
            'App\\Models\\Period' => 'period',
            'App\\Models\\PlanTopic' => 'plan_topic',
            'App\\Models\\Post' => 'post',
            'App\\Models\\PostCategory' => 'post_category',
            'App\\Models\\Quiz' => 'quiz',
            'App\\Models\\QuizAttempt' => 'quiz_attempt',
            'App\\Models\\QuizQuestion' => 'quiz_question',
            'App\\Models\\QuranAyah' => 'quran_ayah',
            'App\\Models\\QuranMushaf' => 'quran_mushaf',
            'App\\Models\\QuranPage' => 'quran_page',
            'App\\Models\\QuranProgress' => 'quran_progress',
            'App\\Models\\QuranWord' => 'quran_word',
            'App\\Models\\QuranWordPosition' => 'quran_word_position',
            'App\\Models\\RecitationPractice' => 'recitation_practice',
            'App\\Models\\RegistrationFlow' => 'registration_flow',
            'App\\Models\\RegistrationStudent' => 'registration_student',
            'App\\Models\\Report' => 'report',
            'App\\Models\\School' => 'school',
            'App\\Models\\Setting' => 'setting',
            'App\\Models\\Student' => 'student',
            'App\\Models\\Subject' => 'subject',
            'App\\Models\\SubstitutionAssignment' => 'substitution_assignment',
            'App\\Models\\SubstitutionRequest' => 'substitution_request',
            'App\\Models\\Surah' => 'surah',
            'App\\Models\\SystemMetric' => 'system_metric',
            'App\\Models\\TajweedFeedback' => 'tajweed_feedback',
            'App\\Models\\Teacher' => 'teacher',
            'App\\Models\\TeacherAbsence' => 'teacher_absence',
            'App\\Models\\Testimonial' => 'testimonial',
            'App\\Models\\Timetable' => 'timetable',
            'App\\Models\\User' => 'user',
            'App\\Models\\UserActivity' => 'user_activity',
            'App\\Models\\UserContact' => 'user_contact',
            'App\\Models\\UserNotification' => 'user_notification',
        ];
    }

    /**
     * notifications.type stores a notification class name, not a morph alias.
     *
     * @return array<string, string> old FQCN => new FQCN
     */
    public static function legacyNotificationRewrites(): array
    {
        return [
            'App\\Notifications\\OtpEmailNotification' => 'App\\Domains\\Notifications\\Notifications\\OtpEmailNotification',
            'App\\Notifications\\NewAdmissionApplication' => 'App\\Domains\\Notifications\\Notifications\\NewAdmissionApplication',
            'App\\Notifications\\NewContactMessage' => 'App\\Domains\\Notifications\\Notifications\\NewContactMessage',
        ];
    }

    /**
     * All string rewrites applied by backfill() (morph aliases + notification FQCNs).
     *
     * @return array<string, string>
     */
    public static function allLegacyRewrites(): array
    {
        return self::legacyMorphRewrites() + self::legacyNotificationRewrites();
    }

    /**
     * Rewrite legacy FQCNs in polymorphic / class-name columns.
     * Idempotent; NULL/empty-safe; table-guarded; chunked.
     *
     * @return array<string, int> column key => rows updated
     */
    public static function backfill(int $chunkSize = self::CHUNK_SIZE): array
    {
        $updated = [
            'model_has_roles.model_type' => 0,
            'model_has_permissions.model_type' => 0,
            'payments.payable_type' => 0,
            'notifications.notifiable_type' => 0,
            'notifications.type' => 0,
        ];

        $morph = self::legacyMorphRewrites();

        $updated['model_has_roles.model_type'] = self::rewriteColumn('model_has_roles', 'model_type', $morph, $chunkSize, pk: null);
        $updated['model_has_permissions.model_type'] = self::rewriteColumn('model_has_permissions', 'model_type', $morph, $chunkSize, pk: null);
        $updated['payments.payable_type'] = self::rewriteColumn('payments', 'payable_type', $morph, $chunkSize, pk: 'id');
        $updated['notifications.notifiable_type'] = self::rewriteColumn('notifications', 'notifiable_type', $morph, $chunkSize, pk: 'id');
        $updated['notifications.type'] = self::rewriteColumn('notifications', 'type', self::legacyNotificationRewrites(), $chunkSize, pk: 'id');

        return $updated;
    }

    /**
     * Count rows still holding legacy App\Models\* or App\Notifications\* values.
     *
     * @return array<string, array{count: int, values: list<string>}>
     */
    public static function remainingLegacy(): array
    {
        $targets = [
            'model_has_roles' => ['column' => 'model_type', 'patterns' => ['App\\\\Models\\\\%']],
            'model_has_permissions' => ['column' => 'model_type', 'patterns' => ['App\\\\Models\\\\%']],
            'payments' => ['column' => 'payable_type', 'patterns' => ['App\\\\Models\\\\%']],
            'notifications' => ['column' => 'notifiable_type', 'patterns' => ['App\\\\Models\\\\%']],
        ];

        $report = [];

        foreach ($targets as $table => $meta) {
            $key = "{$table}.{$meta['column']}";
            $report[$key] = self::legacyValueReport($table, $meta['column'], $meta['patterns']);
        }

        $report['notifications.type'] = self::legacyValueReport('notifications', 'type', ['App\\\\Notifications\\\\%']);

        return $report;
    }

    /**
     * @param  array<string, string>  $rewrites
     */
    private static function rewriteColumn(string $table, string $column, array $rewrites, int $chunkSize, ?string $pk): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $total = 0;

        foreach ($rewrites as $old => $new) {
            if ($old === $new) {
                continue;
            }

            if ($pk === null) {
                // Composite-key pivots (Spatie permission tables): UPDATE … LIMIT loop.
                do {
                    $affected = DB::table($table)
                        ->where($column, $old)
                        ->limit($chunkSize)
                        ->update([$column => $new]);
                    $total += $affected;
                } while ($affected > 0);

                continue;
            }

            while (true) {
                $ids = DB::table($table)
                    ->where($column, $old)
                    ->orderBy($pk)
                    ->limit($chunkSize)
                    ->pluck($pk);

                if ($ids->isEmpty()) {
                    break;
                }

                $affected = DB::table($table)
                    ->whereIn($pk, $ids)
                    ->where($column, $old)
                    ->update([$column => $new]);

                $total += $affected;

                if ($affected === 0) {
                    break;
                }
            }
        }

        return $total;
    }

    /**
     * @param  list<string>  $patterns
     * @return array{count: int, values: list<string>}
     */
    private static function legacyValueReport(string $table, string $column, array $patterns): array
    {
        if (! Schema::hasTable($table)) {
            return ['count' => 0, 'values' => []];
        }

        $query = DB::table($table)->where(function ($q) use ($column, $patterns) {
            foreach ($patterns as $i => $pattern) {
                if ($i === 0) {
                    $q->where($column, 'like', $pattern);
                } else {
                    $q->orWhere($column, 'like', $pattern);
                }
            }
        })->whereNotNull($column)->where($column, '!=', '');

        $values = (clone $query)->distinct()->orderBy($column)->pluck($column)->all();

        return [
            'count' => (int) $query->count(),
            'values' => array_values($values),
        ];
    }
}
