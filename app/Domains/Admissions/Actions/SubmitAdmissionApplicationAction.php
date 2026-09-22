<?php

namespace App\Domains\Admissions\Actions;

use App\Domains\Admissions\Models\AdmissionApplication;
use App\Domains\People\Actions\SaveCustomFieldValuesAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * One public enquiry becomes one `admission_applications` row plus its
 * custom-field values (S1.2), or nothing at all.
 *
 * The custom-field values are validated by the same action the student
 * profile uses, and they need the application's id to be stored against —
 * so the two writes share a transaction: a required field left blank rolls
 * the application back rather than leaving an orphan row that has already
 * emailed every admin.
 *
 * @throws ValidationException
 */
class SubmitAdmissionApplicationAction
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int|string, mixed>  $customValues  keyed by definition id
     */
    public function execute(array $attributes, array $customValues): AdmissionApplication
    {
        return DB::transaction(function () use ($attributes, $customValues): AdmissionApplication {
            $application = AdmissionApplication::query()->create($attributes);

            // The entity type is passed as its string value: another domain's
            // Enums are not a cross-domain surface (rule 3), and the Action
            // accepts the string precisely so callers outside People need not
            // import one.
            app(SaveCustomFieldValuesAction::class)->execute(
                'admission_applications',
                (int) $application->id,
                $customValues,
            );

            return $application;
        });
    }
}
