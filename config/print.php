<?php

/*
 * Print products in the Bookstore (docs/NAME_TAGS_PLAN.md §5.4, slice NT0).
 *
 * The fonts a name tag may be set in. Each is self-hosted under
 * `public/fonts/print` beside its licence (C15 slice 5: no third-party font
 * link anywhere), copied from its `@fontsource` package by
 * `scripts/fonts/copy-print-fonts.mjs`. The designer loads them from
 * `/fonts/print`; the print HTML is written to disk and opened by Chrome as a
 * file, so the renderer loads the same files by `file://` path. Faruma ships
 * as before under `public/fonts`. MV Boli is Microsoft's and is not offered.
 *
 * `files` maps a weight to the woff2 file; `scripts` says which writing
 * systems the face covers, so the designer offers a Thaana name only faces
 * that can set it.
 */
return [
    'fonts_dir' => 'fonts/print',

    'fonts' => [
        'faruma' => [
            'family' => 'Faruma',
            'label' => 'Faruma',
            'scripts' => ['thaana'],
            'dir' => 'fonts',
            'files' => [400 => 'Faruma.woff2'],
            'licence' => null,
            'unicode_range' => 'U+0780-07BF',
        ],
        'noto-sans-thaana' => [
            'family' => 'Noto Sans Thaana',
            'label' => 'Noto Sans Thaana',
            'scripts' => ['thaana', 'latin'],
            'files' => [400 => 'noto-sans-thaana-thaana-400-normal.woff2', 700 => 'noto-sans-thaana-thaana-700-normal.woff2'],
            'licence' => 'noto-sans-thaana-LICENSE.txt',
        ],
        'amiri' => [
            'family' => 'Amiri',
            'label' => 'Amiri',
            'scripts' => ['arabic', 'latin'],
            'files' => [400 => 'amiri-arabic-400-normal.woff2', 700 => 'amiri-arabic-700-normal.woff2'],
            'licence' => 'amiri-LICENSE.txt',
        ],
        'cairo' => [
            'family' => 'Cairo',
            'label' => 'Cairo',
            'scripts' => ['arabic', 'latin'],
            'files' => [400 => 'cairo-arabic-400-normal.woff2', 700 => 'cairo-arabic-700-normal.woff2'],
            'licence' => 'cairo-LICENSE.txt',
        ],
        'figtree' => [
            'family' => 'Figtree',
            'label' => 'Figtree',
            'scripts' => ['latin'],
            'files' => [400 => 'figtree-latin-400-normal.woff2', 700 => 'figtree-latin-700-normal.woff2'],
            'licence' => 'figtree-LICENSE.txt',
        ],
        'poppins' => [
            'family' => 'Poppins',
            'label' => 'Poppins',
            'scripts' => ['latin'],
            'files' => [400 => 'poppins-latin-400-normal.woff2', 700 => 'poppins-latin-700-normal.woff2'],
            'licence' => 'poppins-LICENSE.txt',
        ],
        'nunito' => [
            'family' => 'Nunito',
            'label' => 'Nunito',
            'scripts' => ['latin'],
            'files' => [400 => 'nunito-latin-400-normal.woff2', 700 => 'nunito-latin-700-normal.woff2'],
            'licence' => 'nunito-LICENSE.txt',
        ],
        'fredoka' => [
            'family' => 'Fredoka',
            'label' => 'Fredoka',
            'scripts' => ['latin'],
            'files' => [400 => 'fredoka-latin-400-normal.woff2', 700 => 'fredoka-latin-700-normal.woff2'],
            'licence' => 'fredoka-LICENSE.txt',
        ],
        'baloo-2' => [
            'family' => 'Baloo 2',
            'label' => 'Baloo',
            'scripts' => ['latin'],
            'files' => [400 => 'baloo-2-latin-400-normal.woff2', 700 => 'baloo-2-latin-700-normal.woff2'],
            'licence' => 'baloo-2-LICENSE.txt',
        ],
        'lora' => [
            'family' => 'Lora',
            'label' => 'Lora',
            'scripts' => ['latin'],
            'files' => [400 => 'lora-latin-400-normal.woff2', 700 => 'lora-latin-700-normal.woff2'],
            'licence' => 'lora-LICENSE.txt',
        ],
        'merriweather' => [
            'family' => 'Merriweather',
            'label' => 'Merriweather',
            'scripts' => ['latin'],
            'files' => [400 => 'merriweather-latin-400-normal.woff2', 700 => 'merriweather-latin-700-normal.woff2'],
            'licence' => 'merriweather-LICENSE.txt',
        ],
        'pacifico' => [
            'family' => 'Pacifico',
            'label' => 'Pacifico',
            'scripts' => ['latin'],
            'files' => [400 => 'pacifico-latin-400-normal.woff2'],
            'licence' => 'pacifico-LICENSE.txt',
        ],
    ],

    /*
     * The print desk's knobs (plan §4.6, NT1): the office moves them on
     * /admin/bookshop/print (settings `bookshop_print_*`); these are the
     * deploy's defaults. Off until the host can print and a paper is priced.
     */
    'desk' => [
        'enabled' => false,
        'lead_days' => 2,
        'min_sheets' => 1,
        'tiers' => [['sheets' => 10, 'percent' => 5], ['sheets' => 50, 'percent' => 10]],
        'cash_allowed' => false,
        'watermark' => 'PROOF — Akuru Bookstore',
        'max_rows' => 500,
        'max_upload_kb' => 10240,
    ],

    // The proof the customer sees (plan §5.2): a raster at screen resolution,
    // never the print file. A4 at 96 dpi.
    'proof' => [
        'width_px' => 794,
        'height_px' => 1123,
    ],
];
