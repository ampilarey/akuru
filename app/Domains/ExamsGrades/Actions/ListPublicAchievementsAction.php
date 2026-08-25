<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Enums\AwardLevel;
use App\Domains\ExamsGrades\Models\StudentAward;
use App\Domains\People\Actions\HasActiveConsentAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListPublicAchievementsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(): Collection
    {
        $rows = StudentAward::query()
            ->with('award')
            ->whereHas('award', fn ($query) => $query->where('level', AwardLevel::School)->where('active', true))
            ->orderByDesc('awarded_date')
            ->get();

        $students = DB::table('students')
            ->whereIn('id', $rows->pluck('student_id')->unique())
            ->get(['id', 'first_name', 'last_name'])
            ->keyBy('id');

        $consent = app(HasActiveConsentAction::class);

        return $rows->map(function (StudentAward $row) use ($students, $consent) {
            $student = $students[$row->student_id] ?? null;
            $showPhoto = $consent->execute('student', (int) $row->student_id, 'photo_media_use');
            $photo = $showPhoto
                ? DB::table('documents')
                    ->where('documentable_type', 'student')
                    ->where('documentable_id', $row->student_id)
                    ->where('document_type', 'photo')
                    ->orderByDesc('id')
                    ->value('media_path')
                : null;

            return [
                'id' => $row->id,
                'title' => $row->award?->title,
                'student_name' => trim(($student->first_name ?? '').' '.($student->last_name ?? '')),
                'awarded_date' => $row->awarded_date?->toDateString(),
                'photo' => $photo,
                'photo_allowed' => $showPhoto,
            ];
        })->values();
    }
}
