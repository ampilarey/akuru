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
        // Add read_at column to messages table if it doesn't exist
        if (Schema::hasTable('messages') && !Schema::hasColumn('messages', 'read_at')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->timestamp('read_at')->nullable()->after('content');
                $table->index(['read_at']);
            });
        }

        // Create message_attachments table
        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->onDelete('cascade');
            $table->string('filename');
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type');
            $table->integer('size'); // in bytes
            $table->timestamps();
            
            $table->index(['message_id']);
        });

        // Enhance existing tables if they exist
        if (Schema::hasTable('message_threads')) {
            Schema::table('message_threads', function (Blueprint $table) {
                if (!Schema::hasColumn('message_threads', 'tags')) {
                    $table->json('tags')->nullable()->after('subject');
                }
            });
        }
        
        // Create notifications table for explicit notifications
        Schema::create('custom_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type'); // announcement, assignment, quiz, substitution, etc.
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Additional data
            $table->string('action_url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('done_at')->nullable(); // For "mark as done" functionality
            $table->boolean('is_important')->default(false);
            $table->timestamps();
            
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'done_at']);
            $table->index(['type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_notifications');
        Schema::dropIfExists('message_attachments');
        
        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropColumn('read_at');
            });
        }
        
        if (Schema::hasTable('message_threads')) {
            Schema::table('message_threads', function (Blueprint $table) {
                if (Schema::hasColumn('message_threads', 'tags')) {
                    $table->dropColumn('tags');
                }
            });
        }
    }
};