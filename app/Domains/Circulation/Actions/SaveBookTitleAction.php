<?php

namespace App\Domains\Circulation\Actions;

use App\Domains\Circulation\Models\BookTitle;
use Illuminate\Validation\ValidationException;

class SaveBookTitleAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?BookTitle $title = null): BookTitle
    {
        $name = trim((string) ($data['title'] ?? ''));

        if ($name === '') {
            throw ValidationException::withMessages(['title' => 'A book needs a title.']);
        }

        $loanDays = (int) ($data['loan_days'] ?? 14);

        $attributes = [
            'title' => $name,
            'author' => $this->nullable($data['author'] ?? null),
            'isbn' => $this->nullable($data['isbn'] ?? null),
            'classification' => $this->nullable($data['classification'] ?? null),
            'language' => trim((string) ($data['language'] ?? 'en')) ?: 'en',
            'loan_days' => $loanDays > 0 ? $loanDays : 14,
            'notes' => $this->nullable($data['notes'] ?? null),
        ];

        if ($title !== null) {
            $title->update($attributes);

            return $title->refresh();
        }

        return BookTitle::query()->create($attributes);
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
