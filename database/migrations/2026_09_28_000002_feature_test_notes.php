<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The owner's testing log for the feature list (System → Feature testing,
 * `ListFeatureWalkthroughAction`): every time a tester marks a feature as
 * working, broken or blocked, with a comment, a row is added. Nothing is
 * edited or deleted, so what was found and when stays readable; a
 * feature's current state is its latest row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_test_notes', function (Blueprint $table) {
            $table->id();
            $table->string('item_key', 40)->index();
            $table->string('status', 16);
            $table->text('comment')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_test_notes');
    }
};
