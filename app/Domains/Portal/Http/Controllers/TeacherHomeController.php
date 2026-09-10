<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Portal\Actions\ComposeTeacherHomeAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeacherHomeController extends Controller
{
    public function index(Request $request): Response
    {
        // The same permission that lets someone fill or manage a register: this
        // page is the front door to that work, so gating it differently would
        // create a home that some of its own links refuse.
        abort_unless(
            $request->user()?->can('registers.fill') || $request->user()?->can('registers.manage'),
            403,
        );

        return Inertia::render('Portal/TeacherHome', app(ComposeTeacherHomeAction::class)->execute(
            (int) $request->user()->id,
            $request->user()->getRoleNames()->all(),
        ));
    }
}
