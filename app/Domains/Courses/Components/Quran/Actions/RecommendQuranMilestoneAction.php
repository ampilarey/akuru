<?php

namespace App\Domains\Courses\Components\Quran\Actions;

use App\Support\Contracts\HalaqaMilestoneWriter;
use App\Support\Contracts\HalaqaReferenceReader;
use Illuminate\Validation\ValidationException;

/**
 * F5-P3 teacher step: recommend a milestone (pending) for a student of a
 * mapped program. Writes go through the HalaqaMilestoneWriter contract —
 * hifz_milestones remains the single store (rule 11) until retirement.
 */
class RecommendQuranMilestoneAction
{
    private const TYPES = ['surah_completed', 'juz_completed', 'page_completed', 'quran_completed', 'custom'];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function execute(array $data): array
    {
        if (! in_array((string) ($data['type'] ?? ''), self::TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'Invalid milestone type.']);
        }

        $programId = (int) ($data['hifz_program_id'] ?? 0);
        if (app(HalaqaReferenceReader::class)->findProgram($programId) === null) {
            throw ValidationException::withMessages(['hifz_program_id' => 'Unknown program.']);
        }

        return app(HalaqaMilestoneWriter::class)->recommend($data);
    }
}
