<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prayer_categories')) {
            Schema::create('prayer_categories', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('prayer_islands')) {
            Schema::create('prayer_islands', function (Blueprint $table) {
                $table->id();
                $table->foreignId('category_id')->constrained('prayer_categories')->restrictOnDelete();
                $table->string('atoll')->default('');
                $table->string('atoll_latin')->default('');
                $table->string('name')->default('');
                $table->string('name_latin')->default('');
                $table->integer('offset_minutes')->default(0);
                $table->decimal('latitude', 10, 7)->default(0);
                $table->decimal('longitude', 10, 7)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['is_active', 'name_latin']);
            });
        }

        if (! Schema::hasTable('prayer_times')) {
            Schema::create('prayer_times', function (Blueprint $table) {
                $table->id();
                $table->foreignId('category_id')->constrained('prayer_categories')->restrictOnDelete();
                $table->unsignedSmallInteger('day_of_year');
                $table->integer('fajr');
                $table->integer('sunrise');
                $table->integer('dhuhr');
                $table->integer('asr');
                $table->integer('maghrib');
                $table->integer('isha');
                $table->timestamps();
                $table->unique(['category_id', 'day_of_year']);
            });
        }

        if (! Schema::hasTable('prayer_recipient_groups')) {
            Schema::create('prayer_recipient_groups', function (Blueprint $table) {
                $table->id();
                $table->string('name_en');
                $table->string('name_dv')->default('');
                $table->string('name_ar')->default('');
                $table->text('description')->nullable();
                $table->json('member_refs')->nullable();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('prayer_broadcasts')) {
            Schema::create('prayer_broadcasts', function (Blueprint $table) {
                $table->id();
                $table->string('mode', 16);
                $table->foreignId('island_id')->constrained('prayer_islands')->restrictOnDelete();
                $table->date('date_from')->nullable();
                $table->date('date_to')->nullable();
                $table->dateTime('scheduled_at')->nullable();
                $table->string('status', 16)->default('draft');
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->json('message_template')->nullable();
                $table->foreignId('recipient_group_id')->nullable()->constrained('prayer_recipient_groups')->nullOnDelete();
                $table->json('recipient_refs')->nullable();
                $table->string('language', 8)->default('en');
                $table->unsignedInteger('sent_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->decimal('estimated_cost', 10, 2)->nullable();
                $table->json('preview_snapshot')->nullable();
                $table->string('idempotency_key')->nullable()->unique();
                $table->timestamps();
                $table->index(['mode', 'status']);
            });
        }

        if (! Schema::hasTable('prayer_broadcast_recipients')) {
            Schema::create('prayer_broadcast_recipients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('prayer_broadcast_id')->constrained('prayer_broadcasts')->restrictOnDelete();
                $table->json('contact_ref');
                $table->string('phone');
                $table->string('status', 16)->default('pending');
                $table->text('message_body')->nullable();
                $table->decimal('cost', 10, 2)->nullable();
                $table->dateTime('sent_at')->nullable();
                $table->text('error')->nullable();
                $table->timestamps();
                $table->index(['prayer_broadcast_id', 'status']);
            });
        }

        Permission::firstOrCreate(['name' => 'prayer.manage', 'guard_name' => 'web']);
        foreach (['super_admin', 'admin', 'headmaster', 'supervisor'] as $roleName) {
            if (! Role::query()->where('name', $roleName)->exists()) {
                continue;
            }
            Role::findByName($roleName)->givePermissionTo('prayer.manage');
        }

        $now = now();
        foreach ([
            ['key' => 'prayer_times_cache_version', 'value' => '1', 'type' => 'string', 'group' => 'prayer', 'label' => 'Prayer times cache version'],
            ['key' => 'prayer.public_page_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'prayer', 'label' => 'Public prayer page'],
            ['key' => 'prayer.sms_tariff_mvr', 'value' => '0.40', 'type' => 'string', 'group' => 'prayer', 'label' => 'SMS tariff MVR'],
        ] as $row) {
            DB::table('settings')->insertOrIgnore(array_merge($row, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prayer_broadcast_recipients');
        Schema::dropIfExists('prayer_broadcasts');
        Schema::dropIfExists('prayer_recipient_groups');
        Schema::dropIfExists('prayer_times');
        Schema::dropIfExists('prayer_islands');
        Schema::dropIfExists('prayer_categories');
    }
};
