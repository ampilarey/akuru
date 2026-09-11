<?php

namespace App\Domains\Courses\Components\Clubs\Http\Controllers;

use App\Domains\Courses\Actions\CancelEnrollmentAction;
use App\Domains\Courses\Components\Clubs\Actions\AddClubMemberAction;
use App\Domains\Courses\Components\Clubs\Actions\ListClubRosterAction;
use App\Domains\Courses\Components\Clubs\Actions\ListClubsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Thin (rule 5). Clubs have no controller logic of their own worth the name —
 * they are courses, and every verb here is an engine Action.
 */
class ClubController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Courses/Clubs/Index', [
            'clubs' => app(ListClubsAction::class)->execute(),
        ]);
    }

    public function show(int $club): Response
    {
        $clubs = app(ListClubsAction::class)->execute();
        $current = $clubs->firstWhere('id', $club);

        abort_if($current === null, 404);

        return Inertia::render('Courses/Clubs/Roster', [
            'club' => $current,
            'members' => app(ListClubRosterAction::class)->execute($club),
        ]);
    }

    public function addMember(Request $request, int $club, AddClubMemberAction $add): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'min:1'],
        ], [], ['student_id' => 'student']);

        $add->execute($club, (int) $data['student_id'], (int) $request->user()->id);

        return back()->with('success', 'Member added.');
    }

    public function removeMember(int $club, int $enrollment, CancelEnrollmentAction $cancel): RedirectResponse
    {
        return $cancel->execute($enrollment)
            ? back()->with('success', 'Member removed.')
            : back()->with('error', 'That member had already been removed.');
    }

    /**
     * The printable attendance sheet the plan asks for — a club leader with a
     * clipboard, not a screen. Blank columns on purpose: it is filled in by
     * hand and typed up later, which is how clubs actually run.
     */
    public function attendanceSheet(int $club): Response
    {
        $clubs = app(ListClubsAction::class)->execute();
        $current = $clubs->firstWhere('id', $club);

        abort_if($current === null, 404);

        return Inertia::render('Courses/Clubs/AttendanceSheet', [
            'club' => $current,
            'members' => app(ListClubRosterAction::class)->execute($club),
        ]);
    }

    public function export(int $club): StreamedResponse
    {
        $members = app(ListClubRosterAction::class)->execute($club);

        return response()->streamDownload(function () use ($members): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['name', 'student_number', 'on_roll']);
            foreach ($members as $member) {
                fputcsv($out, [$member['name'], $member['student_number'], $member['on_roll'] ? 'yes' : 'no']);
            }
            fclose($out);
        }, 'club-roster.csv', ['Content-Type' => 'text/csv']);
    }
}
