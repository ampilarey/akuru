<?php

namespace App\Http\Requests\Hifz;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHifzSessionRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('record') ?? $this->route('session_record');

        return $record && $this->user()->can('update', $record);
    }

    public function rules(): array
    {
        return [
            'attendance_status' => 'nullable|in:present,late,absent,excused',
            'new_from_surah_id' => 'nullable|exists:surahs,id',
            'new_from_ayah' => 'nullable|integer|min:1',
            'new_to_surah_id' => 'nullable|exists:surahs,id',
            'new_to_ayah' => 'nullable|integer|min:1',
            'new_result' => 'nullable|in:pass,pass_with_notes,repeat,not_prepared,not_done',
            'new_score' => 'nullable|integer|min:0|max:100',
            'recent_revision_text' => 'nullable|string|max:2000',
            'recent_revision_result' => 'nullable|in:pass,repeat,not_done',
            'recent_revision_score' => 'nullable|integer|min:0|max:100',
            'old_revision_text' => 'nullable|string|max:2000',
            'old_revision_result' => 'nullable|in:pass,repeat,not_done',
            'old_revision_score' => 'nullable|integer|min:0|max:100',
            'teacher_note' => 'nullable|string|max:5000',
            'parent_visible_note' => 'nullable|string|max:5000',
            'next_target' => 'nullable|string|max:2000',
            'requires_parent_attention' => 'nullable|boolean',
            'requires_supervisor_review' => 'nullable|boolean',
            'overall_status' => 'nullable|in:excellent,good,needs_revision,weak,absent',
        ];
    }
}
