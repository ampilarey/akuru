<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Offerings\Models\CourseOffering;
use App\Domains\Offerings\Models\OfferingHalaqaLink;
use App\Support\Contracts\HalaqaReferenceReader;
use Illuminate\Validation\ValidationException;

class SaveOfferingHalaqaLinkAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): OfferingHalaqaLink
    {
        $offering = CourseOffering::query()->findOrFail((int) $data['course_offering_id']);
        $programId = (int) ($data['hifz_program_id'] ?? 0);
        $program = app(HalaqaReferenceReader::class)->findProgram($programId);
        if ($program === null) {
            throw ValidationException::withMessages([
                'hifz_program_id' => ['Unknown Hifz program.'],
            ]);
        }

        $link = OfferingHalaqaLink::query()->firstOrNew([
            'course_offering_id' => $offering->id,
        ]);
        $link->fill([
            'hifz_program_id' => $programId,
            'academic_year_id' => $offering->academic_year_id,
        ]);
        $link->save();

        return $link->refresh();
    }
}
