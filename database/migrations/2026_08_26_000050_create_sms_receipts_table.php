<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 16)->default('sms');
            $table->string('type', 40);
            $table->string('reference', 191)->nullable()->index();
            $table->string('phone', 32)->nullable();
            $table->text('body')->nullable();
            $table->string('driver', 20);
            $table->boolean('success');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_receipts');
    }
};
