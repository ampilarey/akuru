# Akuru Bookstore — plan v2.1 (2026-09-25, audited)

> **Named 2026-09-26: the Akuru Bookstore.** The owner first planned it as
> the "Akuru Online Bookshop". Earlier the same day it was renamed the
> "Akuru Online Store" ("It's not only for books but educational items"),
> then the owner chose **Akuru Bookstore**. Bookstores sell stationery and
> educational items too, so the name still covers Fitrah's shop.
>
> Every label a person reads says *Akuru Bookstore*, *Bookstore* in menus,
> and *at Akuru Bookstore* on a vendor's page (decision 11). In Dhivehi it
> is އަކުރު ފޮތްފިހާރަ and in Arabic متجر أكورو للكتب.
>
> The address `/shop`, the office's `/admin/bookshop`, and internal names
> stay unchanged. The internal names are this file's name, the `Bookshop`
> domain, its tables and the `bookshop.manage` permission. Below, "the
> bookshop" means the bookstore; the text records what was decided when.

v2.1 is v2 read back against the code and against how selling works in
the Maldives. The findings and what changed are in **§14**; the fixes are
applied inline, marked *(audit)*.

The owner's brief and confirmations (2026-09-25):

1. Name: **Akuru Online Bookshop**. The Knowledge Library becomes **Akuru Digital Library**.
2. Ordinary e-commerce: cart, checkout, delivery, orders, returns, notifications.
3. **Multi-vendor, invited only** for now — a few vendors (the first sells educational items) selling under Akuru. Public vendors possibly later.
4. **Every vendor's listing appears in the main bookshop** (confirmed: one catalogue).
5. **Vendors customise their own page** — branding, colours, layout — **inside the Akuru Online Bookshop** (confirmed; the owner asked for more customisation than v1 of this plan offered, so §6 is the heart of v2).
6. **Path, not subdomain, for now** (confirmed): `/shop` and `/shop/<vendor>`; a host per vendor stays a later mapping.

This is a large build. The plan is written to be complete before the
first slice, in the repo's discipline: one slice per PR, tests and a
browser walk with each, STATUS updated, no future-phase work "while
you're there" (CLAUDE.md rules 1–12). Nothing here is built yet.

---

## 1. Shape of the thing

| | Akuru Digital Library (exists) | Akuru Online Bookshop (this plan) |
|---|---|---|
| Sells | access to a file, read online | physical goods, delivered or collected |
| After paying | an access grant | an order a vendor fulfils |
| Seller | writers, approved by the office | vendors (shops), invited by the office |
| Seller's page | author page (bio, portrait, works) | **vendor storefront** (branded, themed, sectioned — §6) |
| Money to seller | writer earnings, payouts | vendor earnings, commission, payouts |
| Code | `Domains/Library` | **`Domains/Bookshop`** (new), sharing Commerce, Finance, Media, Notifications |

**One catalogue.** Every active product of every vendor is on `/shop`,
in search, in categories, in collections and in the featured strip, with
the vendor's name on the card. A basket may hold several vendors' items;
one payment; one order per vendor. A vendor storefront is the same
catalogue filtered to that vendor, dressed in their branding.

**One frame.** Whatever a vendor customises, the Akuru Online Bookshop
header, footer, cart, checkout, payment, policies and the line "at Akuru
Online Bookshop" stay. A customer always knows whose site they are on.

---

## 2. Addresses

| Address | What | Notes |
|---|---|---|
| `/shop` | bookshop home | featured, collections, categories, vendors, new arrivals, search |
| `/shop/products/<slug>` | product page | vendor name links to the storefront |
| `/shop/c/<category>` | category listing | filters and sorts |
| `/shop/<vendor>` | **vendor storefront** | branded; sections the vendor arranged |
| `/shop/<vendor>/<collection>` | a vendor's collection | e.g. "Grade 1 starter kit" |
| `/shop/cart`, `/shop/checkout` | cart and checkout | checkout needs sign-in |
| `/my-orders`, `/my-orders/<number>` | the customer's orders | status trail, receipt, return request |
| `/vendor` | vendor portal (Inertia) | products, orders, storefront designer, settings, money |
| `/admin/bookshop` | office (Inertia) | vendors, catalogue, orders, settings, payouts, reports |

Routes live in one group under `config('bookshop.host')` (null = path on
the main site). Moving the shop to `shop.akuru.edu.mv`, or a vendor page
to `<vendor>.akuru.edu.mv` or their own domain, is later: DNS, SSL, one
env line, `SESSION_DOMAIN=.akuru.edu.mv`, and a `vendors.custom_host`
mapping that serves the same storefront. No new screens.

---

## 3. People and roles

| Role | Where | Can |
|---|---|---|
| Customer (any signed-in person; guests browse and carry a cart) | public pages, My orders | browse, cart, check out, pay, track, cancel before dispatch, request a return, review a delivered product, wishlist |
| Vendor owner (member role `owner`) | `/vendor` | everything below, plus members, bank details, payouts, storefront publish |
| Vendor staff (member role `staff`) | `/vendor` | products, stock, orders, storefront drafts (not publish), no money screens |
| Office (`bookshop.manage`) | `/admin/bookshop` | vendors, every product and order, settings, payouts, reports, storefront moderation |
| Super admin | everything | |

Vendor membership is a row on the unified identity (`vendor_members`),
the way writer is a role on a person. A person can belong to more than
one vendor (a switcher in the portal). Public vendor onboarding (apply →
approve, like writers) is a later slice; v1 vendors are created by the
office with an owner account. *(Built in B9a, STATUS §5hg: `/vendor/apply`,
decided on `/admin/bookshop`, the form closable by the office.)*

---

## 4. Customer features (the "all e-commerce features" list)

**Browse and find**
- Home: hero, featured products, collections, categories, vendors, new arrivals, best sellers.
- Listing with filters — category, vendor, price range, in stock, language of a book, age/grade (educational), brand — and sorts — newest, price low/high, best selling, top rated, name. Filters combine and survive in the URL (shareable).
- Search across title, description, SKU, ISBN, tags, vendor; suggestions as you type.
- Product page: gallery with zoom, price and compare-at price, GST note, variants (size / colour / edition / grade), stock state ("3 left", "out of stock — notify me", "made to order, 5 days"), quantity, delivery options and fees per vendor, "collect from" address, vendor card linking to the storefront, related products, "read the e-book" when a library item is linked, reviews and ratings, share links, ISBN/author/publisher/pages/language for books, age/grade/subject for educational items.
- Wishlist (signed in), recently viewed.

**Cart and checkout**
- Server-side cart per person; a guest cart on the session that merges on sign-in; quantities; stock re-checked on every change; discount code box; per-vendor sub-totals shown.
- Checkout (sign-in required, as the library and gift cards; *(audit)* the sign-in step offers the existing **mobile OTP** login so a first-time buyer needs only a phone number, and checkout asks for nothing the order does not need — a guest checkout without any account is **not** offered, because orders, returns and refunds need an identity to land on): address book (atoll, island, street, phone), delivery method **per vendor** (collect from the vendor / collect from Akuru / delivery in Malé–Hulhumalé–Villimalé / atolls by courier or boat), delivery fee per vendor, order notes, gift message, then payment: **card (BML)**, **wallet**, or *(audit)* **bank transfer with a slip upload** (the order waits as *pending payment* until the vendor or the office confirms the transfer — the usual way to pay a small shop in the Maldives, so it launches in B2, not B9), with a discount code where it applies. Gift cards are wallet money once redeemed (rule 12). Cash on delivery stays a decision (§13 no. 7).
- *(audit)* **Stock reservation**: starting checkout reserves the basket's quantities for 30 minutes (a `stock_reservations` row per item); a card payment not confirmed and a bank transfer not slipped in that time releases the reservation and the checkout expires (the existing scheduler runs the sweep, like `akuru:prune-expired`). A reserved item shows "reserved — 2 left" to others. This replaces v2's "needs attention on oversell" as the first line of defence; needs-attention remains for the rare case a reservation lapsed and the webhook still arrived.
- Order confirmation page and email receipt with a tax line and the vendor's TIN; one **order number per vendor**, grouped under one checkout number. *(audit)* Format: checkout `AK-2026-000123`, order `AK-2026-000123-FIT` (the vendor's three-letter code), both sequential per year and never reused; the receipt number is Finance's own.

**After the order**
- My orders: status trail (pending payment → paid → processing → ready to collect / dispatched → collected / delivered), tracking note, receipt, packing details, message the vendor (existing message threads), cancel before dispatch, request a return within the window, see the refund land in the wallet or on the card.
- *(audit)* **Atoll delivery by boat**: the vendor hands the parcel to a launch or supply boat and the customer pays the boat fee on arrival. The delivery method carries a "fee paid to the carrier on arrival" flag so the checkout shows "boat fee paid on arrival (about MVR 20–50)" instead of charging it, and the order's delivery fee is zero.
- *(audit)* **Refunds** go through Finance's existing `RefundPaymentAction`: a wallet-paid order refunds to the wallet at once; a card-paid order is refunded by the office through BML (a vendor accepts the return, the office presses refund) and the customer sees "refund on its way to your card"; a bank-transfer order is refunded to the wallet by default, or by the office's manual transfer where the customer asks. The vendor's earning reverses in every case.
- Notices in app and by email (SMS where the office enables it): paid, ready, dispatched, delivered, refund done, back in stock (if they asked). *(audit)* Email and SMS are queued, so they need the production **queue worker** (OWNER_ACTIONS item 3, BACKLOG C6) — B2's deploy gate includes it running, otherwise customers get in-app notices only.

**Trust**
- Reviews only from customers who received the product; vendor may reply; office may hide.
- Every page trilingual-ready (EN/DV/AR) and RTL-safe; product names and descriptions in three languages where the vendor provides them.
- Policies linked at checkout: Reader/Customer Terms, Returns Policy, Delivery Policy, Privacy — seeded CMS pages like the library's, editable by the office. *(audit)* Seeded in B2 by a `BookshopPolicyPagesSeeder`, never overwriting edits: **Shop Terms**, **Delivery & Returns Policy**, **Vendor Agreement** (what a vendor signs up to: commission, payout timing, who handles a return, what the office may moderate) — the vendor accepts the agreement on first sign-in to the portal, and the acceptance is dated on `vendor_members`.
- *(audit)* **Customer data**: addresses and phone numbers are the customer's; a vendor sees them only on their own orders, and only until the order closes plus the return window; exports of orders mask the phone after that; a customer can delete an address from the address book at any time (order snapshots keep what the order needed). The privacy page says so.
- *(audit)* **Mobile**: the shop's public pages get a fixed bottom bar (Shop, Search, Cart, Orders) on phone widths, since most Maldivian buyers arrive from Instagram or Viber on a phone.

---

## 5. Vendor portal features

**Products**
- Create/edit: title (EN/DV/AR), slug, short and long description (rich text, sanitised), category, brand, tags, photos (drag to order; the first is the card image), price, compare-at price, cost (private, for margin reports), *(audit)* **tax class** (standard / zero-rated / exempt — books are commonly zero-rated or exempt while stationery is standard, so one GST flag per product was wrong; prices are always tax-inclusive and the receipt shows tax per line), SKU, barcode/ISBN, weight and dimensions, stock and low-stock threshold, track stock or not, "made to order" lead time, variants with their own SKU/price/stock/photo, status (draft / active / archived), visibility (shop-wide or storefront-only), optional link to a library item, book fields (author, publisher, year, pages, language) and educational fields (age range, grade, subject).
- Bulk: CSV import/export of products and stock; duplicate a product.
- Collections: named groups of their products (manual or by tag) for the storefront and for `/shop/<vendor>/<collection>`.

**Orders**
- Queue by status; open an order; mark processing / ready / dispatched (with carrier and tracking note) / delivered; print packing slip and delivery label; cancel with reason (before dispatch); accept or decline a return request; issue a refund (goes through the office's Finance refund; the vendor's earning reverses).
- Message the customer on the order (message threads).

**Storefront designer** — see §6.

**Settings**
- Shop identity: legal name, trading name, TIN, contact email and phone, address, opening hours.
- Delivery: which methods, zones and fees (start from the office's template), collection address, handling days, free delivery over an amount, *(audit)* a minimum order amount per method (a vendor will not deliver a MVR 15 pencil to Hulhumalé), and the "fee paid to the carrier" flag for boat delivery.
- Returns: window, conditions, who pays return delivery.
- *(audit)* **Holiday mode**: pause the shop for a date range (Eid, stock-take, travel) — products stay visible marked "back on <date>", the cart refuses them, and the storefront shows the vendor's notice.
- Members: invite by email, roles. *(audit)* Invitations are queued mail; the portal also shows a copyable invitation link so the owner can send it by Viber when the queue worker is not running.
- Bank details for payouts.
- Notifications: which events email or SMS them.

**Money**
- Earnings (sales less commission, less refunds), maturing after the return window; payout requests; payout history; statements by month with CSV.

**Reports**
- Sales by product/period, best sellers, low stock, order status counts, returns rate, storefront visits and conversion (basic).

---

## 6. Vendor storefront customisation (the owner's ask)

The storefront is the vendor's shop inside the bookshop. The vendor
designs it in the portal's **Storefront designer**, previews it, and
publishes it. The design is data (JSON on the vendor), never code, so it
is safe, versioned, and renders inside the fixed Akuru frame.

### 6.1 Identity and branding
- Shop name and tagline (three languages), logo (light and dark variants), favicon, banner/hero image or a short slideshow, a **story** ("About us") with photos, contact block (phone, email, Viber, address with map link), opening hours, social links (Facebook, Instagram, TikTok, YouTube, X), badges the office grants (verified vendor, Akuru partner).

### 6.2 Theme
- **Colours**: primary, secondary, accent, page background, card background, text — as a palette the vendor picks from **presets** (Akuru maroon/beige, ocean, forest, sand, night, ink) or sets by hex, with contrast checked automatically (a colour pair that fails readability is refused, with the reason).
- **Typography**: heading and body from an approved list (Latin: Inter, Merriweather, Poppins, Lora, Bree Serif, Courier Prime; Dhivehi: MV Waheed, Faruma; Arabic: Noto Naskh, Amiri), size scale (compact / regular / large). *(audit)* The Latin and Arabic faces are on Google Fonts; **MV Waheed and Faruma are not** — they ship self-hosted under `public/fonts/` only after their licence is checked (Faruma is freely redistributable; MV Waheed's terms must be confirmed by the owner, otherwise the Dhivehi list is Faruma plus Noto Sans Thaana). Every theme's font stack ends in the system Thaana and Arabic fallbacks so a vendor's choice never breaks Dhivehi or Arabic text.
- **Shape**: corner radius (square / soft / round), button style (filled / outlined), card style (flat / shadow / bordered), banner height, image ratio for product cards (square / portrait / landscape).
- **Dark mode** variant optional; the vendor sets both or lets the theme derive one.
- Themes have **named versions**: save as draft, preview, publish, roll back to a previous published version.

### 6.3 Layout: sections the vendor arranges
A storefront is an ordered list of sections, each with its own settings, arranged in the designer. *(audit)* The designer is built in two steps to keep B5 honest: first a **form-based** designer (a list of sections with up / down / hide buttons and a settings form each, plus a live **preview in an iframe** of the real storefront renderer, so what the vendor sees is what publishes), then drag-to-order as a polish item in B7. A form beats a half-working drag surface, and it is keyboard-accessible from day one. Available sections (v1 set; the office can add types over time):

| Section | What it shows |
|---|---|
| Hero | banner or slideshow, headline, sub-headline, one or two buttons (to a collection, a product, or a page) |
| Announcement bar | one line at the top ("Free delivery in Malé over MVR 500"), with an end date |
| Featured products | hand-picked products, 4/8/12, in a grid or a carousel |
| Collection | one collection, with "see all" |
| Category tiles | the vendor's categories as picture tiles |
| New arrivals / Best sellers | automatic |
| Text and image | a heading, rich text, an image left or right; for stories, offers, how-to-order |
| Image gallery | photos of the shop, events, classrooms |
| Testimonials | quotes the vendor enters, or approved product reviews |
| FAQ | question/answer accordion |
| Delivery and returns | rendered from their settings, in their words |
| Contact and map | contact block with a map embed by coordinates |
| Video | an embedded YouTube/Vimeo video (URL only, no arbitrary HTML) |
| Newsletter (later) | collect emails for the vendor's news, with consent |
| Custom HTML (never) | not offered: vendor pages take structured content only, sanitised, so no script or style can escape the frame |

Each section has: visibility (published / hidden / scheduled between dates), language variants of its text, and mobile ordering if different.

### 6.4 Pages
- Besides the storefront home, a vendor may add simple **pages** under their storefront (`/shop/<vendor>/p/<slug>`): About, Delivery, Returns, Bulk orders for schools, Contact. Built from the same sections.
- Their storefront **navigation**: which collections and pages appear in the storefront's own menu bar (inside the Akuru frame).

### 6.5 Merchandising
- Featured products and collections; product badges ("New", "Sale", "Bestseller", custom text); a per-vendor **promotion**: discount codes funded by the vendor, scoped to their products (Commerce's discount codes with a vendor scope), and a "sale" compare-at price on products; a "spend MVR X, get free delivery" rule.

### 6.6 What stays Akuru's (the frame)
- The site header and footer, the "at Akuru Online Bookshop" line under the shop name, the cart, checkout, payment, receipts, Akuru's policies, the customer account and orders, search across the whole shop, and the vendor card on every product.
- The office can **moderate**: unpublish a storefront, require changes, or lock a section type for a vendor; it sees the published and draft versions.

### 6.7 SEO and sharing
- Per storefront and per page: title, description, share image; canonical URL; sitemap entries; structured data for products (name, price, availability) so search engines list them.

### 6.8 Later, on request
- A vendor's own domain or subdomain mapped to the storefront (§2).
- Storefront analytics beyond basics (funnel, top pages).
- A theme marketplace or per-vendor CSS: ~~not planned~~ — **the owner asked for both (2026-09-26); ADR-039.** Per-vendor CSS **built in B10c** (STATUS §5ho): cleaned, confined to the shop's part of its page, live only once the office approves. The theme gallery follows in B10d.

---

## 7. Office features

- **Vendors**: create with an owner account; profile; commission rate; status (active / suspended); members; storefront moderation; custom host (later); notes.
- **Catalogue**: every product; unpublish; edit any; categories and brands (shared taxonomy); the age/grade/subject lists; featured on the shop home; collections on the shop home.
- **Orders**: every order; intervene; refund (Finance); export.
- **Settings**: shop on/off; GST rate and whether prices include it; default commission; return window; delivery zones template and Akuru-as-collection-point; allowed fonts and theme presets; payment methods on; email/SMS notice switches.
- **Payouts**: requests, decide, statements.
- **Reports**: sales by vendor/period/category, order status counts, low stock across vendors, returns, payouts due and paid, GST collected, stored-value liabilities (already on `/admin/commerce`); all CSV.
- **Content**: the customer-facing policy pages (CMS), the shop home's hero and featured strips.

---

## 8. Money, unchanged rules

- Access or fulfilment for anything paid depends on the **BML webhook**, never the return URL (rule 12). Card checkouts become *paid* on the webhook; wallet checkouts immediately.
- Wallet and ledgers are append-only; refunds are reversals.
- Discount codes reduce a price and never buy gift cards; each says who funds it (Akuru or the vendor); the vendor's share is computed like writers' (Akuru-funded: on full price; vendor-funded: on the discounted price).
- One BML payment per checkout (payable alias `bookshop_checkout`); the webhook marks every order under it. Stock is decremented on *paid*; if a product sold out between checkout and payment the order is marked *needs attention* and the vendor and office are told; never a silent oversell.
- Tax: *(audit)* a **tax class per product** (standard / zero-rated / exempt) and a rate per class in the office settings; prices are inclusive; the receipt shows tax per line and a total, under the vendor's TIN, only when the vendor is GST-registered (a vendor below the registration threshold sells without a tax line — the plan's earlier "GST on every receipt" was wrong for a small shop). **Rates, registration and who files are the owner's** (§13 no. 4).
- *(audit)* **Akuru's commission is a service Akuru sells to the vendor**, so Akuru issues the vendor a monthly **commission tax invoice** (Akuru's TIN, GST on the commission if Akuru is registered), and the statement nets it against sales. Vendor payouts are then sales − refunds − commission invoice, which is what an accountant expects to see.
- Delivery fees go to the vendor who delivers; Akuru's commission applies to goods, not to delivery fees (decision §13 if the owner prefers otherwise). Boat fees paid to the carrier on arrival never touch Akuru's books.
- *(audit)* **Currency**: MVR only, stored as **decimal(10,2)** like the wallet, gift cards and discount codes (corrected in B1a: this line first said "integer laari like the wallet", and the wallet is decimal); no USD pricing in v1 (tourists are not the market; a decision if a vendor asks).

---

## 9. Data (all additive; morph aliases registered in the same slice per ADR-005)

**Vendors**: `vendors` (name, slug fixed at creation, legal name, TIN, status, commission_rate, contact, address, hours, custom_host nullable, settings JSON), `vendor_members` (user, vendor, role), `vendor_bank_details`, `vendor_delivery_methods` (method, zone, fee, free_over, days), `vendor_return_policies`.

**Storefront**: `vendor_storefronts` (vendor, draft_theme JSON, published_theme JSON, draft_sections JSON, published_sections JSON, published_at, published_by), `vendor_storefront_versions` (append-only snapshots for roll-back), `vendor_pages` (vendor, slug, title ×3, sections JSON, status), `vendor_collections` (vendor, slug, name ×3, rule JSON or manual), `vendor_collection_products`.

**Catalogue**: `product_categories` (shared, trilingual), `brands`, `products` (vendor, category, brand, slug, titles ×3, descriptions ×3, price, compare_at, cost, tax_class *(audit)*, sku, barcode, weight, dimensions, stock, low_stock_at, track_stock, lead_days, status, visibility, featured, library_item_id, attributes JSON for book/educational fields), `product_images` (media id, sort), `product_variants` (name, options JSON, sku, price, stock, image), `product_tags`, `product_reviews` (order_item, rating, text, status, vendor_reply), `wishlists`, `stock_movements` (append-only: in, sale, return, adjustment, by whom).

**Orders**: `customer_addresses`, `carts`, `cart_items`, `bookshop_checkouts` (user, number, totals, discount, payment_id, payment_method, status, expires_at *(audit)*), `stock_reservations` *(audit)* (checkout, product/variant, qty, expires_at), `bank_transfer_slips` *(audit)* (checkout, media id, reference, confirmed_by, confirmed_at), `orders` (number, checkout, vendor, user, status, delivery method and fee, carrier_pays flag, address snapshot, subtotal, discount, tax, total, notes), `order_items` (product and variant snapshot: name, sku, unit price, qty, tax_class, tax), `order_events` (append-only trail with actor), `order_returns` (order_item, reason, status, refund_id), `order_messages` via existing message threads.

**Money**: `vendor_earnings` (order, gross, discount, funding source, commission, net, status, available_at), `vendor_commission_invoices` *(audit)* (vendor, period, amount, tax, number), `vendor_payouts`, `vendor_statements`.

**Vendors** also carry *(audit)* `code` (three letters for order numbers, unique), `holiday_from`, `holiday_until`, `holiday_notice`, `gst_registered`, and `vendor_members.agreement_accepted_at`.

No `academic_year_id`: commerce, not a term's record (every commerce table's precedent).

---

## 10. Architecture and quality

- **Domain**: `Domains/Bookshop` with Actions, Models, Http, Contracts, Enums, Listeners, Console. Cross-domain only through Commerce/Finance/Media/Notifications Actions and DTOs (rule 3). No SDKs in domain logic (rule 4); BML and mail stay behind their existing interfaces.
- **Storefront rendering**: sections are structured data → a Blade renderer per section type in the public zone (the library precedent for public Blade), with the theme applied as CSS custom properties on the storefront root; no vendor-supplied CSS or HTML; rich text through `HtmlSanitizer` with a vendor profile (no scripts, iframes only from the allowed video hosts, no inline styles).
- **Images**: public media, resized variants (card, gallery, zoom) at upload through the existing `ImageProcessorInterface` (Media's `WebPImageService`, already behind a contract — *(audit)* not raw `gd` calls in Bookshop, rule 4); lazy loading; a size cap.
- **Performance**: catalogue queries with counts (`withCount`) and cursor pagination; storefront published JSON cached per vendor and cleared on publish; product listing cached per filter set for a minute.
- **Search**: database LIKE with a prefix index for v1; a search service (Meilisearch) is a later binding behind a contract if the catalogue grows.
- **Security**: every write validated into DTOs; vendors reach only their vendor's rows (a `VendorScope` resolved from membership, the way `VerifiedGuardianLink` is the one definition for families — *(audit)* pinned by an architecture test that every query in `Domains/Bookshop/Http/Vendor/*` goes through the scope, the way the private-media reader test lists its allowed callers); office routes gated by `bookshop.manage`; rate limits on cart and checkout; stock and money changes in transactions with row locks; append-only trails; *(audit)* bank-transfer slips are private media (readable by the paying customer, the vendor of that order and the office only).
- *(audit)* **Scheduler**: checkout expiry, reservation release, earnings maturing and holiday-mode transitions are commands on the existing schedule in `routes/console.php` (which already runs `payments:reconcile` and `akuru:prune-expired`); nothing new to install on the host beyond the queue worker.
- **Trilingual and RTL**: all labels in `lang/*/shop.php` with DV/AR first passes; every listing has CSV export (conventions).
- **Accessibility**: theme contrast check; keyboard-navigable designer; alt text required on product images.
- **Tests**: Pest feature tests per slice; architecture suite stays green (public routes and Blade baselines, morph map, thin controllers, discount callers — the gift-card rule test lists the checkout as a permitted discount caller because it buys goods).
- **Walks**: `scripts/smoke/shop.mjs` (customer: find, cart, checkout to the bank, orders) and `vendor.mjs` (vendor: product with photos, storefront designed and published, order fulfilled), seeded by `SmokeMarkerSeeder`.

---

## 11. Slices, in order (one PR each, walked in a browser)

| # | Slice | A person can, at the end |
|---|---|---|
| B0 | **Rename** the Knowledge Library to *Akuru Digital Library* in every label (EN/DV/AR); `/library` stays. **Built 2026-09-25 (STATUS §5gu).** | see the new name across the library |
| B1a | **Vendors and the portal** *(audit: B1 split — v2's B1 was three slices in one)* — domain scaffold, `vendors`, `vendor_members`, morph aliases, office vendor screen (create with owner account, invitation link), vendor portal shell with the Vendor Agreement acceptance, products (photos through `ImageProcessorInterface`, variants, stock, tax class, book and educational fields), shared categories and brands, Fitrah seeded on staging. **Built 2026-09-26 (STATUS §5gv)** — the invitation is a one-time password shown once to the office, not a mailed link; photos are stored as public media and resized in B1b, where they are first shown (`ImageProcessorInterface` has no resize method yet); the seven models carry morph aliases (every domain model does); variant photos deferred. | the office creates Fitrah; Inaaya signs in, accepts the agreement and lists a product with photos |
| B1b | **The public shop** — `/shop` home and listing with filters, product page, category pages, a plain `/shop/<vendor>` page, search, Shop in the header, menu and mobile bottom bar, sitemap entries. **Built 2026-09-26 (STATUS §5gw)** — photos resized through a new `ImageProcessorInterface::getResizedWebPPath`; vendor contact details kept off public pages until ordering; best-selling and top-rated sorts, featured strips and brand filter UI are B7. | anyone finds Fitrah's product on the shop and on her page |
| B2 | **Cart and checkout** — cart with guest merge, addresses, delivery methods and fees (incl. minimums and carrier-paid boat fees), discount codes, **BML, wallet and bank transfer with slip upload**, stock reservation and checkout expiry on the scheduler, one order per vendor under one checkout, webhook confirmation, stock decrement and needs-attention, receipt with tax lines, My orders, notices, the three seeded policy pages, OTP sign-in at checkout. Deploy gate: queue worker running on production. **Built 2026-09-26 (STATUS §5gz)** — nothing in B2 is queued (notices are in-app and written inline; the webhook confirms synchronously), so the queue-worker gate did not bite; what B2 does need on the host is `BOOKSHOP_BANK_ACCOUNT_NUMBER` for bank transfer to be offered, the scheduler for the expiry sweep, and BML's webhook secret for card payments (OWNER_ACTIONS 2). Vendor-side slip confirmation and order messages are B3 as planned. | a customer buys two vendors' items in one basket and both orders show as paid; a bank-transfer order waits for confirmation and then shows as paid |
| B3 | **Fulfilment and returns** — vendor order queue with status steps, packing slip and label, tracking note, bank-transfer confirmation by the vendor, customer cancellation before dispatch, vendor cancellation with reason, holiday mode, return requests and refunds through `RefundPaymentAction` (wallet at once, card by the office), order messages. **Built 2026-09-26 (STATUS §5ha)** — the owner asked for B4 first, then for B3, so the order stands. A confirmed bank transfer is now recorded as a Finance manual payment, so its refund goes through `RefundPaymentAction` like a card's; wallet refunds are a credit on Commerce's ledger (a wallet checkout has no Finance payment). Return window and conditions and holiday mode are columns on `vendors`, not the plan's `vendor_return_policies` table (one row per vendor either way). Holiday mode is worked out from its dates, so nothing runs on the scheduler. Collections, stock movements and the vendor's earning reversal are B5, B8 and B6. | a vendor dispatches; the customer tracks it and, if needed, returns it |
| B4 | **Storefront designer, part 1: identity and theme** — logo, banner, story, contact, hours, socials; colour presets and custom palette with contrast check; fonts; shape; draft / preview / publish / roll back. **Built 2026-09-26 (STATUS §5hb)** — the office's badges (§6.1) came with it, since decision 10 ties Akuru's palette to the partner badge; the preview is the real public renderer in an iframe, as §6.3 asks; story photos, moderation (§6.6) and published-JSON caching (§10) wait for B5; the theme reaches the page only as CSS custom properties. | a vendor's page looks like their brand, inside the Akuru frame |
| B5 | **Storefront designer, part 2: sections, pages, collections, navigation** — the section types of §6.3 in the form-based designer with iframe preview, scheduling, vendor pages, collections, storefront menu, SEO fields. **Built 2026-09-26 (STATUS §5hc)** — with the office's moderation (§6.6: require changes, take down, lift, lock section types), the image library, product structured data and the per-vendor published cache (§10); drag-to-order stays B7. | a vendor arranges a hero, featured products, a story, FAQ and contact, adds an About page, and publishes — walked, `scripts/smoke/sections.mjs` 22/22 |
| B6 | **Money to vendors** — commission, monthly commission tax invoice, earnings maturing after the return window, payout requests and decisions, statements, vendor and office reports, tax report. **Built 2026-09-26 (STATUS §5hd)** — statements are computed, not a table; vendor-funded codes are B7 (the earning already knows the model). | a vendor sees what they are owed and is paid — walked, `scripts/smoke/vendor-money.mjs` 16/16 |
| B7 | **Shop polish and trust** — search suggestions, best-selling and top-rated sorts, wishlist, recently viewed, reviews with vendor replies and office moderation, back-in-stock notices, product badges, vendor-funded discount codes scoped to their products, free-delivery-over rules, shop-home merchandising by the office, drag-to-order in the designer. **Built 2026-09-26 (STATUS §5he)** — reviews on and published at once, premoderation behind `BOOKSHOP_REVIEWS_PREMODERATE`; vendor codes live in Commerce with a vendor scope; recently viewed is per session. | the shop feels like a shop — walked, `scripts/smoke/polish.mjs` 29/29 |
| B8 | **Bulk and operations** — CSV import/export of products and stock, stock movements log, low-stock alerts, order exports, email/SMS switches. **Built 2026-09-26 (STATUS §5hf)** — the import checks and previews before it writes; duplicate and bulk status on a paged product list; order-line exports; email is queued and SMS goes through the SMS contract, both behind the office's switches and each shop's choice per event. | a vendor with 500 items can manage them — walked, `scripts/smoke/operations.mjs` 21/21 |
| B9 | **Later, on request** — public vendor onboarding (apply → approve), custom host per vendor, whole-shop subdomain, cash on delivery, newsletter section, abandoned-cart reminders, bulk quotes for schools (B2B), USD pricing, search service, analytics funnel. **Requested 2026-09-26 ("B9"); built as sub-slices, one PR each.** B9a public vendor onboarding **built (STATUS §5hg)**; B9b cash on delivery **built (§5hh)** — the shop's own delivery or collection only, paid when the shop takes the cash, the commission owed back through payouts; B9c newsletter section and abandoned-cart reminders **built (§5hi)** — the shop sends its own newsletters from the exported list. B9d bulk quotes for schools **built (§5hj)** — asked from the cart (ten items or more), priced per line for a number of days, accepted into the cart, charged at the quoted price while it holds. B9e the shop's funnel and search behind a contract **built (§5hk)** — daily counters only, nothing about the visitor; the database search by default, a Meilisearch server when the owner has one. B9f a shop's own domain and the whole-shop subdomain **built (§5hl)** — the addresses redirect to the one canonical site (the owner sets up DNS and the hosting panel). B9f's dollar prices were **removed at the owner's word (B10a, §5hm)**: MVR only. B10b **Bookstore admins** (`bookshop_manager`, §5hn) run `/admin/bookshop` without being school admins. **Every B9 item is built.** | **built** (B9a–B9f, STATUS §5hg–§5hl) |

Rough size: B1a, B1b and B3 are each about the gift-card slice times
two, B2 times three; B4 and B5 together are the largest piece (the
designer); B6–B8 are each a library-sized slice. Order can change after
B1b if the owner wants the designer before checkout to show vendors their
pages early.

---

## 12. What this plan reuses

`InitiatePayablePaymentAction` and the `PaymentConfirmed` listener
pattern (Finance); `RefundPaymentAction` (Finance); `DebitWalletAction`,
`CreditWalletAction`, `ResolveDiscountAction` and funding sources
(Commerce); `StorePublicMediaAction` / `ResolvePublicMediaUrlAction`
(Media); `SendUserNotificationAction` and message threads
(Notifications); `HtmlSanitizer`; `NavigationMap`; `Csv`; CMS pages for
policies; the Blade-in-public-zone and Inertia-elsewhere split; the seeded
smoke markers and walks; `VerifiedGuardianLink` as the model for a single
scope definition (`VendorScope`).

---

## 13. Decisions the owner makes (not guessed)

| # | Decision | Recommendation | Status |
|---|---|---|---|
| 1 | Path now, subdomain later | as §2 | **confirmed 2026-09-25** |
| 2 | One catalogue for all vendors | as §1 | **confirmed 2026-09-25** |
| 3 | Vendors customise their storefront inside the Akuru frame | as §6 | **confirmed 2026-09-25** |
| 4 | Tax: rates per class, prices inclusive, TIN on receipts, which vendors are registered | inclusive prices; standard / zero-rated / exempt classes at the current rates; a tax line only for GST-registered vendors; Akuru invoices its commission (audit) | **decided 2026-09-25: as recommended.** The rates live in the office settings, not in code, so a change in law is a settings edit. |
| 5 | Default commission on goods; commission on delivery fees? | 10–15% on goods; none on delivery | **decided 2026-09-25: as recommended** — the default is set at **10%** (the low end of the range, for a first vendor), overridable per vendor by the office; no commission on delivery fees. |
| 6 | Delivery zones and fees vendors start from; Akuru's office as a collection point? | Malé–Hulhumalé–Villimalé flat; atolls by courier at the vendor's fee or by boat with the fee paid to the carrier on arrival; yes to Akuru collection | **decided 2026-09-25: as recommended.** |
| 7 | Payment methods at launch | **card, wallet and bank transfer with slip upload in B2** (audit: bank transfer is how most small shops here are paid); cash on delivery in B9 unless the owner wants it at launch | **decided 2026-09-25: as recommended** — card, wallet and bank transfer in B2; cash on delivery in B9. |
| 8 | Return window and returns policy | 7 days, unused, buyer pays return delivery unless faulty | **decided 2026-09-25: as recommended** (the office default; a vendor may offer longer, never shorter). |
| 9 | Vendors in v1 | invitation only | **decided 2026-09-25: the first vendor is the owner's wife's educational-items shop, an independent business selling inside the bookshop; **Fitrah** (owner Fathimath Inaaya, `/shop/fitrah`) — identity, palette, logo and proposed theme in `docs/vendors/FITRAH.md`; contact, delivery, returns and first products still to come. Bank details and ID documents are entered by her in the vendor portal, never sent through the conversation or committed.** |
| 10 | Storefront fonts and presets the office allows; may vendors use Akuru's own maroon palette? | the §6.2 list; yes to presets, Akuru's palette marked "Akuru partner" | **decided 2026-09-25: as recommended** (with decision 14's Dhivehi list). |
| 11 | Does a vendor page show "at Akuru Online Bookshop" under their name? | yes, always | **decided 2026-09-25: yes, always.** |
| 12 | Should reviews be on from B7, or off until vendors ask? | on, moderated | **decided 2026-09-25: on from B7, moderated.** |
| 13 | Order of slices: checkout (B2–B3) before the designer (B4–B5), or designer first? | checkout first: money before polish | **decided 2026-09-25: checkout first** — the order in §11 stands. |
| 14 | *(audit)* Dhivehi fonts: may MV Waheed be self-hosted (licence), or Faruma and Noto Sans Thaana only? | Faruma + Noto Sans Thaana unless the owner holds an MV Waheed licence | **decided 2026-09-25: Faruma and Noto Sans Thaana**; MV Waheed joins only if a licence turns up. |
| 15 | *(audit)* Customer data retention: how long may a vendor see a customer's phone and address after an order closes? | the return window, then masked in the vendor's view and exports | **decided 2026-09-25: as recommended.** |
| 16 | *(audit)* Vendor Agreement text (commission, payout timing, returns responsibility, moderation): the office drafts it, or the owner supplies one? | seeded first draft by B2, like the library policy pages; the owner edits in the page editor | **decided 2026-09-25: as recommended** — first draft seeded in B2, flagged "draft" until the owner reads it (BACKLOG A6's pattern). |

All sixteen decisions are made. The owner said *"Go with the
recommendations and build B0"* on 2026-09-25.

---

## 14. Audit of v2 (2026-09-25)

The owner asked for the plan to be audited once more before anything is
built. Each row was checked against the code (what exists to reuse, what
v2 assumed wrongly) and against how a small shop in the Maldives actually
sells. **Fixed inline** means the section text above was changed and is
marked *(audit)*.

| # | Finding | Severity | Resolution |
|---|---|---|---|
| 1 | v2 put **bank transfer** in B9 ("later"). In the Maldives most small-shop purchases are paid by BML/MIB transfer with a slip sent on Viber; a shop that takes only card and wallet at launch turns away the ordinary buyer. | high | Fixed inline: bank transfer with slip upload and vendor/office confirmation moves into B2; decision 7's recommendation changed. Cash on delivery stays a decision. |
| 2 | v2 relied on "needs attention on oversell" as the only protection between checkout and payment. With a slow BML webhook or a bank transfer taking hours, two customers can buy the last item. | high | Fixed inline: stock reservation at checkout start with a 30-minute expiry swept by the existing scheduler; `stock_reservations` and `bookshop_checkouts.expires_at` added to §9. |
| 3 | A single **GST-inclusive flag per product** cannot express that books are commonly zero-rated or exempt while stationery is standard, and v2 printed a GST line on every receipt even for a vendor below the registration threshold. | high | Fixed inline: tax class per product, rate per class in settings, tax line only for GST-registered vendors (`vendors.gst_registered`); decision 4 reworded. |
| 4 | Akuru's **commission** had no tax treatment: it is a service Akuru sells to the vendor and needs an invoice from Akuru, or the vendor's accountant cannot book it. | medium | Fixed inline: monthly commission tax invoice (`vendor_commission_invoices`), netted on the statement; in B6. |
| 5 | **Atoll delivery by boat** is paid to the boat on arrival, not to the shop; v2 charged every delivery fee at checkout. | medium | Fixed inline: a "fee paid to the carrier on arrival" flag on the delivery method; shown, not charged. |
| 6 | **Refunds**: v2 said "wallet or card" without saying who does it. Finance's `RefundPaymentAction` exists; card refunds are an office action through BML, not something a vendor can trigger. | medium | Fixed inline: wallet refunds at once, card refunds by the office on the vendor's accepted return, bank-transfer refunds to the wallet by default. |
| 7 | **Images**: v2 said "resized with gd", which would put an image library call in domain logic (rule 4). Media already has `ImageProcessorInterface` bound to `WebPImageService`. | medium | Fixed inline: Bookshop resizes through the existing contract. |
| 8 | **Notices and invitations** are queued mail; production has no queue worker (OWNER_ACTIONS 3, BACKLOG C6). A vendor invited by email would wait forever. | medium | Fixed inline: copyable invitation link in the portal; B2's deploy gate includes the worker running. |
| 9 | **Dhivehi fonts**: MV Waheed and Faruma are not on Google Fonts, so v2's font list could not be served as written; MV Waheed's redistribution terms are unclear. | medium | Fixed inline: self-hosted under `public/fonts/` after a licence check; decision 14 added; every stack ends in system Thaana/Arabic fallbacks. |
| 10 | **The designer's drag-and-drop** was the riskiest UI in the plan and in the largest slice. A form-based designer with a real-renderer iframe preview delivers the same result and is keyboard-accessible. | medium | Fixed inline: forms first (B5), drag-to-order as polish (B7). |
| 11 | **B1 was three slices** (domain + portal + public shop) under one PR, against rule 1. | medium | Fixed inline: B1a vendors and portal; B1b public shop. Sizes re-estimated. |
| 12 | **Vendor scoping** was a design intention with nothing enforcing it. | medium | Fixed inline: an architecture test lists the vendor controllers and asserts every query goes through `VendorScope`, like the private-media reader test. |
| 13 | **Customer data**: v2 said nothing about how long a vendor sees a customer's phone and address, or whether a customer can remove an address. | medium | Fixed inline: vendor sees them on their own orders until the return window closes, then masked; address book deletable; decision 15 added. |
| 14 | **Legal pages** were listed for customers only; nothing bound a vendor to commission, payout timing or moderation. | medium | Fixed inline: seeded Vendor Agreement (accepted on first portal sign-in, dated on `vendor_members`), Shop Terms, Delivery & Returns Policy, by B2; decision 16 added. |
| 15 | **Sign-in at checkout**: the library and gift cards require sign-in, but a bookshop buyer arriving from Instagram will not create a password. The mobile OTP login exists (`routes` for OTP course registration). | low | Fixed inline: OTP sign-in offered at checkout; no anonymous guest checkout (orders need an identity). |
| 16 | **Order numbers** were unspecified; vendors and customers quote them on Viber and on slips. | low | Fixed inline: `AK-YYYY-NNNNNN` per checkout, `-XXX` vendor code per order; `vendors.code` added. |
| 17 | **Currency** was implicit. | low | Fixed inline: MVR only, decimal(10,2) like the wallet (the audit first wrote "integer laari"; corrected in B1a); USD parked in B9. |
| 18 | **Holiday mode** and **order minimums** were missing; both are the first things a one-person shop asks for. | low | Fixed inline: vendor settings (§5), B3 and B2 respectively. |
| 19 | **Mobile**: no phone-first affordance for a shop whose customers arrive from Instagram and Viber. | low | Fixed inline: bottom bar (Shop, Search, Cart, Orders) on phone widths, in B1b. |
| 20 | **Scheduler**: the plan introduced timed behaviour (expiry, maturing, holiday) without saying where it runs. `routes/console.php` already schedules `payments:reconcile` and `akuru:prune-expired`. | low | Fixed inline: commands on the existing schedule; nothing new on the host. |
| 21 | **Sitemap and SEO**: v2 asked for sitemap entries; `sitemap.xml` already exists as a route, so this is an extension, not a new feature. | info | Noted in B1b. |
| 22 | **Abandoned-cart reminders** and **bulk quotes for schools** came up while auditing and are real for an educational-items vendor. | info | Parked in B9 and BACKLOG C7. |

What the audit confirmed unchanged: one catalogue, one frame, path not
subdomain, structured (never CSS/HTML) storefront customisation, the
webhook rule, append-only ledgers, the domain layout and the
Commerce/Finance/Media/Notifications reuse list (every named class in §12
exists), `VerifiedGuardianLink` as the precedent for a single scope
definition, and the Fitrah kit.

The owner then decided every open row as recommended and asked for B0
(2026-09-25). Still to come from the vendor before B1a's production
setup: the remaining Fitrah details in `docs/vendors/FITRAH.md`.
