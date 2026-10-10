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
                'title_arabic' => $standard->title_arabic,
                'title_dhivehi' => $standard->title_dhivehi,
                'subject_id' => $standard->subject_id,
                'parent_id' => $standard->parent_id,
                'exams_tagged' => $exams->count(),
                'topics_tagged' => $topics->count(),
                'covered' => $exams->count() > 0 || $topics->count() > 0,
            ];
        });
    }

    /**
     * The plans' topics a standard can be tagged against, by plan and order.
     *
     * @return list<array{id: int, title: string, plan_title: string}>
     */
    public function topics(): array
    {
        return DB::table('plan_topics')
            ->join('course_plans', 'course_plans.id', '=', 'plan_topics.course_plan_id')
            ->orderBy('course_plans.title')
            ->orderBy('plan_topics.order')
            ->get(['plan_topics.id', 'plan_topics.title', 'course_plans.title as plan_title'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'title' => (string) $row->title,
                'plan_title' => (string) $row->plan_title,
            ])
            ->all();
    }
}
