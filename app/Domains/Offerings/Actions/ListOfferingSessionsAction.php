<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Courses\Actions\ListEnrollmentsForOfferingAction;
use App\Domains\Courses\Actions\ResolveEngineCourseAction;
use App\Domains\Offerings\Models\AttendanceRecord;
use App\Domains\Offerings\Models\CourseOffering;
use App\Domains\Offerings\Models\CourseOfferingSession;
use App\Domains\Offerings\Models\OfferingHalaqaLink;
use App\Domains\Offerings\Models\OfferingHalaqaSessionLink;
use App\Support\Contracts\HalaqaReferenceReader;

class ListOfferingSessionsAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $offeringId): array
    {
        $offering = CourseOffering::query()->findOrFail($offeringId);
        $course = app(ResolveEngineCourseAction::class)->execute($offering->course_id);
        $reader = app(HalaqaReferenceReader::class);
        $halaqa = OfferingHalaqaLink::query()->where('course_offering_id', $offeringId)->first();
        $sessionLinks = OfferingHalaqaSessionLink::query()
            ->whereIn(
                'course_offering_session_id',
                CourseOfferingSession::query()->where('course_offering_id', $offeringId)->pluck('id'),
            )
            ->get()
            ->keyBy('course_offering_session_id');

        return [
            'offering' => [
                'id' => $offering->id,
                'title' => $offering->title,
                'course_title' => $course['title'],
                'delivery_mode' => $offering->delivery_mode?->value ?? $offering->delivery_mode,
            ],
            'types' => array_map(fn ($type) => $type->value, \App\Domains\Offerings\Enums\SessionType::cases()),
            'programs' => $reader->listPrograms(),
            'halaqa' => $halaqa ? [
                'hifz_program_id' => (int) $halaqa->hifz_program_id,
                'program' => $reader->findProgram((int) $halaqa->hifz_program_id),
                'dual_write' => (bool) $halaqa->dual_write,
                'last_synced_at' => $halaqa->last_synced_at?->toIso8601String(),
            ] : null,
            'dual_write_enabled' => (bool) config('quran.halaqa_dual_write'),
            'halaqa_sessions' => $halaqa ? $reader->listSessions((int) $halaqa->hifz_program_id) : [],
            // F1 (A.4b) read switch: engine sessions are authoritative for
            // halaqa-linked offerings; the legacy list above stays for the
            // manual link picker. Unmirrored ids let the UI flag lag without
            // changing existing keys. Gate: php artisan halaqa:verify-mirror.
            'read_source' => 'engine',
            'unmirrored_halaqa_session_ids' => $halaqa
                ? collect($reader->listSessions((int) $halaqa->hifz_program_id))
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->diff($sessionLinks->pluck('hifz_session_id')->map(fn ($id) => (int) $id))
                    ->values()
                    ->all()
                : [],
            'sessions' => CourseOfferingSession::query()
                ->where('course_offering_id', $offeringId)
                ->orderBy('starts_at')
                ->get()
                ->map(fn (CourseOfferingSession $session) => [
                    'id' => $session->id,
                    'title' => $session->title,
                    'session_type' => $session->session_type?->value ?? $session->session_type,
                    'starts_at' => $session->starts_at?->toIso8601String(),
                    'ends_at' => $session->ends_at?->toIso8601String(),
                    'timezone' => $session->timezone,
                    'location_name' => $session->location_name,
                    'online_meeting_url' => $session->online_meeting_url,
                    'teacher_user_id' => $session->teacher_user_id,
                    'is_required' => $session->is_required,
                    'hifz_session_id' => isset($sessionLinks[$session->id])
                        ? (int) $sessionLinks[$session->id]->hifz_session_id
                        : null,
                ])
                ->values()
                ->all(),
            'enrollment_count' => count(app(ListEnrollmentsForOfferingAction::class)->execute($offeringId)),
            'marked_count' => AttendanceRecord::query()
                ->where('course_offering_id', $offeringId)
                ->where('status', '!=', 'pending')
                ->count(),
        ];
    }
}
