<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\ListAnnouncementsForStaffAction;
use App\Domains\Academics\Actions\ListClassesForYearAction;
use App\Domains\Academics\Actions\SaveAnnouncementAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The staff noticeboard admin (React since 2026-09-22; the Blade index,
 * create and show screens are gone — S2 DoD "legacy announcement Blade
 * screens removed"). Families read notices on `/portal/announcements`, so a
 * signed-in account without a staff role is sent there.
 */
class AnnouncementController extends Controller
{
    private const STAFF_ROLES = ['super_admin', 'admin', 'headmaster', 'supervisor'];

    public function index(Request $request): Response|RedirectResponse
    {
        if (! $request->user()?->hasAnyRole(self::STAFF_ROLES)) {
            return redirect()->route('portal.announcements');
        }

        return Inertia::render('Academics/Announcements/Index', [
            'announcements' => app(ListAnnouncementsForStaffAction::class)->execute(),
            'types' => SaveAnnouncementAction::TYPES,
            'priorities' => SaveAnnouncementAction::PRIORITIES,
            'audiences' => SaveAnnouncementAction::AUDIENCES,
            'classes' => app(ListClassesForYearAction::class)->execute()->where('is_active', true)->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'title_arabic' => ['nullable', 'string', 'max:255'],
            'title_dhivehi' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'content_arabic' => ['nullable', 'string'],
            'content_dhivehi' => ['nullable', 'string'],
            'type' => ['required', 'in:'.implode(',', SaveAnnouncementAction::TYPES)],
            'priority' => ['required', 'in:'.implode(',', SaveAnnouncementAction::PRIORITIES)],
            'target_audience' => ['nullable', 'array'],
            'target_audience.*' => ['in:'.implode(',', SaveAnnouncementAction::AUDIENCES)],
            'target_classes' => ['nullable', 'array'],
            'target_classes.*' => ['integer', 'exists:classes,id'],
            'publish_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after:publish_date'],
        ]);

        app(SaveAnnouncementAction::class)->execute($data, (int) $request->user()->id);

        return redirect()->route('announcements.index')->with('success', 'Announcement created successfully!');
    }
}
