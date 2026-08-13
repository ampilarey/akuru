<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy polymorphic / class-name column rewrites after the Phase 0 domain move.
 *
 * Two distinct tables:
 * - config/morph-map.php — alias → current FQCN (registered with Eloquent)
 * - morphRewrites() — App\Models\* AND App\Domains\*\Models\* FQCN → alias
 *   (backfill only; domain side is array_flip of the config so they cannot drift)
 * - legacyNotificationRewrites() — old App\Notifications\* → new FQCN
 *   (notifications.type is NOT a morph column; Laravel stores the class name)
 *
 * Staging has a mixed-era dataset: pre-Phase-0 rows hold App\Models\*, post-Phase-0
 * rows (written with no morph map) hold App\Domains\* FQCNs. Both must rewrite.
 */
class MorphMap
{
    public const CHUNK_SIZE = 500;

    public const REPORT_PATH = 'morph-map-backfill-report.json';

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
     * Full morph rewrite map: App\Models\* and App\Domains\*\Models\* → alias.
     * Domain side is derived from config/morph-map.php so it cannot drift.
     *
     * @return array<string, string>
     */
    public static function morphRewrites(): array
    {
        return self::legacyMorphRewrites() + array_flip(config('morph-map', []));
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
     * Known-good notifications.type values after backfill.
     *
     * @return list<string>
     */
    public static function knownGoodNotificationTypes(): array
    {
        return array_values(self::legacyNotificationRewrites());
    }

    /**
     * All string rewrites applied by backfill() (morph aliases + notification FQCNs).
     *
     * @return array<string, string>
     */
    public static function allLegacyRewrites(): array
    {
        return self::morphRewrites() + self::legacyNotificationRewrites();
    }

    /**
     * Collapse duplicate permission pivots, then rewrite FQCNs.
     * Idempotent; NULL/empty-safe; table-guarded; chunked; transactional.
     *
     * @return array{
     *     updated: array<string, int>,
     *     collapses: list<array<string, mixed>>,
     *     collapse_counts: array<string, int>
     * }
     */
    public static function backfill(int $chunkSize = self::CHUNK_SIZE): array
    {
        return DB::transaction(function () use ($chunkSize) {
            $collapses = [];
            $collapses = array_merge(
                $collapses,
                self::collapseCompositeMorphPivots('model_has_roles', 'role_id')
            );
            $collapses = array_merge(
                $collapses,
                self::collapseCompositeMorphPivots('model_has_permissions', 'permission_id')
            );

            $morph = self::morphRewrites();

            $updated = [
                'model_has_roles.model_type' => self::rewriteColumn('model_has_roles', 'model_type', $morph, $chunkSize, pk: null),
                'model_has_permissions.model_type' => self::rewriteColumn('model_has_permissions', 'model_type', $morph, $chunkSize, pk: null),
                'payments.payable_type' => self::rewriteColumn('payments', 'payable_type', $morph, $chunkSize, pk: 'id'),
                'notifications.notifiable_type' => self::rewriteColumn('notifications', 'notifiable_type', $morph, $chunkSize, pk: 'id'),
                'notifications.type' => self::rewriteColumn('notifications', 'type', self::legacyNotificationRewrites(), $chunkSize, pk: 'id'),
            ];

            $collapseCounts = [];
            foreach ($collapses as $collapse) {
                $table = $collapse['table'];
                $collapseCounts[$table] = ($collapseCounts[$table] ?? 0) + 1;
            }

            $report = [
                'updated' => $updated,
                'collapses' => $collapses,
                'collapse_counts' => $collapseCounts,
            ];

            self::writeReport($report);

            return $report;
        });
    }

    /**
     * Remaining bad values after backfill.
     *
     * Morph columns: any value containing a backslash is wrong (no-FQCN invariant).
     * notifications.type: old App\Notifications\* or unexpected App\Domains\* notification classes.
     *
     * @return array<string, array{count: int, values: list<string>}>
     */
    public static function remainingLegacy(): array
    {
        $report = [];

        foreach ([
            'model_has_roles.model_type' => ['model_has_roles', 'model_type'],
            'model_has_permissions.model_type' => ['model_has_permissions', 'model_type'],
            'payments.payable_type' => ['payments', 'payable_type'],
            'notifications.notifiable_type' => ['notifications', 'notifiable_type'],
        ] as $key => [$table, $column]) {
            $report[$key] = self::backslashValueReport($table, $column);
        }

        $report['notifications.type'] = self::badNotificationTypeReport();

        return $report;
    }

    /**
     * @return array{updated: array<string, int>, collapses: list<array<string, mixed>>, collapse_counts: array<string, int>}|null
     */
    public static function lastReport(): ?array
    {
        $path = storage_path('app/'.self::REPORT_PATH);
        if (! is_file($path)) {
            return null;
        }

        /** @var array{updated: array<string, int>, collapses: list<array<string, mixed>>, collapse_counts: array<string, int>}|null $decoded */
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Collapse rows that would violate the composite PK after model_type normalization.
     *
     * @return list<array<string, mixed>>
     */
    private static function collapseCompositeMorphPivots(string $table, string $pivotKey): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $rewrites = self::morphRewrites();
        $collapses = [];

        $rows = DB::table($table)
            ->orderBy($pivotKey)
            ->orderBy('model_id')
            ->orderBy('model_type')
            ->get([$pivotKey, 'model_id', 'model_type']);

        /** @var array<string, list<object>> $groups */
        $groups = [];
        foreach ($rows as $row) {
            $target = $rewrites[$row->model_type] ?? $row->model_type;
            $groupKey = $row->{$pivotKey}.'|'.$row->model_id.'|'.$target;
            $groups[$groupKey][] = $row;
        }

        foreach ($groups as $groupKey => $group) {
            if (count($group) < 2) {
                continue;
            }

            $target = explode('|', $groupKey)[2];

            usort($group, function ($a, $b) use ($target) {
                return ((int) ($b->model_type === $target)) <=> ((int) ($a->model_type === $target));
            });

            $keep = array_shift($group);
            $dropped = [];

            foreach ($group as $duplicate) {
                DB::table($table)
                    ->where($pivotKey, $duplicate->{$pivotKey})
                    ->where('model_id', $duplicate->model_id)
                    ->where('model_type', $duplicate->model_type)
                    ->delete();

                $dropped[] = $duplicate->model_type;
            }

            $collapses[] = [
                'table' => $table,
                $pivotKey => $keep->{$pivotKey},
                'model_id' => $keep->model_id,
                'target_alias' => $target,
                'kept_model_type' => $keep->model_type,
                'dropped_model_types' => $dropped,
            ];
        }

        return $collapses;
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
     * Morph-column invariant: any value containing "\" is a raw FQCN and is wrong.
     *
     * @return array{count: int, values: list<string>}
     */
    private static function backslashValueReport(string $table, string $column): array
    {
        if (! Schema::hasTable($table)) {
            return ['count' => 0, 'values' => []];
        }

        $query = DB::table($table)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->whereRaw('LOCATE(?, '.$column.') > 0', ['\\']);

        $values = (clone $query)->distinct()->orderBy($column)->pluck($column)->all();

        return [
            'count' => (int) $query->count(),
            'values' => array_values($values),
        ];
    }

    /**
     * @return array{count: int, values: list<string>}
     */
    private static function badNotificationTypeReport(): array
    {
        if (! Schema::hasTable('notifications')) {
            return ['count' => 0, 'values' => []];
        }

        $knownGood = self::knownGoodNotificationTypes();

        $values = DB::table('notifications')
            ->whereNotNull('type')
            ->where('type', '!=', '')
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->filter(function (string $type) use ($knownGood) {
                if (str_starts_with($type, 'App\\Notifications\\')) {
                    return true;
                }

                if (str_contains($type, '\\Notifications\\') && str_starts_with($type, 'App\\Domains\\')) {
                    return ! in_array($type, $knownGood, true);
                }

                return false;
            })
            ->values()
            ->all();

        if ($values === []) {
            return ['count' => 0, 'values' => []];
        }

        $count = (int) DB::table('notifications')->whereIn('type', $values)->count();

        return [
            'count' => $count,
            'values' => $values,
        ];
    }

    /**
     * @param  array{updated: array<string, int>, collapses: list<array<string, mixed>>, collapse_counts: array<string, int>}  $report
     */
    private static function writeReport(array $report): void
    {
        $dir = storage_path('app');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $dir.'/'.self::REPORT_PATH,
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );
    }
}
