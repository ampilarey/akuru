<?php

namespace App\Domains\PrayerTimes\Http\Controllers\Admin;

use App\Domains\PrayerTimes\Actions\SavePrayerRecipientGroupAction;
use App\Domains\PrayerTimes\Models\PrayerRecipientGroup;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Prayer recipient groups: the list and the form. Inertia since C9 slice 12
 * (STATUS §5jn), with its strings keyed for Dhivehi and Arabic.
 */
class RecipientGroupController extends Controller
{
    public function index(): Response
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);

        return Inertia::render('PrayerTimes/Groups', [
            'groups' => PrayerRecipientGroup::query()->latest('id')->get()->map(fn (PrayerRecipientGroup $group) => [
                'id' => $group->id,
                'name' => $group->name_en,
                'members' => is_array($group->member_refs) ? count($group->member_refs) : 0,
                'is_active' => (bool) $group->is_active,
            ])->values()->all(),
            't' => trans('admin'),
        ]);
    }

    /** "Every listing gets CSV export" (admin-panel audit, STATUS §5hs). */
    public function export(): StreamedResponse
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);
        $rows = PrayerRecipientGroup::query()->latest('id')->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['id', 'name', 'members', 'active', 'created_at']);
            foreach ($rows as $row) {
                Csv::put($out, [$row->id, $row->name_en, is_array($row->member_refs) ? count($row->member_refs) : 0, $row->is_active ? 'yes' : 'no', $row->created_at?->toDateTimeString()]);
            }
            fclose($out);
        }, 'prayer-recipient-groups.csv', ['Content-Type' => 'text/csv']);
    }

    public function create(): Response
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);

        return Inertia::render('PrayerTimes/GroupForm', ['group' => null, 't' => trans('admin')]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);
        $row = app(SavePrayerRecipientGroupAction::class)->execute($request->all(), null, (int) $request->user()->id);

        return redirect()->route('admin.prayer-times.groups.edit', $row)->with('success', trans('admin.prayer_flash_group_saved'));
    }

    public function edit(PrayerRecipientGroup $group): Response
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);

        return Inertia::render('PrayerTimes/GroupForm', [
            'group' => [
                'id' => $group->id,
                'name_en' => $group->name_en,
                'name_dv' => $group->name_dv,
                'name_ar' => $group->name_ar,
                'description' => $group->description,
                'member_refs' => json_encode($group->member_refs ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                'is_active' => (bool) $group->is_active,
            ],
            't' => trans('admin'),
        ]);
    }

    public function update(Request $request, PrayerRecipientGroup $group): RedirectResponse
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);
        app(SavePrayerRecipientGroupAction::class)->execute($request->all(), $group, (int) $request->user()->id);

        return back()->with('success', trans('admin.prayer_flash_group_saved'));
    }
}
