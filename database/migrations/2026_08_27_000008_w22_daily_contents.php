<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('daily_contents')) {
            Schema::create('daily_contents', function (Blueprint $table) {
                $table->id();
                $table->string('content_type', 16);
                $table->date('publish_date');
                $table->string('status', 16)->default('draft');
                $table->foreignId('quran_ayah_id')->nullable()->constrained('quran_ayahs')->restrictOnDelete();
                $table->text('hadith_text_ar')->nullable();
                $table->text('hadith_text_en')->nullable();
                $table->text('hadith_text_dv')->nullable();
                $table->string('hadith_collection')->nullable();
                $table->string('hadith_number')->nullable();
                $table->string('hadith_grading', 64)->nullable();
                $table->string('grading_source')->nullable();
                $table->text('text_en')->nullable();
                $table->text('text_dv')->nullable();
                $table->text('text_ar')->nullable();
                $table->string('attribution')->nullable();
                $table->string('theme_tag')->nullable();
                $table->text('notes_internal')->nullable();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['publish_date', 'content_type']);
                $table->index(['status', 'publish_date']);
                $table->index('theme_tag');
            });
        }

        Permission::firstOrCreate(['name' => 'daily_content.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'daily_content.approve', 'guard_name' => 'web']);

        foreach (['super_admin', 'admin', 'headmaster', 'supervisor'] as $roleName) {
            if (! Role::query()->where('name', $roleName)->exists()) {
                continue;
            }
            Role::findByName($roleName)->givePermissionTo([
                'daily_content.manage',
                'daily_content.approve',
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_contents');
    }
};
