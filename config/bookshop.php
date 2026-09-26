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
        // B5 (§6.3–§6.5): how much a storefront may hold, and where a video
        // may come from (URL only; the embed is built here, never pasted).
        'max_sections' => 20,
        'max_pages' => 10,
        'max_collections' => 20,
        'max_library_images' => 60,
        'video_hosts' => ['youtube.com', 'youtu.be', 'vimeo.com'],
        // The published storefront is read on every vendor page; cached per
        // vendor and locale, cleared on publish and on moderation (§10).
        'cache_seconds' => 600,
        // Best sellers: paid order lines in this many days (§6.3 "automatic").
        'best_seller_days' => 90,
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

    /*
     * B6 — money to vendors (§5 "Money", §7 "Payouts", §8; decisions 4 and
     * 5). Commission is on goods only, at the vendor's rate or the default
     * above; an earning matures after the return window from delivery; the
     * owner asks for the matured balance once it reaches the minimum.
     * Akuru invoices its commission monthly under its own name and TIN,
     * with GST on it only when Akuru is registered — the owner's numbers,
     * set on the host, never committed.
     */
    'money' => [
        'payouts_enabled' => filter_var(env('BOOKSHOP_PAYOUTS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'min_payout' => (float) env('BOOKSHOP_MIN_PAYOUT', 100),
        'issuer_name' => env('BOOKSHOP_ISSUER_NAME', 'Akuru Institute'),
        'issuer_tin' => env('BOOKSHOP_ISSUER_TIN'),
        'issuer_gst_registered' => filter_var(env('BOOKSHOP_ISSUER_GST_REGISTERED', false), FILTER_VALIDATE_BOOLEAN),
        'commission_tax_rate' => (float) env('BOOKSHOP_COMMISSION_TAX_RATE', 8),
        'invoice_prefix' => 'ACI',
    ],

    /*
     * B7 — shop polish and trust (§4, §6.5; decision 12). A product is
     * "New" for this many days; "Bestseller" means among the top sellers by
     * units paid for in the storefront's best-seller window. Reviews go
     * live at once and the office may hide them (§4 "office may hide");
     * `BOOKSHOP_REVIEWS_PREMODERATE` holds each for the office instead.
     */
    'merchandising' => [
        'new_days' => 30,
        'bestseller_top' => 10,
        'bestseller_min_units' => 2,
        'recently_viewed' => 12,
        'wishlist_max' => 200,
        'home_featured_max' => 12,
        'home_collections_max' => 6,
        'home_hero_max' => 5,
        'vendor_code_max_percent' => 90,
    ],
    'reviews' => [
        'premoderate' => filter_var(env('BOOKSHOP_REVIEWS_PREMODERATE', false), FILTER_VALIDATE_BOOLEAN),
        'max_body' => 2000,
    ],

    'checkout' => [
        // Audit finding 2: stock is reserved while the customer pays.
        'reservation_minutes' => (int) env('BOOKSHOP_RESERVATION_MINUTES', 30),
        // Payment methods offered at checkout (decision 7). Cash on delivery
        // (B9b) is added per basket when the office and every shop allow it.
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
    /*
     * B8: bulk and operations. A CSV import is read, checked and shown
     * before anything is written; the preview is kept this long. Order
     * exports cap at `export_max_rows`.
     */
    'operations' => [
        'import_max_rows' => 2000,
        'import_max_kilobytes' => 2048,
        'import_preview_minutes' => 30,
        'products_per_page' => 50,
        'movements_per_page' => 100,
        'export_max_rows' => 20000,
    ],

    /*
     * B8 (§5 and §7 "email/SMS switches"): every shop notice is in the app;
     * these say which also go by email or SMS. The office's switches
     * (Settings, `/admin/bookshop`) gate each channel for everyone; a shop
     * then chooses per event. SMS is off unless the office turns it on, and
     * only sends where `SMS_LIVE` allows it.
     */
    'notices' => [
        'customer_events' => ['order_paid', 'slip_decided', 'order_progress', 'order_cancelled', 'return_decided', 'refund', 'back_in_stock', 'cart_reminder', 'quote_ready'],
        'vendor_events' => ['new_order', 'customer_cancelled', 'return_requested', 'low_stock', 'review', 'payout_decided', 'invoice', 'quote_requested'],
        'vendor_defaults' => [
            'new_order' => ['email' => true, 'sms' => false],
            'customer_cancelled' => ['email' => true, 'sms' => false],
            'return_requested' => ['email' => true, 'sms' => false],
            'low_stock' => ['email' => true, 'sms' => false],
            'review' => ['email' => false, 'sms' => false],
            'payout_decided' => ['email' => true, 'sms' => false],
            'invoice' => ['email' => true, 'sms' => false],
            'quote_requested' => ['email' => true, 'sms' => false],
        ],
        'office_defaults' => [
            'customer_email' => true,
            'customer_sms' => false,
            'vendor_email' => true,
            'vendor_sms' => false,
        ],
        'sms_max_length' => 300,
    ],

    /*
     * B9a: public vendor onboarding (§3 "apply → approve"). Whether the
     * "Open a shop" form is open is the office's switch (Settings,
     * `/admin/bookshop`); this is its value until the office sets one.
     */
    'onboarding' => [
        'open_by_default' => (bool) env('BOOKSHOP_VENDOR_APPLICATIONS_OPEN', true),
        'setting_key' => 'bookshop_vendor_applications_open',
    ],

    /*
     * B9b: cash on delivery (decision 7). The office's switch (a setting)
     * starts at `enabled_by_default`; each shop opts in on its settings.
     * Only where the shop itself hands the parcel over: its own collection
     * point and its own couriers — not a boat (the carrier takes no cash for
     * the shop) and not the Akuru counter.
     */
    'cod' => [
        'enabled_by_default' => (bool) env('BOOKSHOP_COD_ENABLED', true),
        'setting_key' => 'bookshop_cod_enabled',
        'delivery_kinds' => ['collect_vendor', 'courier_male', 'courier_atolls'],
    ],

    /*
     * B9c: a signed-in customer's cart left alone for `after_hours` is
     * reminded once (in the app, and by email where the office's customer
     * email switch allows); not after `within_days`, and not if they have
     * placed an order since. Hourly on the existing schedule.
     */
    'cart_reminders' => [
        'enabled' => (bool) env('BOOKSHOP_CART_REMINDERS', true),
        'after_hours' => 24,
        'within_days' => 7,
    ],

    /*
     * B9d: bulk quotes for schools. A request needs at least `min_quantity`
     * items from one shop in the cart; the shop's price holds for the days
     * it chooses, up to `max_valid_days`.
     */
    'quotes' => [
        'min_quantity' => 10,
        'default_valid_days' => 14,
        'max_valid_days' => 60,
    ],

    'bank_transfer' => [
        'bank' => env('BOOKSHOP_BANK_NAME', 'Bank of Maldives'),
        'account_name' => env('BOOKSHOP_BANK_ACCOUNT_NAME', 'Akuru Institute'),
        'account_number' => env('BOOKSHOP_BANK_ACCOUNT_NUMBER'),
        'slip_max_kilobytes' => 8192,
        'slip_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
    ],
];
