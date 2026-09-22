<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\ServeAbsenceNoteAttachmentAction;
use App\Domains\Academics\Models\AbsenceNote;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AbsenceNoteAttachmentController extends Controller
{
    public function show(Request $request, AbsenceNote $absenceNote): StreamedResponse
    {
        abort_unless($request->user() !== null, 403);

        return app(ServeAbsenceNoteAttachmentAction::class)->execute(
            (int) $request->user()->id,
            (bool) $request->user()->can('manage_attendance'),
            $absenceNote,
        );
    }
}
