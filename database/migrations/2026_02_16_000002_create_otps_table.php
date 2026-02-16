<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_contact_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_contact_id')->constrained()->onDelete('cascade');
            $table->enum('purpose', ['verify_contact', 'password_reset']);
            $table->enum('channel', ['sms', 'email']);
            $table->string('code_hash', 255);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamps();

            $table->index(['user_contact_id', 'purpose']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_contact_otps');
    }
};
