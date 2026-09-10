<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Academics\Actions\ListAnnouncementsForUserAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * E4 — the noticeboard as families and staff see it.
 *
 * The admin CRUD has existed since 2025 and writes audience and class
 * targeting; nothing read it. This is the reader.
 */
class PortalAnnouncementController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Portal/Announcements', [
            'announcements' => $this->rows($request)->all(),
            'csvUrl' => '/portal/announcements/export',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->rows($request);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['published_on', 'expires_on', 'priority', 'type', 'title', 'content']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['published_on'],
                    $row['expires_on'],
                    $row['priority'],
                    $row['type'],
                    $row['title'],
                    $row['content'],
                ]);
            }
            fclose($out);
        }, 'announcements.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function rows(Request $request): \Illuminate\Support\Collection
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        return app(ListAnnouncementsForUserAction::class)
            ->execute((int) $user->id, $user->getRoleNames()->all());
    }
}
