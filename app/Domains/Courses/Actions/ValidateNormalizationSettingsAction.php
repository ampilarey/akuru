<?php

namespace App\Domains\Courses\Actions;

use Illuminate\Validation\ValidationException;

class ValidateNormalizationSettingsAction
{
    /**
     * SPEC §18 says comparison "must be configurable per activity". It was
     * configurable, but nothing checked what was configured:
     * `CatalogQuestionController` read `normalization_settings` straight from
     * the request and `SaveQuestionAction` stored it whenever it was an array.
     *
     * A misspelled switch is the case that matters. `strict_tashkeel` instead
     * of `strip_tashkeel` was accepted, saved, and then ignored by the
     * normalizer — the question scored leniently while its settings said
     * otherwise, and nothing anywhere reported a problem. For an Arabic
     * diacritics question that is a wrong mark, not a cosmetic issue.
     *
     * @param  mixed  $settings
     * @return array<string, mixed>|null
     *
     * @throws ValidationException
     */
    public function execute($settings): ?array
    {
        if ($settings === null || $settings === '' || $settings === []) {
            return null;
        }

        if (! is_array($settings)) {
            throw ValidationException::withMessages([
                'normalization_settings' => 'Normalization settings must be an object.',
            ]);
        }

        $flags = NormalizeTextAnswerAction::flags();
        $modes = NormalizeTextAnswerAction::modes();
        $clean = [];

        foreach ($settings as $key => $value) {
            if ($key === 'mode') {
                if (! in_array($value, $modes, true)) {
                    throw ValidationException::withMessages([
                        'normalization_settings' => 'Comparison mode must be one of: '.implode(', ', $modes).'.',
                    ]);
                }
                $clean['mode'] = $value;

                continue;
            }

            if (! in_array($key, $flags, true)) {
                throw ValidationException::withMessages([
                    'normalization_settings' => "Unknown normalization setting: {$key}.",
                ]);
            }

            if (! is_bool($value) && ! in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
                throw ValidationException::withMessages([
                    'normalization_settings' => "Normalization setting {$key} must be true or false.",
                ]);
            }

            $clean[$key] = filter_var($value, FILTER_VALIDATE_BOOL);
        }

        return $clean === [] ? null : $clean;
    }
}
