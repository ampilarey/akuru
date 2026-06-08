<?php

namespace App\Http\Requests\Hifz;

use Illuminate\Foundation\Http\FormRequest;

class StoreHifzMistakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create_hifz_mistakes');
    }

    public function rules(): array
    {
        return [
            'hifz_session_record_id' => 'required|exists:hifz_session_records,id',
            'quran_page_id' => 'nullable|exists:quran_pages,id',
            'quran_ayah_id' => 'nullable|exists:quran_ayahs,id',
            'quran_word_id' => 'nullable|exists:quran_words,id',
            'surah_number' => 'nullable|integer|min:1|max:114',
            'ayah_number' => 'nullable|integer|min:1',
            'word_number' => 'nullable|integer|min:1',
            'mistake_type' => 'required|in:haraka,wrong_letter,wrong_word,skipped_word,extra_word,wrong_ayah,wrong_order,hesitation,weak_fluency,waqf_ibtida,tajweed,other',
            'severity' => 'required|in:minor,medium,major',
            'teacher_note' => 'nullable|string|max:2000',
            'parent_visible' => 'nullable|boolean',
        ];
    }
}
