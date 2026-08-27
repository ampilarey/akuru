<?php

namespace App\Domains\Website\Actions;

use App\Domains\Courses\Actions\PresentCourseTitlesAction;
use App\Domains\Website\Enums\FunnelEventName;
use App\Domains\Website\Models\FunnelEvent;

class ComposeCourseFunnelReportAction
{
    /**
     * Per-course funnel counts plus the recorded W1 iterate-from-data decision.
     *
     * @return list<array{
     *     course_id: int,
     *     course_title: string,
     *     counts: array<string, int>,
     *     rates: array<string, ?float>,
     *     decision: string
     * }>
     */
    public function execute(?int $courseId = null): array
    {
        $query = FunnelEvent::query()
            ->selectRaw('course_id, name, COUNT(*) as aggregate')
            ->groupBy('course_id', 'name');
        if ($courseId !== null && $courseId > 0) {
            $query->where('course_id', $courseId);
        }

        $grouped = [];
        foreach ($query->get() as $row) {
            $id = (int) $row->course_id;
            $name = $row->name instanceof FunnelEventName ? $row->name->value : (string) $row->name;
            $grouped[$id][$name] = (int) $row->aggregate;
        }

        $titles = app(PresentCourseTitlesAction::class)->execute(array_keys($grouped));
        $reports = [];
        foreach ($grouped as $id => $rawCounts) {
            $counts = $this->zeroFilled($rawCounts);
            $reports[] = [
                'course_id' => $id,
                'course_title' => $titles[$id] ?? ('Course #'.$id),
                'counts' => $counts,
                'rates' => $this->rates($counts),
                'decision' => $this->decide($counts),
            ];
        }

        usort($reports, fn (array $a, array $b): int => $b['counts']['course_view'] <=> $a['counts']['course_view']);

        return $reports;
    }

    /**
     * @param  array<string, int>  $raw
     * @return array<string, int>
     */
    private function zeroFilled(array $raw): array
    {
        $counts = [];
        foreach (FunnelEventName::cases() as $name) {
            $counts[$name->value] = (int) ($raw[$name->value] ?? 0);
        }

        return $counts;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, ?float>
     */
    private function rates(array $counts): array
    {
        return [
            'view_to_register' => $this->ratio($counts['register_click'], $counts['course_view']),
            'register_to_started' => $this->ratio($counts['registration_started'], $counts['register_click']),
            'started_to_paid' => $this->ratio($counts['payment_completed'], $counts['registration_started']),
        ];
    }

    /**
     * Recorded decision rule (ADR-022): iterate W1 content from this funnel.
     *
     * @param  array<string, int>  $counts
     */
    public function decide(array $counts): string
    {
        $views = (int) ($counts['course_view'] ?? 0);
        $clicks = (int) ($counts['register_click'] ?? 0);
        $started = (int) ($counts['registration_started'] ?? 0);
        $paid = (int) ($counts['payment_completed'] ?? 0);
        $whatsapp = (int) ($counts['whatsapp_click'] ?? 0);
        $syllabus = (int) ($counts['syllabus_download'] ?? 0);

        if ($views === 0 && $clicks === 0 && $started === 0 && $paid === 0 && $whatsapp === 0 && $syllabus === 0) {
            return 'Not enough data yet — keep collecting.';
        }
        if ($views >= 20 && $clicks / max($views, 1) < 0.05) {
            return 'Iterate W1 content (hero, urgency, outcomes, sticky CTA) — few enroll clicks per view.';
        }
        if ($clicks >= 10 && $started / max($clicks, 1) < 0.5) {
            return 'Iterate checkout first step — clicks are not becoming registrations.';
        }
        if ($started >= 10 && $paid / max($started, 1) < 0.3) {
            return 'Iterate payment / fee copy — registrations are not completing payment.';
        }
        if ($whatsapp > $clicks && $whatsapp >= 5) {
            return 'WhatsApp is the stronger path — keep the sticky WhatsApp CTA and iterate enroll later.';
        }

        return 'Keep iterating W1 content from this funnel — no stage is clearly stuck.';
    }

    private function ratio(int $num, int $den): ?float
    {
        if ($den <= 0) {
            return null;
        }

        return round($num / $den, 3);
    }
}
