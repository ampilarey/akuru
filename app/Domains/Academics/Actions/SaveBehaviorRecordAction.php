<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\BehaviorType;
use App\Domains\Academics\Models\BehaviorRecord;
use App\Domains\Academics\Models\BehaviorRecordAudit;
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
