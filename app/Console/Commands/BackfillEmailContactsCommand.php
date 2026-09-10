<?php

namespace App\Console\Commands;

use App\Domains\Identity\Actions\EnsureVerifiedEmailContactAction;
use App\Domains\Identity\Models\User;
use Illuminate\Console\Command;

/**
 * Give existing accounts the email contact row that OTP password reset needs.
 *
 * Every account created before this was fixed — every teacher and student made
 * through the People screens — has `users.email` and no `user_contacts` row, so
 * "reset my password by email" silently does nothing for them. New accounts are
 * handled at creation; this is for the ones already in the database.
 *
 * Idempotent and additive (rule 9): it creates rows and changes none, and
 * running it twice does nothing the second time. Reports conflicts rather than
 * resolving them — two accounts sharing an email address needs a human.
 */
class BackfillEmailContactsCommand extends Command
{
    protected $signature = 'identity:backfill-email-contacts {--dry-run : Report what would change without writing}';

    protected $description = 'Mirror users.email into user_contacts so password reset by email can find the account';

    public function handle(): int
    {
        $action = app(EnsureVerifiedEmailContactAction::class);
        $dryRun = (bool) $this->option('dry-run');

        $created = 0;
        $already = 0;
        $skipped = 0;
        $conflicts = [];

        User::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($users) use ($action, $dryRun, &$created, &$already, &$skipped, &$conflicts): void {
                foreach ($users as $user) {
                    $existing = $user->contacts()
                        ->where('type', 'email')
                        ->exists();

                    if ($existing) {
                        $already++;

                        continue;
                    }

                    if ($dryRun) {
                        $created++;

                        continue;
                    }

                    if ($action->execute($user) === null) {
                        // Either an unusable address, or one already held by a
                        // different account.
                        $skipped++;
                        $conflicts[] = $user->id.' <'.$user->email.'>';

                        continue;
                    }

                    $created++;
                }
            });

        $this->info(($dryRun ? 'Would create' : 'Created').': '.$created);
        $this->info('Already had a contact: '.$already);

        if ($skipped > 0) {
            $this->warn('Skipped (unusable address, or the address belongs to another account): '.$skipped);
            foreach (array_slice($conflicts, 0, 20) as $line) {
                $this->line('  '.$line);
            }
            if (count($conflicts) > 20) {
                $this->line('  … and '.(count($conflicts) - 20).' more');
            }
            $this->warn('These accounts still cannot reset by email. Two accounts sharing an address needs a human.');
        }

        return self::SUCCESS;
    }
}
