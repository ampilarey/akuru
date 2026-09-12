<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LIBRARY_PLAN §9.2 / §30.3 — the half of the protected reader L2 did not
 * build: "detect rapid page opening, detect multi-device/session abuse, limit
 * simultaneous sessions, log suspicious activity".
 *
 * **IP and user agent are stored hashed, never raw.** This is a school; some
 * readers are children. §30 is a security section, not a surveillance one, and
 * every question it asks — is this the same device? how many at once? — is
 * answerable from a hash. A raw address would add nothing except a liability.
 * The hash is peppered with the app key so it cannot be reversed by trying
 * candidate addresses against it.
 *
 * The table is append-only (rule 12's spirit): a reading event is evidence,
 * and evidence that can be edited is not evidence. Old rows are pruned by age,
 * never rewritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_reading_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('library_item_id')->constrained('library_items')->cascadeOnDelete();
            $table->unsignedInteger('page_number');
            // Hashes, not identities. 64 hex chars of sha256.
            $table->string('session_hash', 64)->nullable();
            $table->string('device_hash', 64)->nullable();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            // The two questions the detector asks: "what has this reader done
            // on this item lately" and "how many devices has this reader used".
            $table->index(['user_id', 'library_item_id', 'occurred_at'], 'lib_read_user_item_time');
            $table->index(['user_id', 'occurred_at'], 'lib_read_user_time');
        });

        Schema::create('library_reading_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('library_item_id')->nullable()->constrained('library_items')->nullOnDelete();
            // 'rapid_pages' | 'many_devices' | 'concurrent_sessions'
            $table->string('signal', 32)->index();
            $table->unsignedInteger('observed')->default(0);
            $table->unsignedInteger('threshold')->default(0);
            $table->text('detail')->nullable();
            // An alert is a question for a person, not a verdict. Nothing is
            // blocked by its existence; `reviewed_at` records that somebody
            // looked, and the row is kept either way.
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('outcome', 32)->nullable();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->timestamps();

            // One open alert per reader per item per signal — a reader who
            // flips pages fast for ten minutes is one concern, not six hundred.
            $table->index(['user_id', 'signal', 'reviewed_at'], 'lib_alert_user_signal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_reading_alerts');
        Schema::dropIfExists('library_reading_events');
    }
};
