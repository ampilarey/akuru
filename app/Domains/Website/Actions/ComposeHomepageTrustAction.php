<?php

namespace App\Domains\Website\Actions;

use App\Domains\Media\Actions\ListPublicMediaFilesAction;
use App\Domains\People\Actions\CountStudentsAction;
use App\Domains\Settings\Actions\GetSettingAction;

class ComposeHomepageTrustAction
{
    public const DEFAULT_STUDENTS_MIN_DISPLAY = 25;

    /**
     * Settings-driven homepage trust signals. Empty settings omit the signal —
     * nothing is invented in the view.
     *
     * @return array{
     *     accreditation: ?string,
     *     years_operating: ?int,
     *     years_label: ?string,
     *     students_taught: ?int,
     *     students_source: 'manual'|'computed'|null,
     *     students_label: ?string,
     *     logos: list<array{id: int, url: string, alt: string}>,
     *     has_signals: bool
     * }
     */
    public function execute(?string $locale = null): array
    {
        $locale = $locale ?: app()->getLocale();
        $settings = app(GetSettingAction::class);

        $accreditation = $this->localized($settings->execute('trust.accreditation', []), $locale);
        $yearsLabel = $this->localized($settings->execute('trust.years_label', []), $locale);
        $studentsLabel = $this->localized($settings->execute('trust.students_label', []), $locale);

        $years = $this->yearsOperating($settings);
        [$students, $source] = $this->studentsTaught($settings);

        $logoIds = $settings->execute('trust.partner_logo_ids', []);
        if (is_string($logoIds) && $logoIds !== '') {
            $decoded = json_decode($logoIds, true);
            $logoIds = is_array($decoded) ? $decoded : (preg_split('/\s*,\s*/', $logoIds) ?: []);
        }
        if (! is_array($logoIds)) {
            $logoIds = [];
        }

        $logos = app(ListPublicMediaFilesAction::class)->execute(array_values($logoIds));

        return [
            'accreditation' => $accreditation,
            'years_operating' => $years,
            'years_label' => $yearsLabel,
            'students_taught' => $students,
            'students_source' => $students === null ? null : $source,
            'students_label' => $studentsLabel,
            'logos' => $logos,
            // Years stays composed for non-hero surfaces but no longer
            // decides whether the hero trust block renders.
            'has_signals' => $accreditation !== null || $students !== null || $logos !== [],
        ];
    }

    private function yearsOperating(GetSettingAction $settings): ?int
    {
        $override = $settings->execute('trust.years_operating', '');
        if ($this->isPresent($override)) {
            $years = (int) $override;

            return $years > 0 ? $years : null;
        }

        $founded = $settings->execute('trust.founded_year', '');
        if (! $this->isPresent($founded)) {
            return null;
        }

        $year = (int) $founded;
        if ($year < 1800 || $year > 2100) {
            return null;
        }

        $years = now('Indian/Maldives')->year - $year;

        return $years > 0 ? $years : null;
    }

    /**
     * @return array{0: ?int, 1: 'manual'|'computed'}
     */
    private function studentsTaught(GetSettingAction $settings): array
    {
        $manual = $settings->execute('trust.students_taught', '');
        if ($this->isPresent($manual)) {
            $count = (int) $manual;

            return [$count > 0 ? $count : null, 'manual'];
        }

        // A tiny computed count reads worse than no number (a hero saying
        // "9 students" undersells) — hold it back until it clears the
        // display floor. A manual override always shows: the operator chose it.
        $count = app(CountStudentsAction::class)->execute();
        $floor = $settings->execute('trust.students_min_display', '');
        $minimum = $this->isPresent($floor) ? max((int) $floor, 1) : self::DEFAULT_STUDENTS_MIN_DISPLAY;

        return [$count >= $minimum ? $count : null, 'computed'];
    }

    private function localized(mixed $value, string $locale): ?string
    {
        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }

        if (! is_array($value)) {
            return null;
        }

        foreach ([$locale, 'en', 'dv', 'ar'] as $key) {
            $candidate = trim((string) ($value[$key] ?? ''));
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    private function isPresent(mixed $value): bool
    {
        if ($value === null || is_array($value)) {
            return false;
        }

        return trim((string) $value) !== '';
    }
}
