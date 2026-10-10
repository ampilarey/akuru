<?php

namespace App\Http\Requests\Registration;

use App\Domains\Courses\Models\Course;
use Illuminate\Foundation\Http\FormRequest;

class StartRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact_type' => ['required', 'in:mobile,email'],
            'contact_value' => ['required', 'string'],
            'course_id' => ['nullable', 'exists:courses,id'],
            // `course_enrollments.term_id` is a real foreign key (ADR-037). The
            // form carries the term as a hidden field and it used to go into
            // the session unchecked; an invalid one is a form error here, not
            // a foreign-key exception at the OTP step.
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('contact_type');
            $value = $this->input('contact_value');

            // A closed course's old register form still posts here; nothing is started for it (slice RG1a).
            $course = $this->filled('course_id') ? Course::query()->find((int) $this->input('course_id')) : null;
            if ($course !== null && ! $course->is_enrollment_open) {
                $validator->errors()->add('course_id', __('register.error_closed'));
            }

            if (! empty($value)) {
                if ($type === 'mobile') {
                    $digits = preg_replace('/\D/', '', $value);
                    if (strlen($digits) < 7) {
                        $validator->errors()->add('contact_value', __('register.error_phone'));
                    }
                } elseif ($type === 'email') {
                    if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $validator->errors()->add('contact_value', __('register.error_email'));
                    }
                }
            }
        });
    }
}
