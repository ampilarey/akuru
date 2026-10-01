<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\ShopCustomersAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * COMMERCE_PARITY_PLAN P7c: the Bookstore's customers as the office sees
 * them — who bought what and when, the office's tags, and notes with
 * follow-ups.
 */
class AdminCustomerController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $filters = ['q' => (string) $request->query('q', ''), 'tag' => (string) $request->query('tag', ''), 'follow_ups' => $request->boolean('follow_ups')];
        $customers = app(ShopCustomersAction::class);

        return Inertia::render('Bookshop/Customers', [
            't' => trans('shop'), 'filters' => $filters, 'tags' => $customers->allTags(),
            'customers' => $customers->list($filters['q'], $filters['tag'], $filters['follow_ups']),
        ]);
    }

    public function show(Request $request, int $customer): Response
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $profile = app(ShopCustomersAction::class)->show($customer);
        abort_if($profile === null, 404);

        return Inertia::render('Bookshop/Customer', ['t' => trans('shop'), 'customer' => $profile]);
    }

    public function tags(Request $request, int $customer): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate(['tags' => 'present|array', 'tags.*' => 'string|max:30']);
        app(ShopCustomersAction::class)->saveTags($customer, $data['tags'], (int) $request->user()->id);

        return back()->with('success', __('shop.customer_tags_saved'));
    }

    public function note(Request $request, int $customer): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate(['body' => 'required|string|max:2000', 'follow_up_on' => 'nullable|date|after_or_equal:today']);
        app(ShopCustomersAction::class)->addNote($customer, (int) $request->user()->id, $data['body'], $data['follow_up_on'] ?? null);

        return back()->with('success', __('shop.customer_note_saved'));
    }

    public function done(Request $request, int $customer, int $note): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        app(ShopCustomersAction::class)->done($customer, $note, (int) $request->user()->id);

        return back()->with('success', __('shop.customer_follow_up_done'));
    }

    /** Every listing gets a CSV (conventions). */
    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $rows = app(ShopCustomersAction::class)->list((string) $request->query('q', ''), (string) $request->query('tag', ''), $request->boolean('follow_ups'), 10000);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['name', 'email', 'phone', 'orders', 'spent', 'last_order', 'tags', 'follow_ups_due']);
            foreach ($rows as $r) {
                Csv::put($out, [$r['name'], $r['email'], $r['phone'], $r['orders'], $r['spent'], $r['last_order'], implode('; ', $r['tags']), $r['follow_ups_due']]);
            }
            fclose($out);
        }, 'bookstore-customers.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
