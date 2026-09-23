<?php

use Database\Seeders\SmokeMarkerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * `scripts/smoke/certify.mjs` builds `SMOKE-Cert`, issues on it and revokes.
 * `SmokeMarkerSeeder::certifyCycle()` clears the template, its issued rows
 * and their rendered documents, so the walk can run twice; the seeder too.
 */
it('clears a run\'s template, issued certificates and documents, and runs twice', function () {
    Storage::fake('local');
    $this->seed();
    $this->seed(SmokeMarkerSeeder::class);
    $this->seed(SmokeMarkerSeeder::class);

    $templateId = DB::table('certificate_templates')->insertGetId([
        'name' => 'SMOKE-Cert', 'kind' => 'course_completion', 'rules' => json_encode(['min_progress_percent' => 100]),
        'body_html' => '<p>{{student_name}}</p>', 'active' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $documentId = DB::table('documents')->insertGetId([
        'documentable_type' => 'issued_certificate', 'documentable_id' => 0, 'media_path' => 'documents/smoke-cert.html',
        'document_type' => 'course_certificate', 'title' => 'SMOKE-Cert', 'created_at' => now(), 'updated_at' => now(),
    ]);
    Storage::disk('local')->put('documents/smoke-cert.html', '<p>x</p>');
    $issuedId = DB::table('issued_certificates')->insertGetId([
        'certificate_template_id' => $templateId,
        'student_id' => (int) DB::table('students')->orderBy('id')->value('id'),
        'academic_year_id' => (int) DB::table('academic_years')->orderBy('id')->value('id'),
        'public_id' => 'smokecertpublicid0000000000',
        'certificate_number' => 'AKU-SMOKE-1', 'document_id' => $documentId,
        'completion_date' => now()->toDateString(), 'issued_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('documents')->where('id', $documentId)->update(['documentable_id' => $issuedId]);

    $this->seed(SmokeMarkerSeeder::class);

    expect(DB::table('issued_certificates')->where('id', $issuedId)->exists())->toBeFalse()
        ->and(DB::table('documents')->where('id', $documentId)->exists())->toBeFalse()
        ->and(Storage::disk('local')->exists('documents/smoke-cert.html'))->toBeFalse()
        ->and(DB::table('certificate_templates')->where('id', $templateId)->exists())->toBeFalse();
});
