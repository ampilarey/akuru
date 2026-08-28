<?php

namespace App\Support\Translation;

use App\Support\Contracts\MachineTranslatorInterface;

/**
 * Default binding: no external service, no suggestion. Everything
 * works with the translator off (rule 8's spirit) — the editor simply
 * hides the Suggest affordance.
 */
class NullMachineTranslator implements MachineTranslatorInterface
{
    public function translate(string $text, string $from, string $to): ?string
    {
        return null;
    }
}
