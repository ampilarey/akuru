<?php

namespace App\Domains\HR\Actions;

use App\Domains\Academics\Actions\ListClassesForYearAction;
use App\Domains\Academics\Actions\ListSubjectsAction;
use App\Domains\HR\Models\LessonObservation;
use App\Domains\People\Actions\ListStaffProfilesAction;
use Illuminate\Support\Collection;

class ListLessonObservationsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(?int $staffProfileId = null): Collection
    {
        $staff = app(ListStaffProfilesAction::class)->execute()->keyBy('id');
        $classes = app(ListClassesForYearAction::class)->execute()->keyBy('id');
        $subjects = app(ListSubjectsAction::class)->execute()->keyBy('id');

        return LessonObservation::query()
            ->when($staffProfileId, fn ($query) => $query->where('staff_profile_id', $staffProfileId))
            ->orderByDesc('date')
            ->get()
            ->map(function (LessonObservation $row) use ($staff, $classes, $subjects): array {
                $profile = $staff->get($row->staff_profile_id);
                $class = $row->class_id ? $classes->get($row->class_id) : null;
                $subject = $row->subject_id ? $subjects->get($row->subject_id) : null;

                return [
                    'id' => $row->id,
                    'staff_profile_id' => $row->staff_profile_id,
                    'staff_name' => $profile ? trim(($profile->first_name ?? '').' '.($profile->last_name ?? '')) : '',
                    'date' => $row->date?->toDateString(),
                    'class_id' => $row->class_id,
                    'class_name' => is_array($class) ? ($class['label'] ?? null) : null,
                    'subject_id' => $row->subject_id,
                    'subject_name' => is_array($subject) ? ($subject['name'] ?? null) : null,
                    'summary' => $row->summary,
                    'shared_with_staff' => $row->shared_with_staff,
                ];
            })
            ->values();
    }
}
