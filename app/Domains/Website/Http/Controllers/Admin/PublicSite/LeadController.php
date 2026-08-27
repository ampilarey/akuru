<?php

namespace App\Domains\Website\Http\Controllers\Admin\PublicSite;

use App\Domains\Website\Actions\ListLeadsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['source', 'status', 'course_id']);
        $leads = app(ListLeadsAction::class)->execute($filters);

        return view('admin.public-site.leads.index', [
            'leads' => $leads,
            'filters' => $filters,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = app(ListLeadsAction::class)->execute($request->only(['source', 'status', 'course_id']));

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'course_id', 'course_title', 'name', 'mobile', 'email', 'source', 'status', 'notes', 'created_at']);
            foreach ($rows as $row) {
                fputcsv($out, [
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
