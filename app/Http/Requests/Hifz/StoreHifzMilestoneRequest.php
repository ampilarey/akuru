<?php

namespace App\Http\Requests\Hifz;

use Illuminate\Foundation\Http\FormRequest;

class StoreHifzMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recommend_hifz_milestones');
    }

    public function rules(): array
    {
        return [
            'hifz_program_id' => 'required|exists:hifz_programs,id',
            'student_id' => 'required|exists:students,id',
            'type' => 'required|in:surah_completed,juz_completed,page_completed,quran_completed,custom',
            'surah_number' => 'nullable|integer|min:1|max:114',
            'juz_number' => 'nullable|integer|min:1|max:30',
            'page_number' => 'nullable|integer|min:1|max:604',
            'title' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:5000',
        ];
    }
}
