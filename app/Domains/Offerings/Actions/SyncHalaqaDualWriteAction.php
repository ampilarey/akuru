<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Courses\Actions\EnrollUnifiedStudentInOfferingAction;
use App\Domains\Offerings\Models\CourseOffering;
use App\Domains\Offerings\Models\OfferingHalaqaLink;
use App\Support\Contracts\HalaqaReferenceReader;
use Illuminate\Validation\ValidationException;

class SyncHalaqaDualWriteAction
{
    /**
     * @return array{sessions_created: int, enrollments_mirrored: int}
     */
    public function execute(int $offeringId): array
    {
        if (! config('quran.halaqa_dual_write')) {
            throw ValidationException::withMessages([
                'dual_write' => ['Halaqa dual-write is disabled.'],
            ]);
        }

        $offering = CourseOffering::query()->findOrFail($offeringId);
        $link = OfferingHalaqaLink::query()->where('course_offering_id', $offering->id)->first();
        if ($link === null) {
            throw ValidationException::withMessages([
                'halaqa' => ['Link a Hifz program before dual-write sync.'],
            ]);
        }

        $link->dual_write = true;
        $link->save();

        $reader = app(HalaqaReferenceReader::class);
        $created = 0;
        foreach ($reader->listSessions((int) $link->hifz_program_id) as $session) {
            $result = app(MirrorHalaqaSessionAction::class)->execute((int) $session['id'], $offering->id);
            if ($result['created']) {
                $created++;
            }
        }

        $mirrored = 0;
        foreach ($reader->listEnrollments((int) $link->hifz_program_id) as $enrollment) {
            app(EnrollUnifiedStudentInOfferingAction::class)->execute(
                (int) $enrollment['student_id'],
                (int) $offering->course_id,
                (int) $offering->id,
            );
            $mirrored++;
        }

        $link->last_synced_at = now();
        $link->save();

        return [
            'sessions_created' => $created,
            'enrollments_mirrored' => $mirrored,
        ];
    }
}
