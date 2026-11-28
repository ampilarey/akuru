<?php

namespace App\Domains\Settings\Http\Controllers\Admin;

use App\Domains\Settings\Support\OfficeKnobs;
use App\Http\Controllers\Controller;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ST4 (admin audit 2026-11-27, STATUS §5uw): one product's business
 * settings on its own page — what used to be changed only in `.env`. The
 * product is the route's own (a fixed address each); its gate is the
 * route's middleware, the same as the office it belongs to.
 */
class OfficeSettingsController extends Controller
{
    public function show(Request $request): Response
    {
        $product = (string) $request->route('product');
        abort_unless(in_array($product, OfficeKnobs::products(), true), 404);

        $groups = array_map(fn (array $group) => [
            'key' => $group['group'],
            'title' => (string) __("knobs.{$product}_group_{$group['group']}"),
            'knobs' => array_map(fn (array $knob) => $knob + [
                'label' => (string) __("knobs.{$product}_{$knob['name']}"),
                'hint' => (string) __("knobs.{$product}_{$knob['name']}_hint"),
            ], $group['knobs']),
        ], OfficeKnobs::groups($product));

        return Inertia::render('Settings/Knobs', [
            'product' => $product,
            'title' => (string) __("knobs.{$product}_title"),
            'intro' => (string) __("knobs.{$product}_intro"),
            'groups' => $groups,
            'save_url' => route("admin.settings.office.{$product}.save", [], false),
            't' => Phrases::once('knobs'),
            'admin_t' => Phrases::once('admin'),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $product = (string) $request->route('product');
        abort_unless(in_array($product, OfficeKnobs::products(), true), 404);
        $data = $request->validate(['group' => 'required|string|max:40', 'values' => 'present|array']);
        OfficeKnobs::save($product, $data['group'], $data['values']);

        return back()->with('success', __('knobs.saved'));
    }
}
