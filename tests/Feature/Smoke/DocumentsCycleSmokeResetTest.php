<?php

use Database\Seeders\SmokeMarkerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * `scripts/smoke/documents-language.mjs` generates `SMOKE-Doc-Term`'s report
 * cards in Dhivehi and Arabic (STATUS §5pz). The cards are drafts the walk
 * makes; `SmokeMarkerSeeder::documentsCycle()` keeps the term and removes
 * them, with their documents, each run — and never another term's.
 */
it('keeps the walk\'s term, removes its cards and their documents, leaves other terms alone, and runs twice', function () {
    $this->seed();
    $this->seed(SmokeMarkerSeeder::class);
    $this->seed(SmokeMarkerSeeder::class);

    $termId = (int) DB::table('terms')->where('name', 'SMOKE-Doc-Term')->value('id');
    expect($termId)->toBeGreaterThan(0)
        ->and(DB::table('terms')->where('name', 'SMOKE-Doc-Term')->count())->toBe(1);

    $studentId = (int) DB::table('students')->orderBy('id')->value('id');
    $classId = (int) DB::table('classes')->orderBy('id')->value('id');
    $templateId = (int) (DB::table('report_card_templates')->value('id')
        ?? DB::table('report_card_templates')->insertGetId(['name' => 'SMOKE-Doc-Template', 'sections' => json_encode(['grades_table']), 'created_at' => now(), 'updated_at' => now()]));
    $card = fn (int $term) => DB::table('report_cards')->insertGetId([
        'student_id' => $studentId, 'term_id' => $term, 'class_id' => $classId, 'template_id' => $templateId, 'status' => 'draft',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $walkCard = $card($termId);
    DB::table('documents')->insert([
        'documentable_type' => 'report_card', 'documentable_id' => $walkCard, 'document_type' => 'report_card',
        'title' => 'Report card', 'media_path' => 'documents/smoke-doc.html',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    // Another term's card, on a term of this test's own, is not the walk's.
    $otherTerm = DB::table('terms')->insertGetId([
        'academic_year_id' => DB::table('terms')->where('id', $termId)->value('academic_year_id'), 'name' => 'Not-the-walk',
        'status' => 'upcoming', 'start_date' => now()->toDateString(), 'end_date' => now()->addDays(10)->toDateString(),
        'sort_order' => 97, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $otherCard = $card($otherTerm);

    $this->seed(SmokeMarkerSeeder::class);

    expect(DB::table('report_cards')->where('id', $walkCard)->exists())->toBeFalse()
        ->and(DB::table('documents')->where('documentable_type', 'report_card')->where('documentable_id', $walkCard)->exists())->toBeFalse()
        ->and(DB::table('report_cards')->where('id', $otherCard)->exists())->toBeTrue();
});
