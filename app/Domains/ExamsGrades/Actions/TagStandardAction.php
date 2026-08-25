<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Models\Exam;
use App\Domains\ExamsGrades\Models\Standard;
use App\Domains\ExamsGrades\Models\StandardTaggable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TagStandardAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): StandardTaggable
    {
        $standard = Standard::query()->find((int) ($data['standard_id'] ?? 0));
        if ($standard === null || ! $standard->active) {
            throw ValidationException::withMessages(['standard_id' => 'Standard not found.']);
        }

        $type = (string) ($data['taggable_type'] ?? '');
        $id = (int) ($data['taggable_id'] ?? 0);
        $alias = match ($type) {
            'exam', Exam::class => 'exam',
            'plan_topic' => 'plan_topic',
            default => null,
        };

        if ($alias === null || $id < 1) {
            throw ValidationException::withMessages(['taggable_type' => 'Tag exams or plan topics only.']);
        }

        if ($alias === 'exam' && ! Exam::query()->where('id', $id)->exists()) {
            throw ValidationException::withMessages(['taggable_id' => 'Exam not found.']);
        }

        if ($alias === 'plan_topic' && ! DB::table('plan_topics')->where('id', $id)->exists()) {
            throw ValidationException::withMessages(['taggable_id' => 'Plan topic not found.']);
        }

        return StandardTaggable::query()->firstOrCreate([
            'standard_id' => $standard->id,
            'taggable_type' => $alias,
            'taggable_id' => $id,
        ]);
    }
}
