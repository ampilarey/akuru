<?php

namespace App\Http\Requests\Registration;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class EnrollAdultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'dob' => ['required', 'date', 'before:today'],
            'gender' => ['nullable', 'in:male,female'],
            'course_ids' => ['required', 'array'],
            'course_ids.*' => ['exists:courses,id'],
            'term_id' => ['nullable', 'integer'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $dob = $this->input('dob');
            if ($dob && Carbon::parse($dob)->age < 18) {
                $validator->errors()->add('dob', 'You must be 18 or older to enroll yourself.');
            }
        });
    }
}
