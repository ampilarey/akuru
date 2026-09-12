<?php

namespace App\Console\Commands;

use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Identity\Models\Otp;
use App\Domains\Library\Models\LibraryReadingEvent;
use Illuminate\Console\Command;

class PruneExpiredDataCommand extends Command
{
    protected $signature = 'akuru:prune-expired
                            {--dry-run : Preview without deleting}';

    protected $description = 'Delete expired OTPs, prune old library reading events, and cancel stale draft/pending-payment enrollments';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // --- Expired OTPs (used, expired, or > 24 h old) ---
        //
        // The column is `used_at`. It was written as `consumed_at`, which does
        // not exist on `user_contact_otps`, so this command threw on its FIRST
        // query — every hour, since it was scheduled. Nothing after this point
        // had ever run either: no OTP was ever pruned and no stale enrolment
        // was ever cancelled. Found by a test that called the command for an
        // unrelated reason; no test had ever invoked it.
        $otpQuery = Otp::where(function ($q) {
            $q->whereNotNull('used_at')
                ->orWhere('expires_at', '<', now());
        })->where('created_at', '<', now()->subDay());

        $otpCount = $otpQuery->count();
        $this->line("Expired OTPs to delete: {$otpCount}");
        if (! $dryRun) {
            $otpQuery->delete();
        }

        // --- Stale draft enrollments (status = 'draft', older than 2 hours) ---
        $draftQuery = CourseEnrollment::where('status', 'draft')
            ->where('created_at', '<', now()->subHours(2));

        $draftCount = $draftQuery->count();
        $this->line("Stale draft enrollments to delete: {$draftCount}");
        if (! $dryRun) {
            $draftQuery->delete();
        }

        // --- Stale pending-payment enrollments (older than 24 h, never paid) ---
        //
        // Second bug in the same never-run command: `payments()` is not a
        // relation on `CourseEnrollment` (there is `payment()` and
        // `paymentItem()`), so this threw `BadMethodCallException`.
        //
        // Rewritten against `payment_status`, which is the field
        // `isPaymentConfirmed()` reads and the field the BML webhook sets
        // (rule 12). Cancelling an enrolment whose payment HAD been confirmed
        // would take a paid course away from a student, so the test below pins
        // that a confirmed one survives.
        $pendingQuery = CourseEnrollment::where('status', 'pending')
            ->where('created_at', '<', now()->subHours(24))
            ->where('payment_status', '!=', 'confirmed');

        $pendingCount = $pendingQuery->count();
        $this->line("Stale pending-payment enrollments to cancel: {$pendingCount}");
        if (! $dryRun) {
            $pendingQuery->update(['status' => 'cancelled']);
        }

        // --- Library reading events past their retention window (L2b, §30.3) ---
        // These exist to answer "what happened lately". Keeping a reader's
        // page-by-page history indefinitely serves no purpose the plan asks
        // for, and several of these readers are children. Alerts are NOT
        // pruned: an alert is a decision somebody took, and the record of it
        // outlives the events that raised it.
        $retentionDays = max(1, (int) config('library.abuse.retention_days', 90));
        $eventQuery = LibraryReadingEvent::query()
            ->where('occurred_at', '<', now('Indian/Maldives')->subDays($retentionDays));

        $eventCount = $eventQuery->count();
        $this->line("Library reading events older than {$retentionDays} days to delete: {$eventCount}");
        if (! $dryRun) {
            $eventQuery->delete();
        }

        if ($dryRun) {
            $this->warn('Dry-run mode — no changes made.');
        } else {
            $this->info('Pruning complete.');
        }

        return 0;
    }
}
