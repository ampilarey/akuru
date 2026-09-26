<?php

/**
 * Akuru Bookstore (docs/BOOKSHOP_PLAN.md). Knobs the office may later move
 * into a settings screen; env-backed until then.
 */
return [
    // Decision 5 (2026-09-25): 10–15% on goods, none on delivery; the
    // default is the low end, overridable per vendor by the office.
    'default_commission_rate' => (float) env('BOOKSHOP_DEFAULT_COMMISSION_RATE', 10),

    'currency' => 'MVR',

    // Product photos: public media, jpeg/png/webp.
    'photos' => [
        'max_per_product' => 8,
        'max_kilobytes' => 5120,
        'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
    ],

    'variants' => [
        'max_per_product' => 30,
    ],

    /*
     * B2 — checkout (plan §4, §8; decisions 4, 6, 7).
     */
    /*
     * B4 — the storefront designer (plan §6.1, §6.2; decisions 10 and 14).
     * The design is data on the vendor, never code: a theme is a preset or
     * a palette of hex colours, checked for contrast on save and again on
     * publish; fonts come from this approved list only. Every font stack
     * ends in the system Thaana and Arabic fallbacks, so a vendor's choice
     * never breaks Dhivehi or Arabic text.
     */
    'storefront' => [
        'images' => [
            'max_kilobytes' => 5120,
            'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        ],
        // Google Fonts serve every face but Faruma, which ships under
        // public/fonts (decision 14: Faruma and Noto Sans Thaana; MV Waheed
        // only if a licence turns up).
        'fonts' => [
            'latin' => ['Inter', 'Merriweather', 'Poppins', 'Lora', 'Bree Serif', 'Courier Prime'],
            'dhivehi' => ['Faruma', 'Noto Sans Thaana'],
            'arabic' => ['Noto Naskh Arabic', 'Amiri'],
            'self_hosted' => ['Faruma'],
        ],
        // Decision 10: presets, with Akuru's own palette reserved for a
        // vendor the office has badged "Akuru partner".
        'presets' => [
            'akuru' => ['label' => 'Akuru maroon and beige', 'badge' => 'akuru_partner', 'colors' => ['primary' => '#7A1F2B', 'secondary' => '#E9D8B4', 'accent' => '#8A5A0B', 'page_bg' => '#FBF7F1', 'card_bg' => '#FFFFFF', 'text' => '#2B1B1E', 'on_primary' => '#FFFFFF', 'on_accent' => '#FFFFFF']],
            'ocean' => ['label' => 'Ocean', 'colors' => ['primary' => '#0F4C81', 'secondary' => '#CFE8F3', 'accent' => '#0B7285', 'page_bg' => '#F4FAFC', 'card_bg' => '#FFFFFF', 'text' => '#12303F', 'on_primary' => '#FFFFFF', 'on_accent' => '#FFFFFF']],
            'forest' => ['label' => 'Forest', 'colors' => ['primary' => '#1F5F3F', 'secondary' => '#DDEBDD', 'accent' => '#8C5A16', 'page_bg' => '#F5F8F4', 'card_bg' => '#FFFFFF', 'text' => '#1B2A20', 'on_primary' => '#FFFFFF', 'on_accent' => '#FFFFFF']],
            'sand' => ['label' => 'Sand', 'colors' => ['primary' => '#8A5A2B', 'secondary' => '#F2E6D3', 'accent' => '#B0413E', 'page_bg' => '#FBF6EE', 'card_bg' => '#FFFFFF', 'text' => '#3A2A1A', 'on_primary' => '#FFFFFF', 'on_accent' => '#FFFFFF']],
            'night' => ['label' => 'Night', 'colors' => ['primary' => '#1E1B4B', 'secondary' => '#3B3765', 'accent' => '#F2C778', 'page_bg' => '#14122E', 'card_bg' => '#1F1C40', 'text' => '#F3F0FF', 'on_primary' => '#FFFFFF', 'on_accent' => '#1A1400']],
            'ink' => ['label' => 'Ink', 'colors' => ['primary' => '#111827', 'secondary' => '#E5E7EB', 'accent' => '#1D4ED8', 'page_bg' => '#FFFFFF', 'card_bg' => '#F9FAFB', 'text' => '#111827', 'on_primary' => '#FFFFFF', 'on_accent' => '#FFFFFF']],
        ],
        'max_versions_shown' => 20,
    ],

    /*
     * B3 — returns (decision 8): seven days from delivery or collection,
     * unused, the buyer paying return delivery unless the item was faulty.
     * A shop may offer longer, never shorter. Decision 15: a shop sees its
     * customer's phone and address until the order has closed (delivered,
     * collected or cancelled) and this window has passed, then masked.
     */
    'returns' => [
        'window_days' => 7,
        'max_window_days' => 60,
    ],

    'checkout' => [
        // Audit finding 2: stock is reserved while the customer pays.
        'reservation_minutes' => (int) env('BOOKSHOP_RESERVATION_MINUTES', 30),
        // Payment methods offered at checkout (decision 7). Cash on delivery
        // is B9.
        'methods' => ['card', 'wallet', 'bank_transfer'],
        'max_quantity_per_line' => 50,
    ],

    /*
     * Decision 4: prices are tax-inclusive; a tax class per product; a tax
     * line only for GST-registered vendors. Rates are percentages of the
     * tax-exclusive price (GST general rate in the Maldives is 8% from
     * 2023). A change in law is a settings edit, never a code change.
     */
    'tax' => [
        'rates' => [
            'standard' => (float) env('BOOKSHOP_TAX_STANDARD', 8),
            'zero_rated' => 0,
            'exempt' => 0,
        ],
    ],

    /*
     * Decision 6: the delivery methods a vendor starts from ("Start from
     * the template" in the portal). A vendor with none uses these as they
     * are, at these fees. Boat fees are paid to the carrier on arrival.
     */
    'delivery_template' => [
        ['kind' => 'collect_vendor', 'name' => 'Collect from the shop', 'fee' => 0, 'handling_days' => 1],
        ['kind' => 'collect_akuru', 'name' => 'Collect from Akuru Institute', 'fee' => 0, 'handling_days' => 2],
        ['kind' => 'courier_male', 'name' => 'Delivery in Malé, Hulhumalé and Villimalé', 'fee' => 30, 'free_over' => 500, 'handling_days' => 2],
        ['kind' => 'courier_atolls', 'name' => 'Courier to the atolls', 'fee' => 80, 'handling_days' => 3],
        ['kind' => 'boat', 'name' => 'Boat to the atolls (fee paid to the boat on arrival)', 'fee' => 0, 'carrier_paid_on_arrival' => true, 'handling_days' => 3],
    ],

    /*
     * Bank transfer (decision 7): the account the customer transfers to.
     * Set on the host; never committed. With no account number the method
     * is not offered.
     */
    'bank_transfer' => [
        'bank' => env('BOOKSHOP_BANK_NAME', 'Bank of Maldives'),
        'account_name' => env('BOOKSHOP_BANK_ACCOUNT_NAME', 'Akuru Institute'),
        'account_number' => env('BOOKSHOP_BANK_ACCOUNT_NUMBER'),
        'slip_max_kilobytes' => 8192,
        'slip_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
    ],
];
