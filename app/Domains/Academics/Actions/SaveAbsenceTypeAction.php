<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\AbsenceNote;
use App\Domains\Academics\Models\AbsenceType;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Add or change an absence reason.
 *
 * **The code never changes once a note has used it.** A code is what old notes
 * point at; rewriting it would silently restate why a child was away last term.
 * The display name may be edited freely — that is the part a school actually
 * wants to reword.
 *
 * A type in use cannot be deleted either, only deactivated. There is no delete
 * action in this domain for that reason.
 */
class SaveAbsenceTypeAction
{
    public function execute(array $data, ?AbsenceType $type = null): AbsenceType
    {
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Give the reason a name families will recognise.']);
        }

        $attributes = [
            'name' => $name,
            'name_dhivehi' => $this->nullable($data['name_dhivehi'] ?? null),
            'name_arabic' => $this->nullable($data['name_arabic'] ?? null),
            'excuses_absence' => (bool) ($data['excuses_absence'] ?? true),
            'requires_evidence' => (bool) ($data['requires_evidence'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => array_key_exists('sort_order', $data)
                ? (int) $data['sort_order']
                // A new reason goes to the end of the list. Defaulting to 0
                // spliced it between the seeded ones, which the browser walk
                // showed as "Illness, Unauthorised holiday, Medical
                // appointment" — an order nobody chose.
                : (int) (AbsenceType::query()->max('sort_order') ?? -1) + 1,
        ];

        if ($type !== null) {
            if (($data['code'] ?? $type->code) !== $type->code
                && AbsenceNote::query()->where('absence_type_id', $type->id)->exists()) {
                throw ValidationException::withMessages([
                    'code' => 'This reason is already on notes, so its code cannot change. Edit the name instead.',
                ]);
            }

            $type->update($attributes);

            return $type->refresh();
        }

        $code = Str::slug((string) ($data['code'] ?? $name), '_');

        if ($code === '') {
            throw ValidationException::withMessages(['code' => 'That name cannot be turned into a code.']);
        }

        if (AbsenceType::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['code' => 'A reason with that code already exists.']);
        }

        return AbsenceType::query()->create($attributes + ['code' => $code]);
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
