<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Offerings\Models\CourseOfferingSession;
use App\Domains\Offerings\Models\OfferingHalaqaLink;
use App\Domains\Offerings\Models\OfferingHalaqaSessionLink;
use App\Support\Contracts\HalaqaReferenceReader;
use Illuminate\Validation\ValidationException;

class SaveOfferingHalaqaSessionLinkAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): OfferingHalaqaSessionLink
    {
        $session = CourseOfferingSession::query()->findOrFail((int) $data['course_offering_session_id']);
        $hifzSessionId = (int) ($data['hifz_session_id'] ?? 0);
        $hifzSession = app(HalaqaReferenceReader::class)->findSession($hifzSessionId);
        if ($hifzSession === null) {
            throw ValidationException::withMessages([
                'hifz_session_id' => ['Unknown Hifz session.'],
            ]);
        }

        $programLink = OfferingHalaqaLink::query()
            ->where('course_offering_id', $session->course_offering_id)
            ->first();
        if ($programLink === null || (int) $programLink->hifz_program_id !== (int) $hifzSession['hifz_program_id']) {
            throw ValidationException::withMessages([
                'hifz_session_id' => ['Hifz session must belong to the linked program.'],
            ]);
        }

        $link = OfferingHalaqaSessionLink::query()->firstOrNew([
            'course_offering_session_id' => $session->id,
        ]);
        $link->fill([
            'hifz_session_id' => $hifzSessionId,
            'academic_year_id' => $session->academic_year_id,
        ]);
        $link->save();

        return $link->refresh();
    }
}
