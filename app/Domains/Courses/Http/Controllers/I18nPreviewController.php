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
                    'heading' => 'English sample',
                    'body' => 'The lesson player must stay readable in English.',
                ],
                [
                    'locale' => 'dv',
                    'dir' => 'rtl',
                    'heading' => 'ތައްލިމް',
                    'body' => 'އެލެކްޓްރޮނިކް ތައްލީމް',
                ],
                [
                    'locale' => 'ar',
                    'dir' => 'rtl',
                    'heading' => 'عينة عربية',
                    'body' => 'يجب أن يبقى مشغّل الدرس واضحاً في العربية.',
                ],
            ],
        ]);
    }
}
