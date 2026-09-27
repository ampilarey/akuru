<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use App\Support\Authorization\RoleLabels;
use Illuminate\Database\Eloquent\Builder;

/**
 * Manage users (docs/ADMIN_PANEL.md; C9 slice 2, STATUS §5jd): the account
 * roster the system admin reads — who can sign in, as what, how to reach
 * them, and whether they still can. One query serves the screen and its
 * CSV, so the download cannot drift from the list it claims to copy.
 */
class ListAdminUsersAction
{
    public const PER_PAGE = 25;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters): Builder
    {
        // Newest first; the id breaks ties made in the same second, so two
        // loads of the page agree on the order.
        $query = User::with(['contacts', 'roles'])->latest()->orderByDesc('id');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('national_id', 'like', "%{$search}%")
                    ->orWhereHas('contacts', fn ($c) => $c->where('value', 'like', "%{$search}%"));
            });
        }

        $role = (string) ($filters['role'] ?? '');
        if ($role !== '' && in_array($role, RoleLabels::KNOWN, true)) {
            $query->role($role);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{users: list<array<string, mixed>>, pagination: array<string, mixed>, total: int}
     */
    public function execute(array $filters, int $selfId, ?string $locale = null): array
    {
        $page = $this->query($filters)->paginate(self::PER_PAGE)->withQueryString();

        return [
            'users' => collect($page->items())->map(fn (User $user) => $this->row($user, $selfId, $locale))->values()->all(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
                'prev' => $page->previousPageUrl(),
                'next' => $page->nextPageUrl(),
            ],
            'total' => $page->total(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(User $user, int $selfId, ?string $locale): array
    {
        $role = $user->roles->first()?->name;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'mobile' => $user->contacts->firstWhere('type', 'mobile')?->value,
            'email' => $user->contacts->firstWhere('type', 'email')?->value,
            'identity' => $user->national_id ?? $user->passport,
            'role' => $role,
            'role_label' => $role !== null ? RoleLabels::label($role, $locale) : null,
            'is_active' => (bool) $user->is_active,
            'is_self' => $user->id === $selfId,
            // Protected from deletion: the actor, and any System admin.
            'is_protected' => $user->id === $selfId || $user->roles->contains('name', 'super_admin'),
            'registered' => $user->created_at?->format('d M Y'),
        ];
    }
}
