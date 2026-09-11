<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E7 — linked accounts and the switcher.
 *
 * The other half of E7, the routing fix, **already shipped**:
 * `ResolveDashboardLandingAction` replaced the `elseif` chain that let branch
 * order decide where a teacher-parent landed, and it exposes the identity the
 * landing did not choose. `docs/EDUPAGE_FEATURES_PLAN.md` still listed that as
 * outstanding — the seventeenth time that document has recorded shipped work as
 * missing, and it is corrected in this slice.
 *
 * What was genuinely missing is switching *between* two accounts the same
 * person owns. A teacher who is also a parent has two real accounts, and today
 * must log out and back in to see their own child's attendance.
 *
 * **The link is a claim that two accounts are one human, so it is only ever
 * created by proving both.** You are signed into the first; you supply the
 * second's credentials. Nothing an administrator can do creates a link, because
 * an administrator cannot know that two accounts are the same person — and a
 * link they could create would be an impersonation tool wearing a different
 * name.
 *
 * Rows are **reciprocal**: proving you own both earns switching in both
 * directions, and unlinking removes both. A one-way link would let somebody
 * reach an account that cannot reach back, which is impersonation again.
 *
 * `account_link_events` is append-only and records **failed link attempts as
 * well as successful ones**. A link form takes a password, so it is a
 * credential-checking endpoint: somebody trying passwords against it is the
 * event worth seeing, and it is exactly the one a success-only log misses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linked_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('linked_user_id')->constrained('users')->cascadeOnDelete();

            // Never null in practice: a row is only written once both sets of
            // credentials have been proved. The column exists so an unverified
            // link is representable and refused, rather than unrepresentable
            // and assumed.
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'linked_user_id']);
            $table->index('user_id');
        });

        Schema::create('account_link_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Null when a link attempt named an identifier that matches no
            // account — there is nobody to point at, and that attempt is
            // precisely the one worth recording.
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('action', 20);
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_link_events');
        Schema::dropIfExists('linked_accounts');
    }
};
