<?php

return [
    /*
     * ADR-012 (amended 2026-09-29, STATUS §5lr): documents are HTML; a PDF of
     * one is printed by headless Chrome, and only on a host where one is
     * installed and named here. Unset (the default), no screen offers a PDF.
     */
    'pdf' => [
        'chrome_path' => env('DOCUMENTS_CHROME_PATH'),
        'timeout' => (int) env('DOCUMENTS_PDF_TIMEOUT', 60),
    ],
];
