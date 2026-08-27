<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('daily_contents') && ! Schema::hasColumn('daily_contents', 'share_card_path')) {
            Schema::table('daily_contents', function (Blueprint $table) {
                $table->string('share_card_path')->nullable()->after('approved_by');
            });
        }

        $now = now();
        DB::table('settings')->insertOrIgnore([
            'key' => 'daily.homepage_layout',
            'value' => 'stacked',
            'type' => 'string',
            'group' => 'daily_settings',
            'label' => 'Homepage daily widget: stacked (all types) or rotate (one type per day)',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('daily_contents') && Schema::hasColumn('daily_contents', 'share_card_path')) {
            Schema::table('daily_contents', function (Blueprint $table) {
                $table->dropColumn('share_card_path');
            });
        }
        DB::table('settings')->where('key', 'daily.homepage_layout')->delete();
    }
};
