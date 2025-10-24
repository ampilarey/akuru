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
        Schema::create('dashboard_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('metric_type'); // 'page_views', 'login_count', 'course_progress', etc.
            $table->string('metric_name'); // 'Homepage Views', 'Login Count', 'Quran Progress', etc.
            $table->decimal('metric_value', 15, 4)->default(0);
            $table->json('metadata')->nullable(); // Additional data like page paths, course IDs, etc.
            $table->date('recorded_date');
            $table->timestamps();
            
            $table->index(['user_id', 'metric_type', 'recorded_date']);
            $table->index(['metric_type', 'recorded_date']);
            $table->unique(['user_id', 'metric_type', 'recorded_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_analytics');
    }
};