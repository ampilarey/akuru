<?php

namespace App\Domains\Website\Http\Controllers\Admin\PublicSite;

use App\Domains\Website\Actions\ListDailyContentSubscriptionsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DailySubscriptionController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->can('daily_content.manage'), 403);

        $metrics = app(ListDailyContentSubscriptionsAction::class)->metrics();

        return view('admin.public-site.daily-subscriptions.index', [
            'metrics' => $metrics,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('daily_content.manage'), 403);

        $rows = app(ListDailyContentSubscriptionsAction::class)->csvRows();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'id',
                'user_id',
                'channel',
                'status',
                'language',
                'content_types',
                'send_time',
                'email',
                'phone',
                'unsubscribed_at',
                'unsubscribe_reason',
            ]);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['id'],
                    $row['user_id'],
                    $row['channel'],
                    $row['status'],
                    $row['language'],
                    $row['content_types'],
                    $row['send_time'],
                    $row['email'],
                    $row['phone'],
                    $row['unsubscribed_at'],
                    $row['unsubscribe_reason'],
                ]);
            }
            fclose($out);
        }, 'daily-subscriptions.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
