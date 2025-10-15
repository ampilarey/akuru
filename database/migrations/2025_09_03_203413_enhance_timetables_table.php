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
        Schema::table('timetables', function (Blueprint $table) {
            $table->foreignId('period_id')->nullable()->after('class_id')->constrained()->onDelete('cascade');
            $table->date('start_date')->nullable()->after('end_time');
            $table->date('end_date')->nullable()->after('start_date');
            $table->enum('frequency', ['daily', 'weekly', 'monthly'])->default('weekly')->after('end_date');
            $table->json('recurring_days')->nullable()->after('frequency'); // For specific days
            $table->boolean('is_recurring')->default(true)->after('recurring_days');
            $table->string('color')->default('#3B82F6')->after('is_recurring'); // For calendar display
            $table->text('description')->nullable()->after('color');
            $table->text('description_arabic')->nullable()->after('description');
            $table->text('description_dhivehi')->nullable()->after('description_arabic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timetables', function (Blueprint $table) {
            $table->dropForeign(['period_id']);
            $table->dropColumn([
                'period_id', 'start_date', 'end_date', 'frequency', 
                'recurring_days', 'is_recurring', 'color', 'description',
                'description_arabic', 'description_dhivehi'
            ]);
        });
    }
};