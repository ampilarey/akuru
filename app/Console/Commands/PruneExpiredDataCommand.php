<?php

namespace App\Console\Commands;

use App\Domains\Commerce\Actions\RecordDiscountRedemptionAction;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Identity\Models\Otp;
use App\Domains\Identity\Models\OtpAbuseEvent;
use App\Domains\Library\Models\LibraryPurchase;
use App\Domains\Library\Models\LibraryReadingEvent;
use App\Domains\Library\Models\LibrarySearchLog;
use App\Domains\Settings\Models\DashboardAnalytics;
use App\Domains\Settings\Models\UserActivity;
use Illuminate\Console\Command;

class PruneExpiredDataCommand extends Command
{
    /** Days of page-view bookkeeping kept (`user_activities`, `dashboard_analytics`). */
    public const ACTIVITY_RETENTION_DAYS = 90;

    protected $signature = 'akuru:prune-expired
                            {--dry-run : Preview without deleting}';

    protected $description = 'Delete expired OTPs, prune old library reading events, cancel stale draft/pending-payment enrollments, and release abandoned discount slots';

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
            // Read the ids before deleting: the redemption points at the
            // enrolment, and once the row is gone there is nothing left to
            // match it to.
            $draftIds = (clone $draftQuery)->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $draftQuery->delete();
            $released = app(RecordDiscountRedemptionAction::class)
                ->releaseAbandoned('course_enrollment', $draftIds);
            $this->line("Discount redemptions released from stale drafts: {$released}");
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
            $pendingIds = (clone $pendingQuery)->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $pendingQuery->update(['status' => 'cancelled']);

            // Cancelling frees the seat automatically, because `cancelled` is
            // not an occupying status. The discount slot was not freed by
            // anything at all — `'released'` had no writer in the codebase —
            // so an abandoned checkout burned a use of the code for good.
            $released = app(RecordDiscountRedemptionAction::class)
                ->releaseAbandoned('course_enrollment', $pendingIds);
            $this->line("Discount redemptions released from stale pending enrollments: {$released}");
        }

        // --- Library card purchases never paid (older than 24 h) (STATUS §5pn) ---
        //
        // A reader who chose the card and closed BML's page left a pending
        // purchase and, with a code, a pending redemption — which
        // `ResolveDiscountAction` counts against the code's limits. The
        // enrolments above give their slot back here and the Bookstore when a
        // checkout expires; nothing gave the Library's. The purchase itself
        // stays, `pending`: it moved no money, and a payment that lands late
        // still finds it (and takes the slot back, `confirmLanded`).
        $stalePurchaseIds = LibraryPurchase::query()
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subHours(24))
            ->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $this->line('Stale pending library purchases: '.count($stalePurchaseIds));
        if (! $dryRun) {
            $released = app(RecordDiscountRedemptionAction::class)
                ->releaseAbandoned('library_purchase', $stalePurchaseIds);
            $this->line("Discount redemptions released from stale library purchases: {$released}");
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

        // B14: the shelf's search log goes with the reading events — a term
        // somebody typed is no more worth keeping than a page they opened.
        $searchQuery = LibrarySearchLog::query()->where('created_at', '<', now('Indian/Maldives')->subDays($retentionDays));
        $searchCount = $searchQuery->count();
        $this->line("Library searches older than {$retentionDays} days to delete: {$searchCount}");
        if (! $dryRun) {
            $searchQuery->delete();
        }

        // --- OTP abuse events past retention (SPEC §32) ---
        // These exist so an admin can see a pattern across days, not forever:
        // the contact is already hashed, and an incident from last year tells
        // nobody anything useful about this month's SMS bill.
        $otpRetention = max(1, (int) config('otp.abuse_log_retention_days', 90));
        $abuseQuery = OtpAbuseEvent::query()
            ->where('occurred_at', '<', now('Indian/Maldives')->subDays($otpRetention));

        $abuseCount = $abuseQuery->count();
        $this->line("OTP abuse events older than {$otpRetention} days to delete: {$abuseCount}");
        if (! $dryRun) {
            $abuseQuery->delete();
        }

        // --- Page-view bookkeeping past retention (ADMIN_PANEL.md §7 P3) ---
        // `TrackUserActivity` writes a `user_activities` row and a
        // `dashboard_analytics` upsert for every signed-in page view on 943
        // routes, and nothing ever deleted them. One screen (`/analytics`)
        // reads them, and it reads the last 30 days. Ninety keeps a quarter.
        $activityCutoff = now('Indian/Maldives')->subDays(self::ACTIVITY_RETENTION_DAYS);
        $activityQuery = UserActivity::query()->where('performed_at', '<', $activityCutoff);
        $activityCount = $activityQuery->count();
        $this->line('User activity rows older than '.self::ACTIVITY_RETENTION_DAYS." days to delete: {$activityCount}");
        if (! $dryRun) {
            $activityQuery->delete();
        }
        $metricQuery = DashboardAnalytics::query()->where('recorded_date', '<', $activityCutoff->toDateString());
        $metricCount = $metricQuery->count();
        $this->line('Dashboard metric rows older than '.self::ACTIVITY_RETENTION_DAYS." days to delete: {$metricCount}");
        if (! $dryRun) {
            $metricQuery->delete();
        }

        if ($dryRun) {
            $this->warn('Dry-run mode — no changes made.');
        } else {
            $this->info('Pruning complete.');
        }

        return 0;
    }
}
