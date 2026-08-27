<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\BehaviorType;
use App\Domains\Academics\Events\BehaviorRecordLogged;
use App\Domains\Academics\Models\BehaviorRecord;
use App\Domains\Academics\Models\BehaviorRecordAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

class SaveBehaviorRecordAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?BehaviorRecord $record = null, ?int $actorId = null): BehaviorRecord
    {
        $description = trim((string) ($data['description'] ?? ''));
        if ($description === '') {
            throw ValidationException::withMessages(['description' => 'Description is required.']);
        }

        $payload = [
            'student_id' => (int) $data['student_id'],
            'academic_year_id' => (int) $data['academic_year_id'],
            'term_id' => isset($data['term_id']) && $data['term_id'] !== '' && $data['term_id'] !== null
                ? (int) $data['term_id']
                : null,
            'type' => BehaviorType::from((string) $data['type']),
            'category' => trim((string) $data['category']),
            'description' => $description,
            'points' => isset($data['points']) && $data['points'] !== '' && $data['points'] !== null
                ? (int) $data['points']
                : null,
            'date' => $data['date'],
            'recorded_by' => $record?->recorded_by ?? (int) $data['recorded_by'],
            'parent_visible' => array_key_exists('parent_visible', $data) ? (bool) $data['parent_visible'] : true,
            'requires_followup' => (bool) ($data['requires_followup'] ?? false),
            'followup_notes' => trim((string) ($data['followup_notes'] ?? '')) ?: null,
        ];

        if ($payload['category'] === '') {
            throw ValidationException::withMessages(['category' => 'Category is required.']);
        }

        if ($record === null) {
            $created = BehaviorRecord::query()->create($payload);
            $this->audit($created, $actorId ?? $created->recorded_by, 'created', $created->toArray());
            $this->notifyParents($created);

            return $created;
        }

        $before = $record->toArray();
        $record->fill($payload);
        $record->save();
        $this->audit($record, $actorId ?? (int) $record->recorded_by, 'updated', ['before' => $before, 'after' => $record->toArray()]);

        return $record->refresh();
    }

    public function delete(BehaviorRecord $record, int $actorId): void
    {
        $this->audit($record, $actorId, 'deleted', $record->toArray());
        $record->delete();
    }

    /**
     * S2 spec: notify parents of a parent-visible behaviour record, behind an
     * opt-in setting. Gating happens here at dispatch — the same place
     * RecordClassAttendanceAction gates absent-vs-late — so the listener stays
     * a pure sender. Compliments never notify: a "good news" SMS at 9pm is not
     * what the setting is for.
     */
    private function notifyParents(BehaviorRecord $record): void
    {
        if (! $record->parent_visible) {
            return;
        }

        $type = $record->type instanceof BehaviorType ? $record->type : BehaviorType::from((string) $record->type);
        if ($type === BehaviorType::Compliment) {
            return;
        }

        if (! app(ResolveNotificationSettingsAction::class)->execute()['behavior_notify_parents']) {
            return;
        }

        $student = DB::table('students')->where('id', $record->student_id)->first(['first_name', 'last_name']);

        Event::dispatch(new BehaviorRecordLogged(
            recordId: (int) $record->id,
            studentId: (int) $record->student_id,
            studentName: trim((string) ($student->first_name ?? '').' '.(string) ($student->last_name ?? '')),
            type: $type->value,
            date: (string) ($record->date instanceof \DateTimeInterface
                ? $record->date->format('Y-m-d')
                : $record->date),
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function audit(BehaviorRecord $record, int $actorId, string $action, array $payload): void
    {
        BehaviorRecordAudit::query()->create([
            'behavior_record_id' => $record->id,
            'actor_id' => $actorId,
            'action' => $action,
            'payload' => $payload,
        ]);
    }
}
