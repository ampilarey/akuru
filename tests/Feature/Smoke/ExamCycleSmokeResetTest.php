<?php

use App\Domains\ExamsGrades\Models\Exam;
use App\Domains\ExamsGrades\Models\ExamMark;
use App\Domains\ExamsGrades\Models\ExamStatusAudit;
use App\Domains\ExamsGrades\Models\ReportCard;
use App\Domains\ExamsGrades\Models\TermGrade;
use Database\Seeders\SmokeMarkerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * `scripts/smoke/exams.mjs` walks the S3 cycle — schedule, mark, publish,
 * report card, parent — and every step of it refuses to happen twice: a
 * published report card cannot be regenerated and `report_cards` is unique
 * per student and term. So the walk works in a term of its own, `SMOKE-Term`,
 * which `SmokeMarkerSeeder::examCycle()` plants once and empties every run.
 *
 * This pins the two things the walk depends on: that re-seeding leaves one
 * term and no leftovers, and that the real term's published cards — the ones
 * `sensitiveRecords()` plants for the own-data probe — are not touched.
 */
it('plants SMOKE-Term once and empties it on every run, leaving the real term alone', function () {
    $this->seed();
    $this->seed(SmokeMarkerSeeder::class);
    $this->seed(SmokeMarkerSeeder::class);

    $terms = DB::table('terms')->where('name', 'SMOKE-Term')->get();
    expect($terms)->toHaveCount(1);

    $term = $terms->first();
    $realTermId = (int) DB::table('terms')->where('academic_year_id', $term->academic_year_id)->orderBy('id')->value('id');
    expect($realTermId)->not->toBe((int) $term->id);

    $realCards = ReportCard::query()->where('term_id', $realTermId)->where('status', 'published')->count();
    expect($realCards)->toBeGreaterThan(0);

    // What a run of the walk leaves behind.
    $classId = (int) DB::table('classes')->where('academic_year_id', $term->academic_year_id)->orderBy('id')->value('id');
    $studentId = (int) DB::table('class_student')->where('class_id', $classId)->value('student_id');
    $subjectId = (int) DB::table('subjects')->value('id');
    $typeId = (int) DB::table('exam_types')->value('id');
    $templateId = (int) DB::table('report_card_templates')->value('id');
    // A real user: the comment's author is a foreign key, and under a shared
    // MySQL the ids do not restart at 1 between tests.
    $adminId = (int) DB::table('users')->where('email', 'admin@akuru.edu.mv')->value('id');

    $exam = Exam::query()->create([
        'academic_year_id' => $term->academic_year_id, 'term_id' => $term->id, 'class_id' => $classId,
        'subject_id' => $subjectId, 'exam_type_id' => $typeId, 'name' => 'SMOKE-Exam', 'status' => 'published',
    ]);
    ExamMark::query()->create(['exam_id' => $exam->id, 'student_id' => $studentId, 'marks' => 85, 'entered_by' => $adminId, 'updated_by' => $adminId]);
    ExamStatusAudit::query()->create(['exam_id' => $exam->id, 'from_status' => 'review', 'to_status' => 'published', 'actor_id' => $adminId]);
    TermGrade::query()->create([
        'student_id' => $studentId, 'class_id' => $classId, 'subject_id' => $subjectId, 'term_id' => $term->id,
        'academic_year_id' => $term->academic_year_id, 'weighted_percent' => 85, 'grade' => 'A', 'components' => [],
    ]);
    $card = ReportCard::query()->create([
        'student_id' => $studentId, 'term_id' => $term->id, 'class_id' => $classId, 'template_id' => $templateId, 'status' => 'published',
    ]);
    $documentId = DB::table('documents')->insertGetId([
        'documentable_type' => 'report_card', 'documentable_id' => $card->id, 'media_path' => 'smoke/x.html',
        'document_type' => 'report_card', 'title' => 'x', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $card->update(['document_id' => $documentId]);
    DB::table('report_card_comments')->insert([
        'report_card_id' => $card->id, 'comment_type' => 'class_teacher', 'comment' => 'x', 'author_id' => $adminId,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->seed(SmokeMarkerSeeder::class);

    expect(DB::table('terms')->where('name', 'SMOKE-Term')->count())->toBe(1)
        ->and(Exam::query()->where('name', 'SMOKE-Exam')->exists())->toBeFalse()
        ->and(ExamMark::query()->where('exam_id', $exam->id)->exists())->toBeFalse()
        ->and(ExamStatusAudit::query()->where('exam_id', $exam->id)->exists())->toBeFalse()
        ->and(TermGrade::query()->where('term_id', $term->id)->exists())->toBeFalse()
        ->and(ReportCard::query()->where('term_id', $term->id)->exists())->toBeFalse()
        ->and(DB::table('documents')->where('id', $documentId)->exists())->toBeFalse()
        ->and(DB::table('report_card_comments')->where('report_card_id', $card->id)->exists())->toBeFalse()
        ->and(ReportCard::query()->where('term_id', $realTermId)->where('status', 'published')->count())->toBe($realCards);
});
