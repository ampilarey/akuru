<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\Print\AddPrintJobToCartAction;
use App\Domains\Bookshop\Actions\Print\PresentPrintJobAction;
use App\Domains\Bookshop\Actions\Print\ProofPrintJobAction;
use App\Domains\Bookshop\Actions\Print\SavePrintJobAction;
use App\Domains\Bookshop\Actions\Print\UploadPrintDesignAction;
use App\Domains\Bookshop\Http\Controllers\Concerns\ResolvesCart;
use App\Domains\Bookshop\Models\PrintJob;
use App\Domains\Bookshop\Models\PrintTemplate;
use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Media\Actions\ReadGeneratedDocumentAction;
use App\Http\Controllers\Controller;
use App\Support\Inertia\Phrases;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * NAME_TAGS_PLAN §3 (NT3): the customer's side of the print desk — the door
 * and its gallery, a job's page (fill the fields, choose the options, see
 * the price), the proof and its approval, and *My designs*.
 *
 * A job is its owner's; a guest's job is theirs through their basket until
 * they sign in, when it follows the basket to the account. Designing is open
 * to guests; the proof needs an account. While the service is off, only the
 * Bookstore office may open these pages, to try them before switching on.
 */
class ShopPrintController extends Controller
{
    use ResolvesCart;

    public function door(Request $request): Response
    {
        $this->available($request);

        return Inertia::render('Shop/PrintDoor', [
            't' => Phrases::once('print'),
            'templates' => app(PresentPrintJobAction::class)->gallery(app()->getLocale()),
            ...collect(app(PresentPrintJobAction::class)->choices(app()->getLocale(), []))->only(['fonts', 'font_css'])->all(),
            'sample' => \App\Domains\Bookshop\Actions\Print\ListPrintTemplatesAction::sampleRow(app()->getLocale()),
            'enabled' => PrintDesk::settings()['enabled'],
            'signed_in' => $request->user() !== null,
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $this->available($request);
        $data = $request->validate(['template' => 'required|integer']);
        $template = PrintTemplate::query()->findOrFail((int) $data['template']);
        $cart = $request->user() === null ? $this->cart($request, create: true) : null;
        $job = app(SavePrintJobAction::class)->start($template, $request->user()?->id, $cart?->id, app()->getLocale());

        return redirect()->route('public.shop.print.job', $job->id);
    }

    /** NT6: a blank design for the free-form designer (open to guests, as templates are). */
    public function startFreeform(Request $request): RedirectResponse
    {
        $this->available($request);
        $cart = $request->user() === null ? $this->cart($request, create: true) : null;
        $job = app(SavePrintJobAction::class)->startFreeform($request->user()?->id, $cart?->id, app()->getLocale());

        return redirect()->route('public.shop.print.job', $job->id);
    }

    /** NT6: *Open in the designer* — this template job becomes the customer's own free-form design. */
    public function openDesigner(Request $request, int $job): RedirectResponse
    {
        $this->available($request);
        app(SavePrintJobAction::class)->toFreeform($this->job($request, $job), app()->getLocale());

        return redirect()->route('public.shop.print.job', $job)->with('success', __('print.flash_freeform'));
    }

    /** NT5: a new job for the customer's own artwork (signed in: the file is kept privately on the account). */
    public function startUpload(Request $request): RedirectResponse
    {
        $this->available($request);
        $job = app(SavePrintJobAction::class)->startUpload((int) $request->user()->id, app()->getLocale());

        return redirect()->route('public.shop.print.job', $job->id);
    }

    /** NT5: the artwork onto the job, read and preflighted. */
    public function upload(Request $request, int $job): RedirectResponse
    {
        $this->available($request);
        $row = $this->job($request, $job);
        $maxKb = PrintDesk::settings()['max_upload_kb'];
        $data = $request->validate(['file' => 'required|file|max:'.$maxKb, 'upload_kind' => 'required|string|in:tag,sheet']);
        app(UploadPrintDesignAction::class)->execute($row, $data['file'], $data['upload_kind'], (int) $request->user()->id, app()->getLocale());

        return back()->with('success', __('print.flash_uploaded'));
    }

    public function show(Request $request, int $job): Response
    {
        $this->available($request);
        $row = $this->job($request, $job);
        if ($request->user() === null) {
            // Signing in from here comes back here, and the job comes along with the basket.
            $request->session()->put('url.intended', $request->fullUrl());
        }
        $present = app(PresentPrintJobAction::class);

        // NT5: a job from the customer's own artwork has its own page.
        return Inertia::render($row->isUpload() ? 'Shop/PrintUploadJob' : 'Shop/PrintJob', [
            't' => Phrases::once('print'),
            'job' => $present->job($row, app()->getLocale()),
            ...$present->choices(app()->getLocale(), (array) $row->design),
            // NT6: the designer's library, field names and limits, for a free-form design.
            'designer' => $row->source === 'freeform' ? collect(app(\App\Domains\Bookshop\Actions\Print\ListPrintTemplatesAction::class)->designerProps())
                ->only(['images', 'field_keys', 'max_elements'])->all() + ['sample' => \App\Domains\Bookshop\Actions\Print\ListPrintTemplatesAction::sampleRow(app()->getLocale())] : null,
            'signed_in' => $request->user() !== null,
        ]);
    }

    /** Autosave: the page sends what changed, and gets the job back priced, or the refusal. */
    public function save(Request $request, int $job): JsonResponse
    {
        $this->available($request);
        $row = $this->job($request, $job);
        $data = $request->validate([
            'layout_id' => 'sometimes|nullable|integer', 'paper_id' => 'sometimes|nullable|integer', 'colour' => 'sometimes|boolean',
            'finishing' => 'sometimes|array|max:20', 'finishing.*' => 'integer', 'sheets' => 'sometimes|integer|min:1|max:500',
            'entry' => 'sometimes|array', 'scheme' => 'sometimes|nullable|string|max:40', 'font' => 'sometimes|nullable|string|max:40',
            'name' => 'sometimes|nullable|string|max:120', 'upload_kind' => 'sometimes|string|in:tag,sheet', 'design' => 'sometimes|array',
        ]);
        $saved = app(SavePrintJobAction::class)->update($row, $data, app()->getLocale());

        return response()->json(['job' => app(PresentPrintJobAction::class)->job($saved->fresh(), app()->getLocale())]);
    }

    public function proof(Request $request, int $job): RedirectResponse
    {
        $this->available($request);
        $confirm = ['declared' => $request->boolean('declared'), 'accept_warnings' => $request->boolean('accept_warnings')];
        app(ProofPrintJobAction::class)->proof($this->job($request, $job), (int) $request->user()->id, app()->getLocale(), $confirm);

        return back()->with('success', __('print.flash_proofed'));
    }

    public function approve(Request $request, int $job): RedirectResponse
    {
        $this->available($request);
        app(ProofPrintJobAction::class)->approve($this->job($request, $job), app()->getLocale());

        return back()->with('success', __('print.flash_approved'));
    }

    /** NT4: the approved design into the basket, at the price held with its proof. */
    public function addToCart(Request $request, int $job): RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        $this->available($request);
        $row = $this->job($request, $job);
        app(AddPrintJobToCartAction::class)->execute($this->cart($request, create: true), $row, (int) $request->user()->id);
        session()->flash('success', __('print.flash_added_to_cart'));

        // The basket is a page of its own, outside the app: a full visit, not one inside it.
        return $request->header('X-Inertia') ? Inertia::location(route('public.shop.cart')) : redirect()->route('public.shop.cart');
    }

    public function copy(Request $request, int $job): RedirectResponse
    {
        $this->available($request);
        $cart = $request->user() === null ? $this->cart($request, create: true) : null;
        $copy = app(SavePrintJobAction::class)->copy($this->job($request, $job), $request->user()?->id, $cart?->id, app()->getLocale());

        return redirect()->route('public.shop.print.job', $copy->id)->with('success', __('print.flash_copied'));
    }

    public function rename(Request $request, int $job): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:120']);
        app(SavePrintJobAction::class)->rename($this->job($request, $job), $data['name']);

        return back()->with('success', __('print.flash_renamed'));
    }

    public function destroy(Request $request, int $job): RedirectResponse
    {
        app(SavePrintJobAction::class)->remove($this->job($request, $job));

        return redirect()->route('public.shop.print.designs')->with('success', __('print.flash_removed'));
    }

    /** One proof sheet, by a signed link minutes long, to its owner only. Never the print file. */
    public function proofImage(Request $request, int $job, int $sheet): HttpResponse
    {
        $row = $this->job($request, $job);
        $ids = array_values((array) $row->proof_document_ids);
        abort_unless($sheet >= 1 && isset($ids[$sheet - 1]), 404);
        $doc = app(ReadGeneratedDocumentAction::class)->execute((int) $ids[$sheet - 1]);
        abort_unless(str_ends_with((string) $doc['path'], '.png'), 404);

        return response($doc['contents'], 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="proof-'.$sheet.'.png"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function designs(Request $request): Response
    {
        $present = app(PresentPrintJobAction::class);

        return Inertia::render('Shop/PrintDesigns', [
            't' => Phrases::once('print'),
            'designs' => $present->mine((int) $request->user()->id, app()->getLocale()),
            ...collect($present->choices(app()->getLocale(), []))->only(['fonts', 'font_css'])->all(),
            'enabled' => PrintDesk::settings()['enabled'],
        ]);
    }

    /** The service is the customer's only while it is on; the office may try it before. */
    private function available(Request $request): void
    {
        abort_unless(PrintDesk::settings()['enabled'] || $request->user()?->can('bookshop.manage'), 404);
    }

    /** The caller's own job: theirs by account, or a guest's by their basket. Anyone else's is not found. */
    private function job(Request $request, int $id): PrintJob
    {
        // Resolving the basket first lets a guest's jobs follow it to the account that just signed in.
        $cart = $this->cart($request);
        $job = PrintJob::query()->with('template')->findOrFail($id);
        $user = $request->user();
        $mine = $user !== null
            ? (int) $job->user_id === (int) $user->id
            : $job->user_id === null && $cart !== null && (int) $job->cart_id === (int) $cart->id;
        abort_unless($mine, 404);

        return $job;
    }
}
