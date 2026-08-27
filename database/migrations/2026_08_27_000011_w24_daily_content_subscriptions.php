<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('daily_content_subscriptions')) {
            Schema::create('daily_content_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('channel', 16);
                $table->json('content_types');
                $table->string('language', 8)->default('en');
                $table->time('send_time')->default('06:00:00');
                $table->string('status', 16)->default('active');
                $table->string('unsubscribe_token', 64)->unique();
                $table->timestamp('unsubscribed_at')->nullable();
                $table->string('unsubscribe_reason', 16)->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'channel']);
                $table->index(['status', 'channel', 'send_time']);
            });
        }

        if (! Schema::hasTable('daily_content_deliveries')) {
            Schema::create('daily_content_deliveries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subscription_id')->constrained('daily_content_subscriptions')->cascadeOnDelete();
                $table->date('send_date');
                $table->string('channel', 16);
                $table->string('status', 32);
                $table->text('error')->nullable();
                $table->timestamps();

                $table->unique(['subscription_id', 'send_date']);
                $table->index(['send_date', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_content_deliveries');
        Schema::dropIfExists('daily_content_subscriptions');
    }
};
