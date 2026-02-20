<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // string|json|boolean
            $table->string('group', 50)->default('general');
            $table->string('label')->nullable();
            $table->timestamps();
        });

        // Seed default settings
        $defaults = [
            ['key' => 'phone',        'value' => '+960 797 2434',                       'group' => 'contact',  'label' => 'Phone number'],
            ['key' => 'whatsapp',     'value' => '9607972434',                          'group' => 'contact',  'label' => 'WhatsApp number (digits only)'],
            ['key' => 'email',        'value' => 'info@akuru.edu.mv',                   'group' => 'contact',  'label' => 'Contact email'],
            ['key' => 'address',      'value' => "Malé, Republic of Maldives",          'group' => 'contact',  'label' => 'Office address'],
            ['key' => 'facebook',     'value' => '',                                    'group' => 'social',   'label' => 'Facebook URL'],
            ['key' => 'instagram',    'value' => '',                                    'group' => 'social',   'label' => 'Instagram URL'],
            ['key' => 'twitter',      'value' => '',                                    'group' => 'social',   'label' => 'X / Twitter URL'],
            ['key' => 'youtube',      'value' => '',                                    'group' => 'social',   'label' => 'YouTube URL'],
            ['key' => 'site_name',    'value' => 'Akuru Institute',                     'group' => 'seo',      'label' => 'Site name'],
            ['key' => 'tagline',      'value' => 'Learn Quran, Arabic & Islamic Studies', 'group' => 'seo',    'label' => 'Site tagline'],
            ['key' => 'hero_title',   'value' => 'Welcome to Akuru Institute',          'group' => 'homepage', 'label' => 'Hero title'],
            ['key' => 'hero_subtitle','value' => 'Learn Quran, Arabic, and Islamic Studies in the Maldives', 'group' => 'homepage', 'label' => 'Hero subtitle'],
        ];

        foreach ($defaults as $row) {
            DB::table('settings')->insertOrIgnore(array_merge($row, [
                'type' => 'string',
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
