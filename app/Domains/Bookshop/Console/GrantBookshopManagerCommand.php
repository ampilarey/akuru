<?php

namespace App\Domains\Bookshop\Console;

use App\Domains\Bookshop\Actions\ManageBookshopTeamAction;
use Illuminate\Console\Command;

/**
 * BOOKSHOP_PLAN B10b: make (or unmake) a Bookstore admin from the server,
 * e.g. the first one on production. The account must already exist — sign
 * up or be created first; no password is made or printed here.
 */
class GrantBookshopManagerCommand extends Command
{
    protected $signature = 'bookshop:grant-manager {email : The account\'s email} {--revoke : Take the role away instead}';

    protected $description = 'Give an existing account the Bookstore admin role (bookshop_manager), or take it away';

    public function handle(): int
    {
        $userModel = config('auth.providers.users.model');
        $user = $userModel::query()->where('email', strtolower(trim((string) $this->argument('email'))))->first();
        if ($user === null) {
            $this->error('No account with that email. Create it first (or add it from /admin/bookshop as a full admin).');

            return self::FAILURE;
        }
        if ($this->option('revoke')) {
            $user->removeRole(ManageBookshopTeamAction::ROLE);
            $this->info("{$user->name} is no longer a Bookstore admin.");

            return self::SUCCESS;
        }
        app(ManageBookshopTeamAction::class)->add((string) $user->email);
        $this->info("{$user->name} is now a Bookstore admin: /admin/bookshop.");

        return self::SUCCESS;
    }
}
