<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class I18nPreviewController extends Controller
{
    public function show(Request $request): Response
    {
        abort_unless($request->user()?->can('courses.manage'), 403);

        return Inertia::render('Courses/Catalog/I18nPreview', [
            'samples' => [
                [
                    'locale' => 'en',
                    'dir' => 'ltr',
                    'font' => 'latin',
                    'heading' => 'English sample',
                    'body' => 'The lesson player must stay readable in English.',
                ],
                [
                    'locale' => 'dv',
                    'dir' => 'rtl',
                    'font' => 'thaana',
                    'heading' => trans('learn.dashboard_title', [], 'dv'),
                    'body' => trans('learn.catalog_intro', [], 'dv'),
                ],
                [
                    'locale' => 'ar',
                    'dir' => 'rtl',
                    'font' => 'arabic',
                    'heading' => trans('learn.dashboard_title', [], 'ar'),
                    'body' => trans('learn.catalog_intro', [], 'ar'),
                ],
            ],
        ]);
    }
}
