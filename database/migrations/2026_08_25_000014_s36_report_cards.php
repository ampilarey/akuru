<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S3.6 — report card templates, cards, comments, and production renderer seed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_card_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('applies_to')->nullable();
            $table->json('sections');
            $table->string('header')->nullable();
            $table->string('footer')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('report_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('term_id');
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('template_id');
            $table->string('status', 16)->default('draft');
            $table->unsignedBigInteger('document_id')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'term_id'], 'rc_student_term_uq');
            $table->index(['class_id', 'term_id', 'status'], 'rc_class_term_status_idx');
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('term_id')->references('id')->on('terms')->cascadeOnDelete();
            $table->foreign('class_id')->references('id')->on('classes')->cascadeOnDelete();
            $table->foreign('template_id')->references('id')->on('report_card_templates')->restrictOnDelete();
            $table->foreign('document_id')->references('id')->on('documents')->nullOnDelete();
        });

        Schema::create('report_card_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_card_id');
            $table->string('comment_type', 32);
            $table->text('comment');
            $table->text('comment_arabic')->nullable();
            $table->text('comment_dhivehi')->nullable();
            $table->unsignedBigInteger('author_id')->nullable();
            $table->timestamps();

            $table->unique(['report_card_id', 'comment_type'], 'rc_comment_type_uq');
            $table->foreign('report_card_id')->references('id')->on('report_cards')->cascadeOnDelete();
            $table->foreign('author_id')->references('id')->on('users')->nullOnDelete();
        });

        DB::table('report_card_templates')->insert([
            'name' => 'Default term report',
            'applies_to' => null,
            'sections' => json_encode([
                'grades_table',
                'attendance_summary',
                'behavior_summary',
                'competencies',
                'teacher_comment',
                'head_comment',
                'awards',
            ]),
            'header' => 'Akuru Institute',
            'footer' => 'Official report card',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('report_card_comments');
        Schema::dropIfExists('report_cards');
        Schema::dropIfExists('report_card_templates');
    }
};
