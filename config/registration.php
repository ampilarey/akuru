<?php

return [
    'default_country_code' => env('REGISTRATION_DEFAULT_COUNTRY_CODE', '960'),

    /*
     * C17 slice R2 (STATUS §5od): "Fill from a photo of the ID card" on the
     * registration forms. The photo is read in the person's own browser by
     * the self-hosted reader under public/ocr/tesseract/{ocr_version}; it
     * is not sent anywhere to be read. Off hides the button and the forms
     * work exactly as before.
     */
    'id_scan' => (bool) env('REGISTRATION_ID_SCAN', true),

    // The folder `npm run vendor:ocr` copies the reader into. Bump it with
    // the tesseract.js version so browsers never mix a cached old engine
    // with a new worker.
    'ocr_version' => '7.0.0',
];
