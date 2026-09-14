<?php

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Actions\DeleteUserAccountAction;
use App\Domains\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $users = $this->filtered($request)->paginate(25)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    /**
     * CLAUDE.md: *"every listing gets CSV export."*
     *
     * The account roster — who can sign in, as what, and whether they still
     * can. Carries the screen's search and role filter, so the download is a
     * copy of what was being looked at rather than the whole table.
     *
     * `national_id`, `address` and `date_of_birth` are deliberately left out,
     * for the same reason the staff export leaves them out: they are on the
     * screen behind a `super_admin` login, and a spreadsheet leaves the
     * building. Contacts are in, because an account roster without a way to
     * reach the account holder is not a roster.
     */
    public function export(Request $request): StreamedResponse
    {
        $users = $this->filtered($request)->get();

        return response()->streamDownload(function () use ($users): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'name', 'roles', 'contacts', 'active', 'created_at']);

            foreach ($users as $user) {
                fputcsv($handle, [
                    $user->id,
                    $user->name,
                    $user->roles->pluck('name')->implode(' '),
                    $user->contacts->map(fn ($contact) => $contact->type.':'.$contact->value)->implode(' '),
                    $user->is_active ? 'yes' : 'no',
                    $user->created_at?->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, 'users.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * The screen's query, shared so the export cannot drift away from the list
     * it claims to be a copy of.
     */
    private function filtered(Request $request): Builder
    {
        $query = User::with(['contacts', 'roles'])
            ->withCount(['contacts'])
            ->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('national_id', 'like', "%{$search}%")
                    ->orWhereHas('contacts', fn ($c) => $c->where('value', 'like', "%{$search}%"));
            });
        }

        if ($role = $request->input('role')) {
            $query->role($role);
        }

        return $query;
    }

    /**
     * SPEC §29: "Historical student data must remain intact."
     *
     * This method used to disable foreign key checks, hard-delete the user's
     * enrolments with a query-builder delete that bypasses `SoftDeletes`,
     * hard-delete their `payments` rows against rule 12, and hard-delete the
     * account. Progress, attempts, attendance and certificates were left
     * orphaned rather than removed, because with the keys off the cascades
     * never fired.
     *
     * The rule now lives in `DeleteUserAccountAction`, which deactivates an
     * account that anything depends on and hard-deletes only what §29 allows.
     */
    public function destroy(Request $request, User $user)
    {
        $name = $user->name;

        try {
            $result = app(DeleteUserAccountAction::class)->execute($user, auth()->id());
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }

        if ($result['deleted']) {
            return back()->with('success', "User \"{$name}\" has been deleted.");
        }

        return back()->with('success', sprintf(
            'User "%s" has been deactivated and can no longer sign in. Their history is kept: %s.',
            $name,
            $this->describe($result['blocked_by']),
        ));
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function describe(array $counts): string
    {
        $parts = [];
        foreach ($counts as $table => $count) {
            $parts[] = $count.' '.str_replace('_', ' ', $table);
        }

        return implode(', ', $parts);
    }
}
