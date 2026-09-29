<?php

namespace App\Support\Http;

use App\Support\Contracts\PdfConverterInterface;
use Illuminate\Http\Response;

/**
 * ADR-012 (amended, STATUS §5lr): a stored document back to the person who
 * may read it — as the HTML it was rendered as, or, when they ask for a PDF
 * and this host can print one, as that. The stored HTML stays the record.
 */
final class DocumentResponse
{
    public static function make(string $contents, string $mime, string $basename, bool $wantsPdf): Response
    {
        $pdf = app(PdfConverterInterface::class);
        if ($wantsPdf && $pdf->enabled() && str_contains($mime, 'html')) {
            try {
                return response($pdf->fromHtml($contents), 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="'.$basename.'.pdf"',
                ]);
            } catch (\Throwable $e) {
                // A printer that fails still leaves the document readable.
                report($e);
            }
        }

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$basename.'.html"',
        ]);
    }
}
