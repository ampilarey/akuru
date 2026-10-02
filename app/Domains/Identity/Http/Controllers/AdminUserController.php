<?php

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Actions\DeleteUserAccountAction;
use App\Domains\Identity\Actions\ListAdminUsersAction;
use App\Domains\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Support\Authorization\RoleLabels;
use App\Support\Csv;
use App\Support\Inertia\Phrases;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Manage users (docs/ADMIN_PANEL.md). Inertia since C9 slice 2 (STATUS
 * §5jd), with its strings keyed for Dhivehi and Arabic. `role:super_admin`
 * on the route group.
 */
class AdminUserController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'role']);

        return Inertia::render('Identity/Users', app(ListAdminUsersAction::class)->execute($filters, (int) $request->user()->id, app()->getLocale()) + [
            'filters' => ['search' => (string) ($filters['search'] ?? ''), 'role' => (string) ($filters['role'] ?? '')],
            // Every role, by the name people read (ADR-040 slice 3).
            'roles' => collect(RoleLabels::all())->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all(),
            't' => Phrases::once('admin'),
        ]);
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
        $users = app(ListAdminUsersAction::class)->query($request->only(['search', 'role']))->get();

        return response()->streamDownload(function () use ($users): void {
            $handle = fopen('php://output', 'w');
            Csv::put($handle, ['id', 'name', 'roles', 'contacts', 'active', 'created_at']);

            foreach ($users as $user) {
                Csv::put($handle, [
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
            return back()->with('success', trans('admin.users_deleted', ['name' => $name]));
        }

        return back()->with('success', trans('admin.users_deactivated_kept', [
            'name' => $name,
            'kept' => $this->describe($result['blocked_by']),
        ]));
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
