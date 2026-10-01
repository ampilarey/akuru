<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\AkuruDeliveryAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * COMMERCE_PARITY_PLAN P6b: a driver's own page (`/deliveries`), made for
 * the phone: today's deliveries, *Picked up*, and *Delivered* with a photo
 * from the camera. A driver sees and moves only their own.
 */
class DriverController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('bookshop.deliver'), 403);

        return Inertia::render('Bookshop/Deliveries', [
            't' => trans('shop'),
            'deliveries' => app(AkuruDeliveryAction::class)->forDriver((int) $request->user()->id),
        ]);
    }

    public function pickUp(Request $request, int $delivery): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.deliver'), 403);
        app(AkuruDeliveryAction::class)->pickUp((int) $request->user()->id, $delivery);

        return back()->with('success', __('shop.driver_picked_up_flash'));
    }

    public function deliver(Request $request, int $delivery): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.deliver'), 403);
        $data = $request->validate([
            'photo' => ['required', 'file', 'max:10240', 'mimetypes:'.implode(',', AkuruDeliveryAction::PROOF_MIMES)],
            'note' => 'nullable|string|max:255',
            'cash_received' => 'nullable|boolean',
        ]);
        app(AkuruDeliveryAction::class)->deliver((int) $request->user()->id, $delivery, $request->file('photo'), $data['note'] ?? null, (bool) ($data['cash_received'] ?? false));

        return back()->with('success', __('shop.driver_delivered_flash'));
    }

    /** The proof photo: for the office, or the driver who took it. Never cached. */
    public function proof(Request $request, int $delivery)
    {
        $user = $request->user();
        $deliveries = app(AkuruDeliveryAction::class);
        abort_unless($user !== null && ($user->can('bookshop.manage') || $deliveries->driverOf($delivery) === (int) $user->id), 403);
        $media = $deliveries->proof($delivery);
        abort_if($media === null, 404);

        return response($media['contents'], 200, [
            'Content-Type' => $media['mime'],
            'Content-Disposition' => 'inline; filename="delivery-'.$delivery.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
