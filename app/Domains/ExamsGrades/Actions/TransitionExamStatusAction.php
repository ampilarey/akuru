<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Enums\ExamStatus;
use App\Domains\ExamsGrades\Events\ExamResultsPublished;
use App\Domains\ExamsGrades\Models\Exam;
use App\Domains\ExamsGrades\Models\ExamStatusAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionExamStatusAction
{
    public function execute(Exam $exam, ExamStatus $to, int $actorId, ?string $reason = null): Exam
    {
        $from = $exam->status;

        if ($from === $to) {
            return $exam;
        }

        $isUnlock = $from === ExamStatus::Locked && $to !== ExamStatus::Locked;
        if ($isUnlock) {
            $note = trim((string) $reason);
            if ($note === '') {
                throw ValidationException::withMessages(['reason' => 'Unlock requires a reason.']);
            }
        } elseif (! in_array($to, $from->allowedNext(), true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot move from {$from->value} to {$to->value}.",
            ]);
        }

        return DB::transaction(function () use ($exam, $from, $to, $actorId, $reason, $isUnlock): Exam {
            $exam->status = $to;
            if ($to === ExamStatus::Published) {
                $exam->published_at = now();
            }
            $exam->save();

            ExamStatusAudit::query()->create([
                'exam_id' => $exam->id,
                'from_status' => $from,
                'to_status' => $to,
                'actor_id' => $actorId,
                'reason' => $isUnlock ? trim((string) $reason) : $this->nullable($reason),
            ]);

            if ($to === ExamStatus::Published) {
                event(new ExamResultsPublished(
                    $exam->id,
                    $exam->class_id,
                    $exam->name,
                    $exam->exam_date?->toDateString(),
                ));
            }

            return $exam->refresh();
        });
    }

    private function nullable(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
