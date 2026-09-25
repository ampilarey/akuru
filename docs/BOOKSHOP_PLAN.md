# Akuru Online Bookshop — plan v2 (2026-09-25)

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
office with an owner account.

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
- Checkout (sign-in required, as the library and gift cards): address book (atoll, island, street, phone), delivery method **per vendor** (collect from the vendor / collect from Akuru / delivery in Malé–Hulhumalé–Villimalé / atolls by courier or boat), delivery fee per vendor, order notes, gift message, then payment: **card (BML)** or **wallet**, with a discount code where it applies. Gift cards are wallet money once redeemed (rule 12). Bank transfer with office confirmation and cash on delivery are decisions (§13).
- Order confirmation page and email receipt with GST line and the vendor's TIN; one **order number per vendor**, grouped under one checkout number.

**After the order**
- My orders: status trail (pending payment → paid → processing → ready to collect / dispatched → collected / delivered), tracking note, receipt, packing details, message the vendor (existing message threads), cancel before dispatch, request a return within the window, see the refund land in the wallet or on the card.
- Notices in app and by email (SMS where the office enables it): paid, ready, dispatched, delivered, refund done, back in stock (if they asked).

**Trust**
- Reviews only from customers who received the product; vendor may reply; office may hide.
- Every page trilingual-ready (EN/DV/AR) and RTL-safe; product names and descriptions in three languages where the vendor provides them.
- Policies linked at checkout: Reader/Customer Terms, Returns Policy, Delivery Policy, Privacy — seeded CMS pages like the library's, editable by the office.

---

## 5. Vendor portal features

**Products**
- Create/edit: title (EN/DV/AR), slug, short and long description (rich text, sanitised), category, brand, tags, photos (drag to order; the first is the card image), price, compare-at price, cost (private, for margin reports), GST-inclusive flag, SKU, barcode/ISBN, weight and dimensions, stock and low-stock threshold, track stock or not, "made to order" lead time, variants with their own SKU/price/stock/photo, status (draft / active / archived), visibility (shop-wide or storefront-only), optional link to a library item, book fields (author, publisher, year, pages, language) and educational fields (age range, grade, subject).
- Bulk: CSV import/export of products and stock; duplicate a product.
- Collections: named groups of their products (manual or by tag) for the storefront and for `/shop/<vendor>/<collection>`.

**Orders**
- Queue by status; open an order; mark processing / ready / dispatched (with carrier and tracking note) / delivered; print packing slip and delivery label; cancel with reason (before dispatch); accept or decline a return request; issue a refund (goes through the office's Finance refund; the vendor's earning reverses).
- Message the customer on the order (message threads).

**Storefront designer** — see §6.

**Settings**
- Shop identity: legal name, trading name, TIN, contact email and phone, address, opening hours.
- Delivery: which methods, zones and fees (start from the office's template), collection address, handling days, free delivery over an amount.
- Returns: window, conditions, who pays return delivery.
- Members: invite by email, roles.
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
- **Typography**: heading and body from an approved list (Latin: Inter, Merriweather, Poppins, Lora, Bree Serif, Courier Prime; Dhivehi: MV Waheed, Faruma; Arabic: Noto Naskh, Amiri), size scale (compact / regular / large).
- **Shape**: corner radius (square / soft / round), button style (filled / outlined), card style (flat / shadow / bordered), banner height, image ratio for product cards (square / portrait / landscape).
- **Dark mode** variant optional; the vendor sets both or lets the theme derive one.
- Themes have **named versions**: save as draft, preview, publish, roll back to a previous published version.

### 6.3 Layout: sections the vendor arranges
A storefront is an ordered list of sections, each with its own settings, dragged into place in the designer. Available sections (v1 set; the office can add types over time):

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
- A theme marketplace or per-vendor CSS: **not planned** — structured customisation keeps every vendor page safe, fast and accessible.

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
- GST: recorded per product (inclusive flag), shown as a line on the receipt with the vendor's TIN; **rate and registration are the owner's** (§13).
- Delivery fees go to the vendor who delivers; Akuru's commission applies to goods, not to delivery fees (decision §13 if the owner prefers otherwise).

---

## 9. Data (all additive; morph aliases registered in the same slice per ADR-005)

**Vendors**: `vendors` (name, slug fixed at creation, legal name, TIN, status, commission_rate, contact, address, hours, custom_host nullable, settings JSON), `vendor_members` (user, vendor, role), `vendor_bank_details`, `vendor_delivery_methods` (method, zone, fee, free_over, days), `vendor_return_policies`.

**Storefront**: `vendor_storefronts` (vendor, draft_theme JSON, published_theme JSON, draft_sections JSON, published_sections JSON, published_at, published_by), `vendor_storefront_versions` (append-only snapshots for roll-back), `vendor_pages` (vendor, slug, title ×3, sections JSON, status), `vendor_collections` (vendor, slug, name ×3, rule JSON or manual), `vendor_collection_products`.

**Catalogue**: `product_categories` (shared, trilingual), `brands`, `products` (vendor, category, brand, slug, titles ×3, descriptions ×3, price, compare_at, cost, gst_inclusive, sku, barcode, weight, dimensions, stock, low_stock_at, track_stock, lead_days, status, visibility, featured, library_item_id, attributes JSON for book/educational fields), `product_images` (media id, sort), `product_variants` (name, options JSON, sku, price, stock, image), `product_tags`, `product_reviews` (order_item, rating, text, status, vendor_reply), `wishlists`, `stock_movements` (append-only: in, sale, return, adjustment, by whom).

**Orders**: `customer_addresses`, `carts`, `cart_items`, `bookshop_checkouts` (user, totals, discount, payment_id, status), `orders` (number, checkout, vendor, user, status, delivery method and fee, address snapshot, subtotal, discount, gst, total, notes), `order_items` (product and variant snapshot: name, sku, unit price, qty, gst), `order_events` (append-only trail with actor), `order_returns` (order_item, reason, status, refund_id), `order_messages` via existing message threads.

**Money**: `vendor_earnings` (order, gross, discount, funding source, commission, net, status, available_at), `vendor_payouts`, `vendor_statements`.

No `academic_year_id`: commerce, not a term's record (every commerce table's precedent).

---

## 10. Architecture and quality

- **Domain**: `Domains/Bookshop` with Actions, Models, Http, Contracts, Enums, Listeners, Console. Cross-domain only through Commerce/Finance/Media/Notifications Actions and DTOs (rule 3). No SDKs in domain logic (rule 4); BML and mail stay behind their existing interfaces.
- **Storefront rendering**: sections are structured data → a Blade renderer per section type in the public zone (the library precedent for public Blade), with the theme applied as CSS custom properties on the storefront root; no vendor-supplied CSS or HTML; rich text through `HtmlSanitizer` with a vendor profile (no scripts, iframes only from the allowed video hosts, no inline styles).
- **Images**: public media, resized variants (card, gallery, zoom) at upload with `gd`; lazy loading; a size cap.
- **Performance**: catalogue queries with counts (`withCount`) and cursor pagination; storefront published JSON cached per vendor and cleared on publish; product listing cached per filter set for a minute.
- **Search**: database LIKE with a prefix index for v1; a search service (Meilisearch) is a later binding behind a contract if the catalogue grows.
- **Security**: every write validated into DTOs; vendors reach only their vendor's rows (a `VendorScope` resolved from membership, the way `VerifiedGuardianLink` is the one definition for families); office routes gated by `bookshop.manage`; rate limits on cart and checkout; stock and money changes in transactions with row locks; append-only trails.
- **Trilingual and RTL**: all labels in `lang/*/shop.php` with DV/AR first passes; every listing has CSV export (conventions).
- **Accessibility**: theme contrast check; keyboard-navigable designer; alt text required on product images.
- **Tests**: Pest feature tests per slice; architecture suite stays green (public routes and Blade baselines, morph map, thin controllers, discount callers — the gift-card rule test lists the checkout as a permitted discount caller because it buys goods).
- **Walks**: `scripts/smoke/shop.mjs` (customer: find, cart, checkout to the bank, orders) and `vendor.mjs` (vendor: product with photos, storefront designed and published, order fulfilled), seeded by `SmokeMarkerSeeder`.

---

## 11. Slices, in order (one PR each, walked in a browser)

| # | Slice | A person can, at the end |
|---|---|---|
| B0 | **Rename** the Knowledge Library to *Akuru Digital Library* in every label (EN/DV/AR); `/library` stays. | see the new name across the library |
| B1 | **Vendors and products** — domain scaffold, vendors and members, office vendor screen, vendor portal with products (photos, variants, stock, book and educational fields), shared categories and brands, public `/shop` home and listing, product page, a plain `/shop/<vendor>` page, Shop in the header and menu. | the office invites a vendor; the vendor lists a product; anyone finds it on the shop and the vendor's page |
| B2 | **Cart and checkout** — cart with guest merge, addresses, delivery methods and fees, discount codes, BML and wallet, one order per vendor under one checkout, webhook confirmation, stock decrement and needs-attention, receipt with GST, My orders, notices. | a customer buys two vendors' items in one basket and both orders show as paid |
| B3 | **Fulfilment and returns** — vendor order queue with status steps, packing slip and label, tracking note, customer cancellation before dispatch, vendor cancellation with reason, return requests and refunds (wallet or card), order messages. | a vendor dispatches; the customer tracks it and, if needed, returns it |
| B4 | **Storefront designer, part 1: identity and theme** — logo, banner, story, contact, hours, socials; colour presets and custom palette with contrast check; fonts; shape; draft / preview / publish / roll back. | a vendor's page looks like their brand, inside the Akuru frame |
| B5 | **Storefront designer, part 2: sections, pages, collections, navigation** — the section types of §6.3, drag-to-order, scheduling, vendor pages, collections, storefront menu, SEO fields. | a vendor arranges a hero, featured products, a story, FAQ and contact, adds an About page, and publishes |
| B6 | **Money to vendors** — commission, earnings maturing after the return window, payout requests and decisions, statements, vendor and office reports, GST report. | a vendor sees what they are owed and is paid |
| B7 | **Shop polish and trust** — search suggestions, best-selling and top-rated sorts, wishlist, recently viewed, reviews with vendor replies and office moderation, back-in-stock notices, product badges, vendor-funded discount codes scoped to their products, free-delivery-over rules, shop-home merchandising by the office. | the shop feels like a shop |
| B8 | **Bulk and operations** — CSV import/export of products and stock, stock movements log, low-stock alerts, order exports, email/SMS switches. | a vendor with 500 items can manage them |
| B9 | **Later, on request** — public vendor onboarding (apply → approve), custom host per vendor, whole-shop subdomain, bank transfer with office confirmation, cash on delivery, newsletter section, search service, analytics funnel. | parked in `BACKLOG.md` |

Rough size: B1–B3 are each about the gift-card slice times three; B4 and
B5 together are the largest piece (the designer); B6–B8 are each a
library-sized slice. Order can change after B1 if the owner wants the
designer before checkout to show vendors their pages early.

---

## 12. What this plan reuses

`InitiatePayablePaymentAction` and the `PaymentConfirmed` listener
pattern (Finance); refunds (Finance); `DebitWalletAction`,
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
| 4 | GST: rate, prices inclusive or not, TIN on receipts | inclusive prices; rate per current law; each vendor's TIN | open |
| 5 | Default commission on goods; commission on delivery fees? | 10–15% on goods; none on delivery | open |
| 6 | Delivery zones and fees vendors start from; Akuru's office as a collection point? | Malé–Hulhumalé–Villimalé flat; atolls by courier/boat at vendor's fee; yes to Akuru collection | open |
| 7 | Payment methods at launch | card and wallet; bank transfer and cash on delivery in B9 | open |
| 8 | Return window and returns policy | 7 days, unused, buyer pays return delivery unless faulty | open |
| 9 | Vendors in v1 | invitation only | **decided 2026-09-25: the first vendor is the owner's wife's educational-items shop, an independent business selling inside the bookshop; **Fitrah** (owner Fathimath Inaaya, `/shop/fitrah`) — identity, palette, logo and proposed theme in `docs/vendors/FITRAH.md`; contact, delivery, returns and first products still to come. Bank details and ID documents are entered by her in the vendor portal, never sent through the conversation or committed.** |
| 10 | Storefront fonts and presets the office allows; may vendors use Akuru's own maroon palette? | the §6.2 list; yes to presets, Akuru's palette marked "Akuru partner" | open |
| 11 | Does a vendor page show "at Akuru Online Bookshop" under their name? | yes, always | open |
| 12 | Should reviews be on from B7, or off until vendors ask? | on, moderated | open |
| 13 | Order of slices: checkout (B2–B3) before the designer (B4–B5), or designer first? | checkout first: money before polish | open |
