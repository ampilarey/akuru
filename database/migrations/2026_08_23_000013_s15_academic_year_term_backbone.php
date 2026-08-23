<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S1.5 — academic year status, terms table, class year/roster, unified_term_id.
 * Does not drop academic_years.terms json or course_enrollments.term_id/term_key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_years', function (Blueprint $table) {
            $table->string('status', 16)->default('upcoming')->after('is_current');
            $table->index('status');
        });

        DB::table('academic_years')->where('is_current', true)->update(['status' => 'active']);
        DB::table('academic_years')->where('is_current', false)->where('status', 'upcoming')->update(['status' => 'upcoming']);

        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 16)->default('upcoming');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['academic_year_id', 'status']);
        });

        $this->backfillTerms();

        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->foreignId('unified_term_id')->nullable()->after('term_id')->constrained('terms')->nullOnDelete();
        });

        $this->backfillEnrollmentTerms();

        Schema::table('classes', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('school_id')->constrained('academic_years')->nullOnDelete();
            $table->foreignId('class_teacher_staff_profile_id')->nullable()->after('class_teacher_id')->constrained('staff_profiles')->nullOnDelete();
        });

        $yearId = $this->ensureYearId();
        if ($yearId !== null) {
            DB::table('classes')->whereNull('academic_year_id')->update(['academic_year_id' => $yearId]);
        }

        $this->disambiguateClassNames($yearId);

        Schema::table('classes', function (Blueprint $table) {
            $table->unique(['name', 'section', 'academic_year_id'], 'classes_name_section_year_unique');
        });

        Schema::create('class_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->unsignedBigInteger('student_id');
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->date('enrolled_at')->nullable();
            $table->date('left_at')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->unique(['class_id', 'student_id']);
            $table->index(['academic_year_id', 'status']);
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
        });

        $now = now();
        $students = DB::table('students')->whereNotNull('class_id')->get(['id', 'class_id']);
        foreach ($students as $student) {
            $classYear = DB::table('classes')->where('id', $student->class_id)->value('academic_year_id') ?? $yearId;
            if ($classYear === null) {
                continue;
            }

            DB::table('class_student')->insert([
                'class_id' => $student->class_id,
                'student_id' => $student->id,
                'academic_year_id' => $classYear,
                'enrolled_at' => $now->toDateString(),
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('class_student');

        Schema::table('classes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('class_teacher_staff_profile_id');
            $table->dropConstrainedForeignId('academic_year_id');
        });

        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unified_term_id');
        });

        Schema::dropIfExists('terms');

        Schema::table('academic_years', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }

    private function backfillTerms(): void
    {
        $years = DB::table('academic_years')->orderBy('id')->get();

        foreach ($years as $year) {
            $decoded = is_string($year->terms) ? json_decode($year->terms, true) : $year->terms;
            $rows = is_array($decoded) ? $decoded : [];

            if ($rows === []) {
                DB::table('terms')->insert([
                    'academic_year_id' => $year->id,
                    'name' => 'Term 1',
                    'start_date' => $year->start_date,
                    'end_date' => $year->end_date,
                    'status' => $year->status === 'active' ? 'active' : 'upcoming',
                    'sort_order' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                continue;
            }

            foreach (array_values($rows) as $index => $term) {
                if (! is_array($term)) {
                    continue;
                }

                DB::table('terms')->insert([
                    'academic_year_id' => $year->id,
                    'name' => (string) ($term['name'] ?? 'Term '.($index + 1)),
                    'start_date' => $term['start_date'] ?? $year->start_date,
                    'end_date' => $term['end_date'] ?? $year->end_date,
                    'status' => (string) ($term['status'] ?? 'upcoming'),
                    'sort_order' => (int) ($term['sort_order'] ?? $index + 1),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function backfillEnrollmentTerms(): void
    {
        $legacyTermId = DB::table('terms')->where('name', 'Legacy')->value('id');
        if ($legacyTermId === null) {
            $yearId = $this->ensureYearId();
            if ($yearId === null) {
                return;
            }

            $year = DB::table('academic_years')->where('id', $yearId)->first();
            $legacyTermId = DB::table('terms')->insertGetId([
                'academic_year_id' => $yearId,
                'name' => 'Legacy',
                'start_date' => $year->start_date,
                'end_date' => $year->end_date,
                'status' => 'closed',
                'sort_order' => 99,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $termIds = DB::table('terms')->pluck('id')->all();
        $enrollments = DB::table('course_enrollments')->whereNotNull('term_id')->get(['id', 'term_id']);

        foreach ($enrollments as $enrollment) {
            $unified = in_array((int) $enrollment->term_id, array_map('intval', $termIds), true)
                ? (int) $enrollment->term_id
                : $legacyTermId;

            DB::table('course_enrollments')->where('id', $enrollment->id)->update([
                'unified_term_id' => $unified,
            ]);
        }
    }

    private function ensureYearId(): ?int
    {
        $yearId = DB::table('academic_years')->where('status', 'active')->value('id')
            ?? DB::table('academic_years')->where('is_current', true)->value('id')
            ?? DB::table('academic_years')->orderBy('id')->value('id');

        if ($yearId !== null) {
            return (int) $yearId;
        }

        if (! Schema::hasTable('academic_years')) {
            return null;
        }

        $hasClasses = Schema::hasTable('classes') && DB::table('classes')->exists();
        $hasEnrollments = Schema::hasTable('course_enrollments') && DB::table('course_enrollments')->exists();

        if (! $hasClasses && ! $hasEnrollments) {
            return null;
        }

        return (int) DB::table('academic_years')->insertGetId([
            'name' => now()->year.'-'.(now()->year + 1),
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'is_current' => true,
            'status' => 'active',
            'description' => 'Backfilled active year',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function disambiguateClassNames(?int $yearId): void
    {
        if ($yearId === null) {
            return;
        }

        $duplicates = DB::table('classes')
            ->select('name', 'section', DB::raw('COUNT(*) as aggregate'))
            ->where('academic_year_id', $yearId)
            ->groupBy('name', 'section')
            ->having('aggregate', '>', 1)
            ->get();

        foreach ($duplicates as $duplicate) {
            $rows = DB::table('classes')
                ->where('academic_year_id', $yearId)
                ->where('name', $duplicate->name)
                ->where(function ($query) use ($duplicate) {
                    $duplicate->section === null
                        ? $query->whereNull('section')
                        : $query->where('section', $duplicate->section);
                })
                ->orderBy('id')
                ->get();

            foreach ($rows as $index => $row) {
                if ($index === 0) {
                    continue;
                }

                DB::table('classes')->where('id', $row->id)->update([
                    'section' => trim((string) $row->section.'-'.$row->id, '-'),
                ]);
            }
        }
    }
};
