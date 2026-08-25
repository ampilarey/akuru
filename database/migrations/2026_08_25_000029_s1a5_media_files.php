<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1A.5 — private media files for course image/audio/video/PDF blocks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('mime', 127);
            $table->string('original_name');
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visibility', 20)->default('private');
            $table->string('process_status', 20)->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['visibility', 'process_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
