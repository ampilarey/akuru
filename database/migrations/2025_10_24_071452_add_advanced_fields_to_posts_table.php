<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Add new fields
            $table->foreignId('post_category_id')->nullable()->after('id')->constrained()->onDelete('set null');
            $table->boolean('is_featured')->default(false)->after('author_id');
            $table->boolean('is_pinned')->default(false)->after('is_featured');
            $table->integer('view_count')->default(0)->after('is_pinned');
            $table->integer('like_count')->default(0)->after('view_count');
            $table->integer('share_count')->default(0)->after('like_count');
            $table->string('reading_time')->nullable()->after('share_count');
            $table->json('tags')->nullable()->after('reading_time');
            $table->text('meta_description')->nullable()->after('tags');
            $table->text('meta_keywords')->nullable()->after('meta_description');
            
            // Add indexes
            $table->index(['is_featured', 'is_published']);
            $table->index(['post_category_id', 'is_published']);
            $table->index('view_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['is_featured', 'is_published']);
            $table->dropIndex(['post_category_id', 'is_published']);
            $table->dropIndex('view_count');
            
            $table->dropColumn([
                'post_category_id',
                'is_featured',
                'is_pinned',
                'view_count',
                'like_count',
                'share_count',
                'reading_time',
                'tags',
                'meta_description',
                'meta_keywords',
            ]);
        });
    }
};