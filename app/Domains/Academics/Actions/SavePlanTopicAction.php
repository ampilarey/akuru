<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\CoursePlan;
use App\Domains\Academics\Models\PlanTopic;
use Illuminate\Validation\ValidationException;

class SavePlanTopicAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(CoursePlan $plan, array $data, ?PlanTopic $topic = null): PlanTopic
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Topic title is required.']);
        }

        $order = isset($data['order']) && $data['order'] !== ''
            ? (int) $data['order']
            : ((int) $plan->topics()->max('order') + 1);

        $payload = [
            'course_plan_id' => $plan->id,
            'order' => max(1, $order),
            'title' => $title,
            'objective' => $this->nullableString($data['objective'] ?? null),
            'resources' => $this->nullableString($data['resources'] ?? null),
            'estimated_minutes' => isset($data['estimated_minutes']) && $data['estimated_minutes'] !== ''
                ? (int) $data['estimated_minutes']
                : 45,
            'assessment_notes' => $this->nullableString($data['assessment_notes'] ?? null),
            'is_completed' => (bool) ($data['is_completed'] ?? false),
        ];

        if ($topic === null) {
            return PlanTopic::query()->create($payload);
        }

        $topic->fill($payload);
        $topic->save();

        return $topic->refresh();
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
