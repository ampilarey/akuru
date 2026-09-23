<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Models\SchoolRequest;
use App\Domains\Media\Actions\ReadDocumentContentAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The supporting document behind a leave request: for the person who sent
 * it and for whoever reviews requests. Same shape as the payslip and receipt
 * documents — private disk, served by the application, never a public URL.
 */
class SchoolRequestDocumentController extends Controller
{
    public function show(Request $request, SchoolRequest $schoolRequest): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);
        abort_unless((int) $schoolRequest->requester_id === (int) $user->id || $user->can('requests.review'), 403);

        $documentId = (int) (($schoolRequest->payload ?? [])['document_id'] ?? 0);
        abort_unless($documentId > 0, 404);

        $document = app(ReadDocumentContentAction::class)->execute($documentId);
        abort_unless($document !== null, 404);

        return response($document['content'], 200, ['Content-Type' => $document['mime']]);
    }
}
