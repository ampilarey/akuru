<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * An account found by its name, email, mobile number or ID card number
 * (BACKLOG C22, BH1), for a form that has to name one: the Commerce office's
 * hand credit to a wallet asked for a typed *User ID*. Other domains call it
 * rather than read `users` (rule 3); it gives them only what tells two
 * accounts apart on the screen.
 */
class SearchAccountsAction
{
    /**
     * @return list<array{id: int, name: string, email: ?string, mobile: ?string}>
     */
    public function execute(string $query, int $limit = 12): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $like = '%'.$query.'%';

        return User::query()
            ->with('contacts')
            ->where(function (Builder $q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('national_id', 'like', $like)
                    ->orWhereHas('contacts', fn (Builder $c) => $c->where('value', 'like', $like));
            })
            ->orderBy('name')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (User $user) => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'email' => $user->email ?: $user->contacts->firstWhere('type', 'email')?->value,
                'mobile' => $user->contacts->firstWhere('type', 'mobile')?->value,
            ])
            ->values()
            ->all();
    }
}
