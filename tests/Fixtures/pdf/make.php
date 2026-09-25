<?php

/**
 * Hand-built PDFs for `PdfTextExtractorTest`, each exercising one shape the
 * parser must handle: written as functions so the bytes stay readable in
 * the test rather than as opaque fixture files. Cross-reference tables are
 * deliberately absent or wrong — the reader scans for objects and must not
 * depend on them.
 */
function pdfFromObjects(array $objects, int $root): string
{
    $out = "%PDF-1.4\n";
    foreach ($objects as $number => $body) {
        $out .= "$number 0 obj\n$body\nendobj\n";
    }
    $out .= "trailer\n<< /Root $root 0 R >>\n%%EOF\n";

    return $out;
}

function pdfStream(string $dict, string $data): string
{
    return "<< $dict /Length ".strlen($data)." >>\nstream\n$data\nendstream";
}

/** Simple WinAnsi font, TJ with kerning, `/Differences`, two pages in a nested tree, resources inherited. */
function pdfSimpleFontTwoPages(): string
{
    $page1 = "BT /F1 12 Tf 72 700 Td [(Hel) -20 (lo) -400 (there)] TJ ET\n"
        ."BT /F1 12 Tf 72 680 Td (caf\\351 \\256 fin) Tj ET\n"
        ."BT /F1 24 Tf 72 640 Td (Heading) Tj ET\n"
        ."BT /F1 12 Tf 72 620 Td (Body after heading) Tj T* ET\n";
    $page2 = 'BT /F1 12 Tf 72 700 Td (Second page) Tj ET';

    return pdfFromObjects([
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 2 /Resources << /Font << /F1 6 0 R >> >> >>',
        3 => '<< /Type /Pages /Parent 2 0 R /Kids [4 0 R 5 0 R] /Count 2 >>',
        4 => '<< /Type /Page /Parent 3 0 R /MediaBox [0 0 612 792] /Contents 7 0 R >>',
        5 => '<< /Type /Page /Parent 3 0 R /MediaBox [0 0 612 792] /Contents 8 0 R >>',
        // 0xAE is `registered` in WinAnsi; Differences makes it `fi`.
        6 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding << /BaseEncoding /WinAnsiEncoding /Differences [174 /fi] >> >>',
        7 => pdfStream('', $page1),
        8 => pdfStream('/Filter /ASCIIHexDecode', bin2hex($page2).'>'),
    ], 1);
}

/** Text drawn through a form XObject with its own font resource, and a Flate-compressed page stream. */
function pdfFormXObject(): string
{
    $form = 'BT /F2 10 Tf 0 0 Td (inside the form) Tj ET';
    $page = "BT /F1 12 Tf 72 700 Td (on the page) Tj ET\nq 1 0 0 1 72 600 cm /Fx1 Do Q";

    return pdfFromObjects([
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> /XObject << /Fx1 6 0 R >> >> /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        5 => pdfStream('/Filter /FlateDecode', gzcompress($page)),
        6 => pdfStream('/Type /XObject /Subtype /Form /BBox [0 0 200 50] /Resources << /Font << /F2 4 0 R >> >>', $form),
    ], 1);
}

/** A page that draws nothing but a rectangle: no text at all. */
function pdfNoText(): string
{
    return pdfFromObjects([
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>',
        4 => pdfStream('', '0 0 1 rg 100 100 200 300 re f'),
    ], 1);
}

/** A text page followed by an empty page, so page numbering has a hole to keep. */
function pdfTextThenBlank(): string
{
    return pdfFromObjects([
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R 5 0 R] /Count 2 /Resources << /Font << /F1 7 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>',
        4 => pdfStream('', 'BT /F1 12 Tf 72 700 Td (Words on one) Tj ET'),
        5 => '<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>',
        6 => pdfStream('', ''),
        7 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ], 1);
}

/** Wrong /Length on the stream and an inline image in the content: both must be stepped over. */
function pdfBrokenLengthAndInlineImage(): string
{
    $page = "BT /F1 12 Tf 72 700 Td (before image) Tj ET\n"
        ."q 100 0 0 50 72 500 cm BI /W 2 /H 2 /CS /G /BPC 8 ID \x00\xFFEI\x10 EI Q\n"
        .'BT /F1 12 Tf 72 400 Td (after image) Tj ET';

    return pdfFromObjects([
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        5 => "<< /Length 5 >>\nstream\n$page\nendstream",
    ], 1);
}
