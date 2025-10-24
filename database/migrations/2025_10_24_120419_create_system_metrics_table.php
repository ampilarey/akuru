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
        Schema::create('system_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('metric_name'); // 'cpu_usage', 'memory_usage', 'disk_usage', 'active_users', etc.
            $table->string('metric_category'); // 'system', 'performance', 'usage', 'security', etc.
            $table->decimal('metric_value', 15, 4);
            $table->string('metric_unit')->nullable(); // 'percent', 'bytes', 'count', 'seconds', etc.
            $table->json('metadata')->nullable(); // Additional context data
            $table->timestamp('recorded_at');
            $table->timestamps();
            
            $table->index(['metric_name', 'recorded_at']);
            $table->index(['metric_category', 'recorded_at']);
            $table->index('recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_metrics');
    }
};