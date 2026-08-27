<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * L-track slice L1 (LIBRARY_PLAN §39.1): item model, categories, tags,
 * authors, plus the library.manage permission (W3 seeding precedent).
 * Columns for later phases (price, access types beyond free, writer,
 * commission) exist per §35.1 but stay unenforced until their phase —
 * additive from day one. Originals are private media (§36); free reading in
 * L1 is HTML body content.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('library_categories')->nullOnDelete();
            $table->string('name');
            $table->string('name_dv')->nullable();
            $table->string('name_ar')->nullable();
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('library_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('library_items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('abstract')->nullable();
            $table->string('content_type', 30);
            $table->string('access_type', 20)->default('free_public');
            $table->decimal('price', 10, 2)->nullable();
            $table->string('currency', 3)->default('MVR');
            $table->string('language', 5)->default('en');
            $table->foreignId('library_category_id')->nullable()->constrained('library_categories')->nullOnDelete();
            $table->string('cover_image')->nullable();
            $table->longText('body')->nullable();
            $table->foreignId('pdf_media_file_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('writer_id')->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->unsignedInteger('reading_time')->nullable();
            $table->boolean('preview_enabled')->default(false);
            $table->unsignedInteger('preview_pages')->nullable();
            $table->string('commission_type', 20)->nullable();
            $table->decimal('commission_value', 10, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['content_type', 'status']);
            $table->index('library_category_id');
        });

        Schema::create('library_item_tag', function (Blueprint $table) {
            $table->foreignId('library_item_id')->constrained('library_items')->cascadeOnDelete();
            $table->foreignId('library_tag_id')->constrained('library_tags')->cascadeOnDelete();

            $table->unique(['library_item_id', 'library_tag_id']);
        });

        Schema::create('library_item_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_item_id')->constrained('library_items')->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Permission::firstOrCreate(['name' => 'library.manage', 'guard_name' => 'web']);
        foreach (['super_admin', 'admin'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->givePermissionTo('library.manage');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_item_authors');
        Schema::dropIfExists('library_item_tag');
        Schema::dropIfExists('library_items');
        Schema::dropIfExists('library_tags');
        Schema::dropIfExists('library_categories');
    }
};
