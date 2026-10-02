<?php

namespace App\Domains\Website\Http\Controllers\Admin\PublicSite;

use App\Domains\Website\Actions\ListLeadsAction;
use App\Domains\Website\Enums\LeadSource;
use App\Domains\Website\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use App\Support\Inertia\Phrases;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Leads from the public website (W14). Inertia since C9 slice 6 (STATUS
 * §5jh), with its strings keyed for Dhivehi and Arabic. `role:super_admin`
 * on the route group.
 */
class LeadController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['source', 'status', 'course_id']);

        return Inertia::render('Website/Leads', [
            'leads' => app(ListLeadsAction::class)->execute($filters)->all(),
            'filters' => ['source' => (string) ($filters['source'] ?? ''), 'status' => (string) ($filters['status'] ?? ''), 'course_id' => (string) ($filters['course_id'] ?? '')],
            'sources' => array_map(fn (LeadSource $s) => $s->value, LeadSource::cases()),
            'statuses' => array_map(fn (LeadStatus $s) => $s->value, LeadStatus::cases()),
            't' => Phrases::once('admin'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = app(ListLeadsAction::class)->execute($request->only(['source', 'status', 'course_id']));

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['id', 'course_id', 'course_title', 'name', 'mobile', 'email', 'source', 'status', 'notes', 'created_at']);
            foreach ($rows as $row) {
                Csv::put($out, [
                    $row['id'],
                    $row['course_id'],
                    $row['course_title'],
                    $row['name'],
                    $row['mobile'],
                    $row['email'],
                    $row['source'],
                    $row['status'],
                    $row['notes'],
                    $row['created_at'],
                ]);
            }
            fclose($out);
        }, 'leads.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
