<?php

namespace App\Http\Controllers\Hifz;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class HifzHubController extends Controller
{
    public function index(): RedirectResponse
    {
        $user = auth()->user();

        if ($user->isHifzDean() || $user->isAdminLevel()) {
            return redirect()->route('hifz.dean.dashboard');
        }

        if ($user->isSupervisor()) {
            return redirect()->route('hifz.supervisor.dashboard');
        }

        if ($user->isTeacher()) {
            return redirect()->route('hifz.teacher.dashboard');
        }

        if ($user->isStudent()) {
            return redirect()->route('hifz.student.dashboard');
        }

        if ($user->isParent()) {
            return redirect()->route('hifz.parent.dashboard');
        }

        abort(403);
    }
}
