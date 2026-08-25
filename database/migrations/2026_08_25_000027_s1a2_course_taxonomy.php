<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * 1A.2 — hierarchical course subjects, audiences, levels, engine workflow.
 * Existing courses.status (open/closed/upcoming) is not renamed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('course_subjects')->nullOnDelete();
            $table->string('name_en');
            $table->string('name_dv')->nullable();
            $table->string('name_ar')->nullable();
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('audiences', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_dv')->nullable();
            $table->string('name_ar')->nullable();
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('course_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_dv')->nullable();
            $table->string('name_ar')->nullable();
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('subject_id')->nullable()->after('course_category_id')->constrained('course_subjects')->nullOnDelete();
            $table->string('workflow_status', 20)->default('draft')->after('status');
            $table->string('course_type', 40)->default('general')->after('workflow_status');
            $table->foreignId('created_by')->nullable()->after('course_type')->constrained('users')->nullOnDelete();
            $table->string('title_dv')->nullable()->after('title');
            $table->string('title_ar')->nullable()->after('title_dv');
        });

        DB::table('courses')->where('status', 'open')->update(['workflow_status' => 'published']);

        $this->seedTaxonomy();

        Permission::firstOrCreate(['name' => 'courses.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'courses.publish', 'guard_name' => 'web']);

        foreach (['super_admin', 'admin'] as $roleName) {
            if (Role::query()->where('name', $roleName)->exists()) {
                Role::findByName($roleName)->givePermissionTo(['courses.manage', 'courses.publish']);
            }
        }
        if (Role::query()->where('name', 'headmaster')->exists()) {
            Role::findByName('headmaster')->givePermissionTo(['courses.manage']);
        }
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subject_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['workflow_status', 'course_type', 'title_dv', 'title_ar']);
        });
        Schema::dropIfExists('course_levels');
        Schema::dropIfExists('audiences');
        Schema::dropIfExists('course_subjects');
    }

    private function seedTaxonomy(): void
    {
        $now = now();
        $roots = [
            ['slug' => 'quran', 'name_en' => 'Quran', 'children' => ['hifz' => 'Hifz', 'tajweed' => 'Tajweed', 'qiraah' => "Qira'ah", 'tafseer' => 'Tafseer']],
            ['slug' => 'arabic', 'name_en' => 'Arabic', 'children' => ['nahw' => 'Nahw', 'sarf' => 'Sarf', 'balagha' => 'Balagha', 'conversation' => 'Conversation']],
            ['slug' => 'islamic-studies', 'name_en' => 'Islamic Studies', 'children' => ['fiqh' => 'Fiqh', 'aqeedah' => 'Aqeedah', 'seerah' => 'Seerah', 'hadith' => 'Hadith']],
            ['slug' => 'dhivehi', 'name_en' => 'Dhivehi', 'children' => []],
            ['slug' => 'english', 'name_en' => 'English', 'children' => []],
        ];

        $sort = 0;
        foreach ($roots as $root) {
            $parentId = DB::table('course_subjects')->insertGetId([
                'parent_id' => null,
                'name_en' => $root['name_en'],
                'slug' => $root['slug'],
                'sort_order' => $sort++,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $childSort = 0;
            foreach ($root['children'] as $slug => $name) {
                DB::table('course_subjects')->insert([
                    'parent_id' => $parentId,
                    'name_en' => $name,
                    'slug' => $slug,
                    'sort_order' => $childSort++,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach (['kids' => 'Kids', 'school-children' => 'School children', 'adults' => 'Adults', 'all' => 'All'] as $slug => $name) {
            DB::table('audiences')->insert([
                'name_en' => $name,
                'slug' => $slug,
                'sort_order' => $sort++,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $levelSort = 0;
        foreach (['foundation' => 'Foundation', 'beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced', 'level-1' => 'Level 1', 'level-2' => 'Level 2'] as $slug => $name) {
            DB::table('course_levels')->insert([
                'name_en' => $name,
                'slug' => $slug,
                'sort_order' => $levelSort++,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
