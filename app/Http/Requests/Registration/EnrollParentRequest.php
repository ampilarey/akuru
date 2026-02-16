<?php

namespace App\Http\Requests\Registration;

use Illuminate\Foundation\Http\FormRequest;

class EnrollParentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'course_ids' => ['required', 'array'],
            'course_ids.*' => ['exists:courses,id'],
            'term_id' => ['nullable', 'integer'],
        ];

        if ($this->input('student_mode') === 'new') {
            $rules['first_name'] = ['required', 'string', 'max:100'];
            $rules['last_name'] = ['required', 'string', 'max:100'];
            $rules['dob'] = ['required', 'date', 'before:today'];
            $rules['gender'] = ['nullable', 'in:male,female'];
            $rules['relationship'] = ['nullable', 'in:father,mother,guardian,other'];
        } else {
            $rules['student_id'] = ['required', 'exists:registration_students,id'];
        }

        return $rules;
    }
}
