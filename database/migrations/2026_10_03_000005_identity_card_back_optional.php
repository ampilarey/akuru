<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The front of the card is enough (C17 slice R3, STATUS §5of). The owner,
 * 2026-10-03: "all the important informations are on front page only". The
 * back becomes optional for every identity check — learners, shops, writers,
 * lenders — so a card can be on file with no back side. Additive (rule 9):
 * the column only stops being required; every row keeps what it has.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('identity_verifications', function (Blueprint $table) {
            $table->unsignedBigInteger('back_media_file_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Left optional: a card filed front-only cannot be made to have had a back.
    }
};
