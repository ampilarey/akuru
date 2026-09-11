<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * E19 — student sensitive information (health and welfare notes).
 *
 * **The plan is blunt about this one:** *"Needs a policy decision before a
 * schema: who may read, who may write, retention, and whether it is
 * exportable. This is the one module where building first and deciding later
 * is actively wrong."*
 *
 * That warning is respected by making every undecided question **fail closed
 * and stay visible**, rather than by picking answers quietly:
 *
 *  - **Who may read** — a dedicated `sensitive.read` / `sensitive.write` pair,
 *    and `admin` is deliberately **not** granted them here. `RoleSeeder` gives
 *    `admin` `Permission::all()`, so doing nothing would have handed every
 *    admin account every child's health note *by accident*. Access starts with
 *    `super_admin` and `headmaster` only. Widening it is one line in a
 *    migration once the Institute decides; narrowing it after the fact is a
 *    disclosure.
 *  - **Retention** — `review_on` lets the school say when a note should be
 *    looked at again, and `archived_at` takes it out of use. **Nothing deletes
 *    automatically.** Auto-expiring a child's allergy on a guessed retention
 *    rule is far worse than keeping it one term too long.
 *  - **Whether it is exportable** — no CSV, deliberately departing from the
 *    repo convention that every listing gets one. A convention that exists to
 *    help an office move data is the wrong default for the one table where
 *    "somebody exported it" is the incident.
 *
 * `sensitive_note_views` logs **every read**, not every write. Whichever way
 * the policy lands, *"who looked at my child's record, and when?"* is the
 * question that will be asked, and it is unanswerable retrospectively.
 *
 * Carries `academic_year_id` (rule 10).
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $permissions = ['sensitive.read', 'sensitive.write'];

    public function up(): void
    {
        Schema::create('student_sensitive_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();

            $table->string('category', 20);
            $table->string('summary');
            $table->text('body')->nullable();

            // When somebody should look at this again. Set by a human, never
            // enforced by a job — see the class comment on retention.
            $table->date('review_on')->nullable();

            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['student_id', 'archived_at']);
            $table->index('review_on');
        });

        Schema::create('sensitive_note_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('viewed_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('viewed_at');
            $table->timestamps();

            $table->index(['student_id', 'viewed_at']);
        });

        foreach ($this->permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Deliberately not `admin`. See the class comment.
        foreach (['super_admin', 'headmaster'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->givePermissionTo($this->permissions);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sensitive_note_views');
        Schema::dropIfExists('student_sensitive_notes');
    }
};
