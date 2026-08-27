<?php

namespace App\Domains\Website\Http\Controllers\Admin\PublicSite;

use App\Domains\Website\Actions\ApproveDailyContentAction;
use App\Domains\Website\Actions\CreateDailyContentBatchAction;
use App\Domains\Website\Actions\ListDailyContentsAction;
use App\Domains\Website\Actions\SaveDailyContentAction;
use App\Domains\Website\Enums\DailyContentStatus;
use App\Domains\Website\Models\DailyContent;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DailyContentController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->can('daily_content.manage'), 403);

        $filters = $request->only(['month', 'status', 'content_type', 'theme_tag', 'q']);
        $items = app(ListDailyContentsAction::class)->execute($filters);
        $month = preg_match('/^\d{4}-\d{2}$/', (string) ($filters['month'] ?? ''))
            ? (string) $filters['month']
            : now()->timezone(config('app.timezone'))->format('Y-m');

        return view('admin.public-site.daily-content.index', [
            'items' => $items,
            'filters' => $filters,
            'month' => $month,
        ]);
    }

    public function queue(Request $request)
    {
        abort_unless($request->user()?->can('daily_content.approve'), 403);

        return view('admin.public-site.daily-content.queue', [
            'items' => app(ListDailyContentsAction::class)->approvalQueue(),
        ]);
    }

    public function ayahPreview(Request $request)
    {
        abort_unless($request->user()?->can('daily_content.manage'), 403);

        return response()->json(app(ListDailyContentsAction::class)->previewAyah(
            (int) $request->input('surah_number'),
            (int) $request->input('ayah_number'),
        ));
    }

    public function create(Request $request)
    {
        abort_unless($request->user()?->can('daily_content.manage'), 403);

        return view('admin.public-site.daily-content.form', [
            'item' => null,
            'type' => $request->input('content_type', 'ayah'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('daily_content.manage'), 403);

        $row = app(SaveDailyContentAction::class)->execute($request->all(), null, (int) $request->user()->id);

        return redirect()
            ->route('admin.daily-content.edit', $row)
            ->with('success', 'Draft saved. A second reviewer must approve before it is scheduled.');
    }

    public function edit(Request $request, DailyContent $dailyContent)
    {
        abort_unless($request->user()?->can('daily_content.manage'), 403);

        return view('admin.public-site.daily-content.form', [
            'item' => app(ListDailyContentsAction::class)->present($dailyContent),
            'type' => $dailyContent->content_type->value,
        ]);
    }

    public function update(Request $request, DailyContent $dailyContent): RedirectResponse
    {
        abort_unless($request->user()?->can('daily_content.manage'), 403);

        app(SaveDailyContentAction::class)->execute($request->all(), $dailyContent, (int) $request->user()->id);

        return redirect()
            ->route('admin.daily-content.edit', $dailyContent)
            ->with('success', 'Daily content updated.');
    }

    public function approve(Request $request, DailyContent $dailyContent): RedirectResponse
    {
        abort_unless($request->user()?->can('daily_content.approve'), 403);

        $status = DailyContentStatus::tryFrom((string) $request->input('status', 'scheduled'))
            ?? DailyContentStatus::Scheduled;

        app(ApproveDailyContentAction::class)->execute($dailyContent, (int) $request->user()->id, $status);

        return redirect()
            ->route('admin.daily-content.queue')
            ->with('success', 'Approved.');
    }

    public function batch(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('daily_content.manage'), 403);

        $created = app(CreateDailyContentBatchAction::class)->execute($request->all(), (int) $request->user()->id);

        return redirect()
            ->route('admin.daily-content.index')
            ->with('success', count($created).' reminder drafts created. Each still needs a second approver.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('daily_content.manage'), 403);

        $rows = app(ListDailyContentsAction::class)->execute($request->only(['month', 'status', 'content_type', 'theme_tag', 'q']));

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'content_type', 'publish_date', 'status', 'theme_tag', 'approved_by', 'attribution', 'hadith_collection', 'hadith_number']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['id'],
                    $row['content_type'],
                    $row['publish_date'],
                    $row['status'],
                    $row['theme_tag'],
                    $row['approved_by'],
                    $row['attribution'],
                    $row['hadith_collection'],
                    $row['hadith_number'],
                ]);
            }
            fclose($out);
        }, 'daily-content.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
