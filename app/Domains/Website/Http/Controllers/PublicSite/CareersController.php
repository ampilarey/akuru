<?php

namespace App\Domains\Website\Http\Controllers\PublicSite;

use App\Domains\HR\Actions\ListJobPostingsAction;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class CareersController extends Controller
{
    public function index(): View
    {
        return view('public.careers.index', [
            'postings' => app(ListJobPostingsAction::class)->execute(true),
        ]);
    }
}
