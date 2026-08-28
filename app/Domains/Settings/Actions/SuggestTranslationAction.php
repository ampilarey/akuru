<?php

namespace App\Domains\Settings\Actions;

use App\Support\Contracts\MachineTranslatorInterface;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\ValidationException;

/**
 * T2: ask the configured translator for a Dhivehi draft of one English
 * reference string. The result only prefills the correction box — a
 * human always confirms before anything is saved.
 */
class SuggestTranslationAction
{
    public function __construct(private MachineTranslatorInterface $translator) {}

    /**
     * @return array{suggestion: ?string}
     */
    public function execute(string $group, string $key): array
    {
        if (! in_array($group, ListTranslationCatalogAction::groups(), true)) {
            throw ValidationException::withMessages(['group' => 'Unknown translation group.']);
        }

        $reference = Lang::get($group.'.'.$key, [], 'en');
        if (! is_string($reference) || $reference === $group.'.'.$key) {
            throw ValidationException::withMessages(['key' => 'Unknown translation key.']);
        }

        return [
            'suggestion' => $this->translator->translate($reference, 'en', ListTranslationCatalogAction::LOCALE),
        ];
    }
}
