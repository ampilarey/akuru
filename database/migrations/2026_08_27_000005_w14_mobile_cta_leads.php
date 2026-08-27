<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('courses', 'whatsapp_number')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->string('whatsapp_number', 32)->nullable()->after('seats');
            });
        }

        if (! Schema::hasColumn('courses', 'syllabus_media_file_id')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->unsignedBigInteger('syllabus_media_file_id')->nullable()->after('whatsapp_number');
                $table->foreign('syllabus_media_file_id')->references('id')->on('media_files')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('leads')) {
            Schema::create('leads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
                $table->string('name');
                $table->string('mobile', 30);
                $table->string('email')->nullable();
                $table->string('source', 32);
                $table->string('status', 32)->default('new');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['course_id', 'created_at']);
                $table->index(['source', 'status']);
            });
        }

        $now = now();
        DB::table('settings')->insertOrIgnore([
            'key' => 'conversion.whatsapp_number',
            'value' => '',
            'type' => 'string',
            'group' => 'conversion',
            'label' => 'Default WhatsApp number (digits with country code). Blank courses fall back to this, then contact viber.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');

        if (Schema::hasColumn('courses', 'syllabus_media_file_id')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropForeign(['syllabus_media_file_id']);
                $table->dropColumn('syllabus_media_file_id');
            });
        }

        if (Schema::hasColumn('courses', 'whatsapp_number')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropColumn('whatsapp_number');
            });
        }

        DB::table('settings')->where('key', 'conversion.whatsapp_number')->delete();
    }
};
