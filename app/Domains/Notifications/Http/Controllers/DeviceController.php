<?php

namespace App\Domains\Notifications\Http\Controllers;

use App\Domains\Notifications\Actions\ForgetDeviceAction;
use App\Domains\Notifications\Actions\RegisterDeviceAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The mobile app's device registration (SPEC §50, STATUS §5jr). The app shell
 * posts its push token after sign-in and forgets it at sign-out; the
 * notification centre lists a person's phones and removes one. Every route is
 * the signed-in person's own devices and nothing else, so it sits under
 * `auth` alone like the account switcher. Thin (rule 5).
 */
class DeviceController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:8', 'max:255'],
            'platform' => ['nullable', 'in:android,ios,web'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'app_version' => ['nullable', 'string', 'max:40'],
            'locale' => ['nullable', 'in:en,dv,ar'],
        ]);

        $device = app(RegisterDeviceAction::class)->execute((int) $request->user()->id, $data);

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'id' => $device->id])
            : back()->with('success', trans('admin.devices_flash_registered'));
    }

    public function forget(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:255']]);
        $forgotten = app(ForgetDeviceAction::class)->byToken((int) $request->user()->id, $data['token']);

        return $request->expectsJson()
            ? response()->json(['ok' => $forgotten])
            : back()->with('success', trans('admin.devices_flash_removed'));
    }

    public function destroy(Request $request, int $device): RedirectResponse
    {
        app(ForgetDeviceAction::class)->byId((int) $request->user()->id, $device);

        return back()->with('success', trans('admin.devices_flash_removed'));
    }
}
