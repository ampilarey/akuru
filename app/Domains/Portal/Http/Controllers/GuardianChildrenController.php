<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GuardianChildrenController extends Controller
{
    public function index(Request $request): Response
    {
        $children = app(ListGuardianChildrenAction::class)
            ->executeForGuardianUserId((int) $request->user()->id);

        return Inertia::render('Portal/Children', [
            'children' => $children,
        ]);
    }
}
