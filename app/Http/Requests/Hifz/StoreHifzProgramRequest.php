<?php

namespace App\Http\Requests\Hifz;

use Illuminate\Foundation\Http\FormRequest;

class StoreHifzProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage_hifz_programs');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'class_id' => 'nullable|exists:classes,id',
            'dean_id' => 'nullable|exists:users,id',
            'supervisor_id' => 'nullable|exists:users,id',
            'default_teacher_id' => 'nullable|exists:teachers,id',
            'status' => 'nullable|in:active,inactive,completed',
        ];
    }
}
