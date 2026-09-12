<?php

namespace App\Domains\Courses\Actions;

/**
 * The one vocabulary for certificate eligibility rules (SPEC §39).
 *
 *   > Certificate rules may be set at course level and overridden at offering
 *   > level.
 *
 * Two callers write these rules and they need *different* shapes, which is
 * the whole reason this exists in one place:
 *
 * **A template is a base.** Every rule has an answer, and an unchecked box
 * genuinely means "not required". So the template shape carries all seven
 * keys, with `null` for an unset threshold and `false` for an unset flag.
 *
 * **An offering is an override.** Here "not required" and "inherit whatever
 * the course says" are different answers, and collapsing them is a live
 * hazard: if an offering stored `false` for every box the operator did not
 * tick, then a batch that only wanted to raise `min_progress_percent` would
 * silently switch off the course's teacher-approval and payment requirements
 * on its way past. So the override shape is **sparse** — a key is present
 * only when someone deliberately set it, and `null` is returned when nothing
 * was overridden at all.
 *
 * `CheckCertificateEligibilityAction` layers the second over the first, which
 * only produces the right answer because absent and false are kept distinct
 * here.
 */
class NormalizeCertificateRulesAction
{
    /** Thresholds, all expressed as percentages (SPEC §27, §39). */
    public const INT_RULES = [
        'min_progress_percent',
        'min_attendance_percent',
        'min_score',
    ];

    /** Which assessment "Pass final assessment" means, when it means one. */
    public const ID_RULES = [
        'assessment_id',
    ];

    /** SPEC §39's "Required ..." rules. */
    public const BOOL_RULES = [
        'require_final_assessment',
        'require_teacher_approval',
        'require_payment',
    ];

    /**
     * @param  bool  $sparse  true for an offering-level override: keep only
     *                        the keys actually set, and return null when none
     *                        were.
     * @return array<string, mixed>|null
     */
    public function execute(mixed $rules, bool $sparse = false): ?array
    {
        if (! is_array($rules)) {
            return $sparse ? null : [];
        }

        $out = [];

        foreach (self::INT_RULES as $key) {
            $value = $this->nullableInt($rules[$key] ?? null);
            if (! $sparse || $value !== null) {
                $out[$key] = $value;
            }
        }

        foreach (self::ID_RULES as $key) {
            $value = $this->nullableId($rules[$key] ?? null);
            if (! $sparse || $value !== null) {
                $out[$key] = $value;
            }
        }

        foreach (self::BOOL_RULES as $key) {
            $given = $rules[$key] ?? null;
            if ($sparse && ($given === null || $given === '')) {
                // Inherit. Not the same as "not required", which is `false`.
                continue;
            }
            $out[$key] = $this->boolean($given);
        }

        if ($sparse && $out === []) {
            return null;
        }

        return $out;
    }

    /**
     * `'0'` has to read as false, because a tri-state override control posts
     * its answer as a string: `''` inherit, `'0'` not required, `'1'`
     * required. A plain `(bool)` cast gets this right where a truthiness test
     * on the raw string would not.
     */
    private function boolean(mixed $value): bool
    {
        if (is_string($value)) {
            return ! in_array(strtolower(trim($value)), ['', '0', 'false', 'no', 'off'], true);
        }

        return (bool) $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
