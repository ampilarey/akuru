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
        Schema::create('fee_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('default_amount', 10, 2);
            $table->string('currency', 3)->default('MVR'); // Maldivian Rufiyaa
            $table->enum('type', ['tuition', 'registration', 'examination', 'activity', 'transport', 'books', 'uniform', 'other'])->default('other');
            $table->enum('frequency', ['one_time', 'monthly', 'quarterly', 'semester', 'annual'])->default('one_time');
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('is_active')->default(true);
            $table->json('applicable_grades')->nullable(); // Which grades this fee applies to
            $table->timestamps();
            
            $table->index(['type', 'is_active']);
            $table->index(['frequency', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_items');
    }
};