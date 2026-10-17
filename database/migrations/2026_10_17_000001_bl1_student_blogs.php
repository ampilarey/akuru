<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BLOG_PLAN BL1 (STATUS §5se): a student may ask to start a blog, a parent
 * consents if the student is under 18, and the office approves.
 *
 *  - `writer_profiles.kind`: `writer` (every existing row) or `student`.
 *    A student's profile also carries its blog's title, the consenting
 *    parent, and the parent's two choices (the photo, the school's name).
 *  - `blog_requests`: one student's *start a blog* request and where it
 *    stands — waiting for a parent, waiting for the office, approved,
 *    refused, or declined by the parent.
 *
 * Additive only (rule 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('writer_profiles', function (Blueprint $table) {
            $table->string('kind', 20)->default('writer')->after('user_id')->index();
            $table->string('blog_title', 120)->nullable()->after('display_name');
            $table->boolean('show_photo')->default(true)->after('photo_media_file_id');
            $table->boolean('show_school')->default(false)->after('show_photo');
            $table->foreignId('guardian_user_id')->nullable()->after('show_school')->constrained('users')->nullOnDelete();
            $table->timestamp('guardian_consented_at')->nullable()->after('guardian_user_id');
        });

        Schema::create('blog_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('pen_name', 60);
            $table->string('blog_title', 120);
            $table->string('about', 300);
            // The student's age band when they asked: a request made at 17
            // keeps needing its parent, even if it is decided after a birthday.
            $table->boolean('under_18');
            $table->string('status', 30)->index();
            $table->foreignId('guardian_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('guardian_decided_at')->nullable();
            $table->boolean('show_photo')->default(false);
            $table->boolean('show_school')->default(false);
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_requests');
        Schema::table('writer_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('guardian_user_id');
            $table->dropColumn(['kind', 'blog_title', 'show_photo', 'show_school', 'guardian_consented_at']);
        });
    }
};
