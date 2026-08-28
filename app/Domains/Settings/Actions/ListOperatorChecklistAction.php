<?php

namespace App\Domains\Settings\Actions;

use App\Domains\Settings\Models\OperatorCheck;
use Illuminate\Support\Facades\DB;

/**
 * The operator close-out checklist (docs/OPERATOR_CHECKLIST.md) as data.
 * Definitions live HERE so they version with the repo; item keys are
 * stable identifiers — never renumber a shipped key, append new ones.
 * State (who ticked what, when) comes from operator_checklist_checks.
 */
class ListOperatorChecklistAction
{
    /**
     * @return list<array{key: string, title: string, items: list<array{key: string, label: string}>}>
     */
    public static function definitions(): array
    {
        return [
            ['key' => 'walks-writer', 'title' => '1a. Writer portal walk (L5)', 'items' => [
                ['key' => 'w1', 'label' => 'Plain user applies as writer at /write (agreement required)'],
                ['key' => 'w2', 'label' => 'Admin approves in /admin/library — user gains writer role + profile'],
                ['key' => 'w3', 'label' => 'Writer creates a draft article, edits, submits for review'],
                ['key' => 'w4', 'label' => 'Admin requests changes — comment reaches the writer trail'],
                ['key' => 'w5', 'label' => 'Resubmit → approve — published and visible on public /library'],
            ]],
            ['key' => 'walks-research', 'title' => '1b. Research peer review walk (L7)', 'items' => [
                ['key' => 'r1', 'label' => 'Writer submits a research item with citations'],
                ['key' => 'r2', 'label' => 'Admin approve refused without a peer accept'],
                ['key' => 'r3', 'label' => 'Reviewer assigned by email — gains reviewer role'],
                ['key' => 'r4', 'label' => 'Reviewer recommends revise at /review — anonymous comment in trail'],
                ['key' => 'r5', 'label' => 'Accept on re-review → publish — citations on the public page'],
            ]],
            ['key' => 'walks-earnings', 'title' => '1c. Earnings & payouts walk (L6)', 'items' => [
                ['key' => 'e1', 'label' => 'Paid purchase creates a writer earning with the right split'],
                ['key' => 'e2', 'label' => 'Payout request shows the payouts-disabled gate (flag stays off)'],
                ['key' => 'e3', 'label' => 'Payouts queue renders; earnings CSV downloads'],
            ]],
            ['key' => 'walks-pronounce', 'title' => '1d. Pronunciation walk, AI off (Arabic B)', 'items' => [
                ['key' => 'p1', 'label' => 'Student records at /learn/pronounce — no AI feedback, flag off'],
                ['key' => 'p2', 'label' => 'Teacher verdict at /teach/pronunciation clears the queue'],
                ['key' => 'p3', 'label' => 'Admin: approve sample, dataset stats, export manifest, model shelf'],
                ['key' => 'p4', 'label' => 'Guards: teacher page 403s plain users; admin needs pronunciation.manage'],
            ]],
            ['key' => 'walks-quran', 'title' => '1e. Recitation queue walk, AI off (Qur\'an B)', 'items' => [
                ['key' => 'q1', 'label' => 'Queue is byte-identical to pre-AI flow — no AI column while flag off'],
            ]],
            ['key' => 'walks-money', 'title' => '1f. Money surfaces walk (Phase 4)', 'items' => [
                ['key' => 'm1', 'label' => 'Wallet refund: enrollment cancelled, discount released, wallet credited'],
                ['key' => 'm2', 'label' => 'Manual payment recorded — activation without BML'],
                ['key' => 'm3', 'label' => 'Payments CSV downloads with refunded totals'],
                ['key' => 'm4', 'label' => 'Offering price override on public listing; zero behaves as free'],
            ]],
            ['key' => 'walks-mobile', 'title' => '1g. Mobile-browser smoke (Phase 5)', 'items' => [
                ['key' => 'mb1', 'label' => 'Phone browser: RTL/Thaana, mic recorder, PWA install prompt'],
            ]],
            ['key' => 'flags', 'title' => '2. Feature-flag decisions', 'items' => [
                ['key' => 'f1', 'label' => 'Payout tax/withholding decision recorded (STATUS + ADR if money flow changes)'],
                ['key' => 'f2', 'label' => 'LIBRARY_PAYOUTS_ENABLED on → payout loop walked to paid'],
                ['key' => 'f3', 'label' => 'Model trained from real approved samples; version registered + activated'],
                ['key' => 'f4', 'label' => '§51.17 consent handling confirmed'],
                ['key' => 'f5', 'label' => 'AI_PRONUNCIATION_ENABLED on test → §1d/§1e re-walked with AI visible'],
            ]],
            ['key' => 'hifz', 'title' => '3. Hifz retirement (ADR-025 gate)', 'items' => [
                ['key' => 'h1', 'label' => 'ADR-025 walks pass on the seeded representative dataset'],
                ['key' => 'h2', 'label' => 'Verify-structure output captured in STATUS.md'],
                ['key' => 'h3', 'label' => 'Deletion slice requested (one PR: namespaces + routes)'],
            ]],
            ['key' => 'devices', 'title' => '4. Phase 5 device work', 'items' => [
                ['key' => 'd1', 'label' => 'cap add + sync; app runs on a real device'],
                ['key' => 'd2', 'label' => '§50 device checklist walked (mic, BML return, offline, RTL)'],
                ['key' => 'd3', 'label' => 'Results in STATUS; signing keys + store listings'],
                ['key' => 'd4', 'label' => 'Push stays future: FCM/APNs keys + token endpoint first'],
            ]],
            ['key' => 'gates', 'title' => '5. Deploy gates & housekeeping', 'items' => [
                ['key' => 'g1', 'label' => 'Payload-cleanup: STATUS §5h query at zero → cleanup its own deploy'],
                ['key' => 'g2', 'label' => 'Public checkout UX swap decided and walked on test'],
                ['key' => 'g3', 'label' => 'Branch protection per docs/BRANCH_PROTECTION.md confirmed on main'],
                ['key' => 'g4', 'label' => 'Stale ci-control-main branch deleted on GitHub'],
            ]],
        ];
    }

    /**
     * @return list<string>
     */
    public static function itemKeys(): array
    {
        $keys = [];
        foreach (self::definitions() as $section) {
            foreach ($section['items'] as $item) {
                $keys[] = $item['key'];
            }
        }

        return $keys;
    }

    /**
     * @return array{sections: mixed, checked: array<string, array{by: string|null, at: string}>, done: int, total: int}
     */
    public function execute(): array
    {
        $rows = OperatorCheck::query()->get();
        $names = DB::table('users')
            ->whereIn('id', $rows->pluck('checked_by')->filter()->all())
            ->pluck('name', 'id');

        $checked = [];
        foreach ($rows as $row) {
            $checked[$row->item_key] = [
                'by' => $row->checked_by !== null ? ($names[$row->checked_by] ?? null) : null,
                'at' => $row->checked_at->toDateTimeString(),
            ];
        }

        return [
            'sections' => self::definitions(),
            'checked' => $checked,
            'done' => count($checked),
            'total' => count(self::itemKeys()),
        ];
    }
}
