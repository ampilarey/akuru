<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Arabic A.1 — admin-managed letters and harakas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arabic_letters', function (Blueprint $table) {
            $table->id();
            $table->string('key_name', 32)->unique();
            $table->string('arabic_character', 8);
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('arabic_harakas', function (Blueprint $table) {
            $table->id();
            $table->string('key_name', 32)->unique();
            $table->string('symbol', 8);
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $letters = [
            ['alif', 'ا', 'Alif'],
            ['baa', 'ب', 'Baa'],
            ['taa', 'ت', 'Taa'],
            ['thaa', 'ث', 'Thaa'],
            ['jeem', 'ج', 'Jeem'],
            ['haa', 'ح', 'Haa'],
            ['khaa', 'خ', 'Khaa'],
            ['daal', 'د', 'Daal'],
            ['dhaal', 'ذ', 'Dhaal'],
            ['raa', 'ر', 'Raa'],
            ['zaay', 'ز', 'Zaay'],
            ['seen', 'س', 'Seen'],
            ['sheen', 'ش', 'Sheen'],
            ['saad', 'ص', 'Saad'],
            ['daad', 'ض', 'Daad'],
            ['taa_emphatic', 'ط', 'Taa emphatic'],
            ['zaa_emphatic', 'ظ', 'Zaa emphatic'],
            ['ayn', 'ع', 'Ayn'],
            ['ghayn', 'غ', 'Ghayn'],
            ['faa', 'ف', 'Faa'],
            ['qaaf', 'ق', 'Qaaf'],
            ['kaaf', 'ك', 'Kaaf'],
            ['laam', 'ل', 'Laam'],
            ['meem', 'م', 'Meem'],
            ['noon', 'ن', 'Noon'],
            ['haa_final', 'ه', 'Haa'],
            ['waaw', 'و', 'Waaw'],
            ['yaa', 'ي', 'Yaa'],
        ];
        foreach ($letters as $index => [$key, $character, $name]) {
            DB::table('arabic_letters')->insert([
                'key_name' => $key,
                'arabic_character' => $character,
                'display_name' => $name,
                'sort_order' => $index + 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ([
            ['fatha', 'َ', 'Fatha'],
            ['kasra', 'ِ', 'Kasra'],
            ['damma', 'ُ', 'Damma'],
            ['sukoon', 'ْ', 'Sukoon'],
        ] as $index => [$key, $symbol, $name]) {
            DB::table('arabic_harakas')->insert([
                'key_name' => $key,
                'symbol' => $symbol,
                'display_name' => $name,
                'sort_order' => $index + 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('arabic_harakas');
        Schema::dropIfExists('arabic_letters');
    }
};
