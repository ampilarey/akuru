<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('funnel_events')) {
            return;
        }

        Schema::create('funnel_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->string('name', 32);
            $table->string('source', 16)->default('server');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'name']);
            $table->index(['name', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_events');
    }
};
