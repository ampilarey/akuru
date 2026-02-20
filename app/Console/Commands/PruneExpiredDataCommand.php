<?php

namespace App\Console\Commands;

use App\Models\Otp;
use App\Models\CourseEnrollment;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PruneExpiredDataCommand extends Command
{
    protected $signature = 'akuru:prune-expired
                            {--dry-run : Preview without deleting}';

    protected $description = 'Delete expired OTPs and cancel stale draft/pending-payment enrollments';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // --- Expired OTPs (consumed, expired, or > 24 h old) ---
        $otpQuery = Otp::where(function ($q) {
            $q->whereNotNull('consumed_at')
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
        $pendingQuery = CourseEnrollment::where('status', 'pending')
            ->where('created_at', '<', now()->subHours(24))
            ->whereDoesntHave('payments', fn($q) => $q->whereIn('status', ['paid', 'completed']));

        $pendingCount = $pendingQuery->count();
        $this->line("Stale pending-payment enrollments to cancel: {$pendingCount}");
        if (! $dryRun) {
            $pendingQuery->update(['status' => 'cancelled']);
        }

        if ($dryRun) {
            $this->warn('Dry-run mode — no changes made.');
        } else {
            $this->info('Pruning complete.');
        }

        return 0;
    }
}
