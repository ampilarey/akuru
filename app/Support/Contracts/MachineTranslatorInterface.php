<?php

namespace App\Support\Contracts;

/**
 * T2 (§5n): machine translation as a SUGGESTION source only — output
 * lands in an editor box for a human to confirm, never auto-publishes
 * (religious terminology makes unreviewed machine output a liability).
 * Null implementation by default; a provider slice can bind a real
 * service behind config('services.machine_translator.driver').
 */
interface MachineTranslatorInterface
{
    /**
     * Translate $text from $from to $to, or null when no translator is
     * configured or the provider cannot answer.
     */
    public function translate(string $text, string $from, string $to): ?string;
}
