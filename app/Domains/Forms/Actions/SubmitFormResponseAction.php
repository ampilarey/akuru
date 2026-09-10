<?php

namespace App\Domains\Forms\Actions;

use App\Domains\Academics\Actions\ResolveAudienceContextAction;
use App\Domains\Finance\Actions\RaiseAdHocInvoiceAction;
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
    public function execute(int $formId, int $userId, array $answers, array $roleNames, ?int $studentId = null): FormResponse
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

        $student = app(ResolveResponseStudentAction::class)
            ->execute($form, $userId, $roleNames, $studentId);

        if ($form->hasFee() && $student === null) {
            throw ValidationException::withMessages([
                'student_id' => 'A paid sign-up has to be for a pupil.',
            ]);
        }

        $response = FormResponse::query()->updateOrCreate(
            ['form_id' => $form->id, 'user_id' => $userId],
            [
                'student_id' => $student,
                'academic_year_id' => $form->academic_year_id,
                'answers' => $clean,
                'submitted_at' => now(),
                // Changing the answer withdraws any confirmation: carrying a
                // guardian's approval across to something they never saw is
                // exactly the failure E6b exists to prevent.
                'confirmed_at' => null,
                'confirmed_by_user_id' => null,
            ],
        );

        // Raised once. Re-answering does not bill a family twice, and the
        // existing invoice is left alone rather than cancelled and re-made —
        // a family may already be part-way through paying it.
        if ($form->hasFee() && $response->invoice_id === null && $student !== null) {
            $invoice = app(RaiseAdHocInvoiceAction::class)->execute(
                $student,
                (float) $form->fee_amount,
                $form->title,
                $userId,
                $form->closes_at?->toDateString(),
                $form->academic_year_id,
                ['source' => 'form', 'source_id' => (int) $form->id],
            );

            $response->update(['invoice_id' => $invoice->id]);
        }

        return $response->refresh();
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
