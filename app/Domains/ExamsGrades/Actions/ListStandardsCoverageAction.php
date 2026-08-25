<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Models\Standard;
use App\Domains\ExamsGrades\Models\StandardTaggable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListStandardsCoverageAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(?int $subjectId = null, ?int $termId = null): Collection
    {
        $standards = Standard::query()
            ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId))
            ->where('active', true)
            ->orderBy('code')
            ->get();

        $examIdsInTerm = $termId
            ? DB::table('exams')->where('term_id', $termId)->pluck('id')
            : collect();

        $topicIdsInTerm = $termId
            ? DB::table('plan_topics')
                ->join('course_plans', 'course_plans.id', '=', 'plan_topics.course_plan_id')
                ->when(
                    DB::getSchemaBuilder()->hasColumn('course_plans', 'term_id'),
                    fn ($query) => $query->where('course_plans.term_id', $termId),
                )
                ->pluck('plan_topics.id')
            : collect();

        $tags = StandardTaggable::query()
            ->whereIn('standard_id', $standards->pluck('id')->all() ?: [0])
            ->get()
            ->groupBy('standard_id');

        return $standards->map(function (Standard $standard) use ($tags, $termId, $examIdsInTerm, $topicIdsInTerm) {
            $group = $tags[$standard->id] ?? collect();
            $exams = $group->where('taggable_type', 'exam');
            $topics = $group->where('taggable_type', 'plan_topic');
            if ($termId) {
                $exams = $exams->whereIn('taggable_id', $examIdsInTerm);
                $topics = $topics->whereIn('taggable_id', $topicIdsInTerm);
            }

            return [
                'id' => $standard->id,
                'code' => $standard->code,
                'title' => $standard->title,
                'subject_id' => $standard->subject_id,
                'parent_id' => $standard->parent_id,
                'exams_tagged' => $exams->count(),
                'topics_tagged' => $topics->count(),
                'covered' => $exams->count() > 0 || $topics->count() > 0,
            ];
        });
    }
}
