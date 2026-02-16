<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->nullable()->constrained('registration_students')->onDelete('set null');
            $table->foreignId('course_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('MVR');
            $table->enum('status', ['initiated', 'pending', 'confirmed', 'failed', 'cancelled', 'expired', 'refunded'])->default('initiated');
            $table->enum('provider', ['bml'])->default('bml');
            $table->string('merchant_reference', 191)->unique();
            $table->string('provider_reference', 191)->nullable();
            $table->text('redirect_url')->nullable();
            $table->json('callback_payload')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index('provider_reference');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
