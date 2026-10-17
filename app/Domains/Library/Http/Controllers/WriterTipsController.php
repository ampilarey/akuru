<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Library\Actions\Blog\ListTipsAction;
use App\Domains\Library\Actions\Blog\SetBlogTipsAction;
use App\Domains\Library\Models\WriterProfile;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * BLOG_PLAN BL6a: the writer's tip box switch and their tips' CSV, on *My
 * blog*. Their own blog only.
 */
class WriterTipsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        $data = $request->validate(['enabled' => 'required|boolean']);
        app(SetBlogTipsAction::class)->execute((int) $request->user()->id, (bool) $data['enabled']);

        return redirect()->route('my-blog')->with('success', __($data['enabled'] ? 'blog.flash_tips_on' : 'blog.flash_tips_off'));
    }

    public function export(Request $request): StreamedResponse
    {
        $profile = WriterProfile::query()->where('user_id', (int) $request->user()->id)->first();
        abort_if($profile === null, 404);
        $rows = app(ListTipsAction::class)->forWriter((int) $profile->id, 5000);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['id', 'at', 'from', 'post', 'amount', 'method', 'status', 'refund_of', 'your_share', 'share_status', 'note']);
            foreach ($rows as $r) {
                Csv::put($out, [$r['id'], $r['at'], $r['from'], $r['post'], number_format($r['amount'], 2, '.', ''), $r['method'], $r['status'], $r['refund_of'], $r['writer_share'] !== null ? number_format($r['writer_share'], 2, '.', '') : '', $r['earning_status'], $r['note']]);
            }
            fclose($out);
        }, 'my-blog-tips.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
