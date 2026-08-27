<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contact_inquiries')) {
            Schema::create('contact_inquiries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inquiry_type_id')->constrained('inquiry_types')->restrictOnDelete();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('subject')->nullable();
                $table->text('message');
                $table->string('status')->default('new');
                $table->string('priority')->default('medium');
                $table->text('admin_notes')->nullable();
                $table->text('response')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->unsignedBigInteger('assigned_to')->nullable();
                $table->string('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->json('custom_fields')->nullable();
                $table->boolean('is_spam')->default(false);
                $table->unsignedInteger('spam_score')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
                $table->index('inquiry_type_id');
            });
        }

        $now = now();
        foreach ([
            [
                'key' => 'conversion.seats_hide_above',
                'value' => '20',
                'label' => 'Hide seats-left badge above this remaining count',
            ],
            [
                'key' => 'conversion.seats_exact_at_or_below',
                'value' => '10',
                'label' => 'Show exact “N seats left” at or below this remaining count',
            ],
            [
                'key' => 'conversion.deadline_badge_days',
                'value' => '14',
                'label' => 'Show enrollment-deadline countdown at or below this many days',
            ],
        ] as $row) {
            DB::table('settings')->insertOrIgnore([
                'key' => $row['key'],
                'value' => $row['value'],
                'type' => 'string',
                'group' => 'conversion',
                'label' => $row['label'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_inquiries');
        DB::table('settings')->where('group', 'conversion')->delete();
    }
};
