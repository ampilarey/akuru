<?php

namespace App\Domains\PrayerTimes\Http\Controllers\Admin;

use App\Domains\PrayerTimes\Actions\SavePrayerRecipientGroupAction;
use App\Domains\PrayerTimes\Models\PrayerRecipientGroup;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RecipientGroupController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);

        return view('admin.prayer-times.groups.index', [
            'groups' => PrayerRecipientGroup::query()->latest('id')->get(),
        ]);
    }

    public function create()
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);

        return view('admin.prayer-times.groups.form', ['group' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);
        $row = app(SavePrayerRecipientGroupAction::class)->execute($request->all(), null, (int) $request->user()->id);

        return redirect()->route('admin.prayer-times.groups.edit', $row)->with('success', 'Group saved.');
    }

    public function edit(PrayerRecipientGroup $group)
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);

        return view('admin.prayer-times.groups.form', ['group' => $group]);
    }

    public function update(Request $request, PrayerRecipientGroup $group): RedirectResponse
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);
        app(SavePrayerRecipientGroupAction::class)->execute($request->all(), $group, (int) $request->user()->id);

        return back()->with('success', 'Group saved.');
    }
}
