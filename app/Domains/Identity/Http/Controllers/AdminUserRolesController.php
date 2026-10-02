<?php

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Actions\SetUserActiveAction;
use App\Domains\Identity\Actions\SetUserRolesAction;
use App\Domains\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Support\Authorization\RoleLabels;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The role and access screen under Manage users (ADR-040 slice 4, BACKLOG
 * C8): which roles a person holds, and whether they can sign in. Thin: the
 * decisions and their protections are the two Actions'.
 */
class AdminUserRolesController extends Controller
{
    public function edit(Request $request, User $user): Response
    {
        $held = $user->getRoleNames()->all();
        $superAdmins = User::query()->role('super_admin')->count();

        return Inertia::render('Identity/UserRoles', [
            't' => Phrases::once('admin'),
            'user' => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_active' => (bool) $user->is_active,
                'is_self' => (int) $user->id === (int) $request->user()->id,
                'roles' => $held,
                'labels' => RoleLabels::list($held),
            ],
            'roles' => collect(RoleLabels::all())->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])->values()->all(),
            // What the screen must not offer: the actor's own System admin
            // role, and the last System admin's.
            'locked' => in_array('super_admin', $held, true) && ((int) $user->id === (int) $request->user()->id || $superAdmins <= 1) ? ['super_admin'] : [],
            'back' => route('admin.users.index', [], false),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'roles' => ['present', 'array'],
            'roles.*' => ['string', Rule::in(RoleLabels::KNOWN)],
        ]);

        try {
            $result = app(SetUserRolesAction::class)->execute($user, $data['roles'], (int) $request->user()->id);
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }

        return back()->with('success', __('admin.roles_saved', ['name' => $user->name, 'roles' => RoleLabels::list($user->fresh()->getRoleNames())]));
    }

    public function setActive(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['active' => ['required', 'boolean']]);

        try {
            app(SetUserActiveAction::class)->execute($user, (bool) $data['active'], (int) $request->user()->id);
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }

        return back()->with('success', __((bool) $data['active'] ? 'admin.access_activated' : 'admin.access_deactivated', ['name' => $user->name]));
    }
}
