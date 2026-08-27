<?php

namespace App\Domains\Website\Http\Controllers\PublicSite;

use App\Domains\Website\Actions\BuildPublicSitemapAction;
use App\Http\Controllers\Controller;

class SitemapController extends Controller
{
    public function index()
    {
        $sitemap = app(BuildPublicSitemapAction::class)->execute();

        return response($sitemap, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
