<?php

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Actions\DeleteUserAccountAction;
use App\Domains\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    public function index(Request $request)
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

        $users = $query->paginate(25)->withQueryString();

        return view('admin.users.index', compact('users'));
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
