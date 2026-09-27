<?php

namespace App\Domains\Website\Http\Controllers\Admin\PublicSite;

use App\Domains\Website\Actions\ListDailyContentSubscriptionsAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Daily content subscribers (W24). Inertia since C9 slice 7 (STATUS §5ji),
 * with its strings keyed for Dhivehi and Arabic. `role:super_admin` on the
 * route group and `daily_content.manage` here.
 */
class DailySubscriptionController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('daily_content.manage'), 403);

        return Inertia::render('Website/DailySubscriptions', [
            'metrics' => app(ListDailyContentSubscriptionsAction::class)->metrics(),
            't' => trans('admin'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('daily_content.manage'), 403);

        $rows = app(ListDailyContentSubscriptionsAction::class)->csvRows();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, [
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
                Csv::put($out, [
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
