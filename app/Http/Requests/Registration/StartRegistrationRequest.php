<?php

namespace App\Http\Requests\Registration;

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
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('contact_type');
            $value = $this->input('contact_value');
            if ($type === 'mobile') {
                $digits = preg_replace('/\D/', '', $value);
                if (strlen($digits) < 7) {
                    $validator->errors()->add('contact_value', 'Please enter a valid phone number.');
                }
            } elseif ($type === 'email') {
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $validator->errors()->add('contact_value', 'Please enter a valid email address.');
                }
            }
        });
    }
}
