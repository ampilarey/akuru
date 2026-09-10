<?php

namespace App\Domains\Forms\Actions;

use App\Domains\Academics\Actions\ResolveAudienceContextAction;
use App\Domains\Forms\Enums\FormFieldType;
use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Models\FormResponse;
use Illuminate\Validation\ValidationException;

/**
 * One person answers a form.
 *
 * Answers are validated against the form's own frozen field list, so a
 * hand-posted key or an option that was never offered cannot land in the
 * results table where somebody would act on it.
 */
class SubmitFormResponseAction
{
    /**
     * @param  array<string, mixed>  $answers
     * @param  list<string>  $roleNames
     */
    public function execute(int $formId, int $userId, array $answers, array $roleNames): FormResponse
    {
        $form = Form::query()->findOrFail($formId);

        $matcher = app(ResolveAudienceContextAction::class);
        if (! $matcher->matches($form->target_audience, $form->target_classes, $matcher->execute($userId, $roleNames))) {
            throw ValidationException::withMessages([
                'form' => 'This form is not for you.',
            ]);
        }

        if (! $form->isOpen()) {
            throw ValidationException::withMessages([
                'form' => 'This form is closed.',
            ]);
        }

        $clean = $this->validateAnswers($form, $answers);

        // Anonymous means no person id is stored at all, and therefore no way
        // to spot a second submission — that is the cost of the promise, and
        // pretending otherwise would make the anonymity fake.
        if ($form->is_anonymous) {
            return FormResponse::query()->create([
                'form_id' => $form->id,
                'user_id' => null,
                'academic_year_id' => $form->academic_year_id,
                'answers' => $clean,
                'submitted_at' => now(),
            ]);
        }

        return FormResponse::query()->updateOrCreate(
            ['form_id' => $form->id, 'user_id' => $userId],
            [
                'academic_year_id' => $form->academic_year_id,
                'answers' => $clean,
                'submitted_at' => now(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    private function validateAnswers(Form $form, array $answers): array
    {
        $clean = [];
        $errors = [];

        foreach ($form->fields ?? [] as $field) {
            $key = (string) $field['key'];
            $type = FormFieldType::tryFrom((string) $field['type']) ?? FormFieldType::Text;
            $given = $answers[$key] ?? null;

            $value = match ($type) {
                FormFieldType::MultiSelect => array_values(array_filter(
                    array_map(fn ($v): string => (string) $v, is_array($given) ? $given : []),
                    fn (string $v): bool => in_array($v, $field['options'] ?? [], true),
                )),
                FormFieldType::Select => in_array((string) $given, $field['options'] ?? [], true)
                    ? (string) $given
                    : null,
                FormFieldType::YesNo => in_array($given, ['yes', 'no', true, false], true)
                    ? (($given === 'yes' || $given === true) ? 'yes' : 'no')
                    : null,
                default => trim((string) ($given ?? '')) === '' ? null : trim((string) $given),
            };

            $isEmpty = $value === null || $value === [] || $value === '';

            if (($field['required'] ?? false) && $isEmpty) {
                $errors["answers.{$key}"] = "“{$field['label']}” is required.";
            }

            $clean[$key] = $value;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $clean;
    }
}
