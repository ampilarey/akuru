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
        $list = app(ListGuardianChildrenAction::class);
        $userId = (int) $request->user()->id;

        return Inertia::render('Portal/Children', [
            'children' => $list->executeForGuardianUserId($userId),
            // Item 13: links the office has not verified yet, by name only.
            'pending' => $list->executePendingForGuardianUserId($userId),
        ]);
    }
}
