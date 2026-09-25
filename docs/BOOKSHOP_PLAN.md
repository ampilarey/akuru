# Akuru Online Bookshop — plan (v1, 2026-09-25)

The owner's brief (2026-09-25):

1. Name: **Akuru Online Bookshop**.
2. The Knowledge Library is renamed **Akuru Digital Library**.
3. The bookshop has the ordinary e-commerce features — cart, checkout, delivery, orders.
4. **Multi-vendor, but not public**: a few invited vendors (the first is an educational-items store) sell under Akuru.
5. Every vendor's items appear in the one Akuru Online Bookshop.
6. A vendor may have their own page — or a subdomain — and public vendors may come later.

This is the plan to build it, in the repo's discipline: one slice per PR,
tests and a browser walk with each, STATUS updated, no future-phase work
"while you're there" (CLAUDE.md rules 1–12). Nothing here is built yet.

## 1. The two shops, side by side

| | Akuru Digital Library (exists) | Akuru Online Bookshop (this plan) |
|---|---|---|
| Sells | access to a file: books, articles, research read online | physical goods: printed books, workbooks, stationery, educational kits |
| After paying | an access grant; the reader serves pages | an order the vendor fulfils; a parcel or a collection |
| Seller | writers (individuals) approved by the office | vendors (shops) invited by the office |
| Money to seller | writer earnings, 70/30 default, payouts | vendor earnings, commission per vendor, payouts |
| Domain in code | `Domains/Library` | **`Domains/Bookshop`** (new) |
| Shared with | Commerce (wallet, gift cards, discounts), Finance (BML), Media, Notifications | the same |

They stay separate domains (rule 3). A printed edition of a library book
links both ways ("also in print" / "read the e-book") through ids, not
imports.

## 2. The subdomain question — recommendation

**Bookshop: a path first, `akuru.edu.mv/shop`, with the code written so a
subdomain is a config change later.** Reasons:

- One app, one login. A session cookie is per host; a subdomain needs
  `SESSION_DOMAIN=.akuru.edu.mv` so a person signed in on the main site is
  signed in on the shop — a change to every cookie the site sets, done
  once and tested once, not something to take on before the shop exists.
- One deploy, one SSL. cPanel AutoSSL covers a subdomain you create, but it
  is another thing to create and watch.
- SEO: the main domain already carries the Institute's authority; the
  shop inherits it on a path and starts from nothing on a subdomain.
- Reversible: the bookshop's routes live in one group with a configurable
  prefix or host (`config/bookshop.php` → `host` null / `shop.akuru.edu.mv`).
  Switching later is DNS + one env line + `SESSION_DOMAIN`.

**Vendor pages: a path, `akuru.edu.mv/shop/<vendor-slug>`, as the canonical
page — with an optional custom host per vendor later.** The vendor page is
their shop front: logo, banner, description, contact, delivery terms, their
products, in their accent colour. That is what "my own shop page" means to
a customer. A per-vendor subdomain (`<vendor>.akuru.edu.mv`) or the
vendor's own domain is then a **mapping** (`vendors.custom_host`) that
serves the same page under their name — worth offering when a vendor asks
and pays for it, not built for the first two or three. When vendors are
opened to the public, path-based pages scale with no operations work per
vendor; the custom host stays the premium option.

**So: `/shop` and `/shop/<vendor>` now; `shop.akuru.edu.mv` and
`<vendor>.akuru.edu.mv` are later env and DNS, not later code.**

## 3. Who does what

| Role | Where | Can |
|---|---|---|
| Customer (any signed-in person; guests browse) | `/shop`, `/shop/<vendor>`, `/shop/cart`, `/shop/checkout`, `/my-orders` | browse, add to cart, check out, pay, see orders, cancel before dispatch |
| Vendor staff (a person a vendor lists; owner or staff) | `/vendor` (Inertia) | their products, stock, orders, delivery settings, shop page, earnings, payout requests |
| Office (`bookshop.manage`) | `/admin/bookshop` | vendors (invite, suspend, commission, custom host), every product and order, delivery zones template, settings, payouts, reports |
| Super admin | everything | |

Vendor is a **membership on the unified identity** (`vendor_members`:
user, vendor, role), the way writer is a role on a person — never a
separate login.

## 4. What a customer gets (v1)

- **Shop home**: featured products, categories, new arrivals, vendors, search.
- **Listing** with filters (category, vendor, price range, in stock, language of the book) and sorts (newest, price, best selling), CSV export for the office.
- **Product page**: photos, price (and compare-at price when on offer), variants where a product has them (size, colour, edition), stock ("3 left" / "out of stock" / "made to order"), delivery options and fees, the vendor's name linking to their page, "read the e-book" when a library item is linked.
- **Cart**: server-side, per person (guests get a session cart that merges on sign-in), quantities, stock checks, a discount code box.
- **Checkout** (sign-in required, like the library and gift cards): delivery method per vendor (collect from the vendor / Akuru, delivery in Malé–Hulhumalé–Villimalé at a flat fee, atolls by courier or boat at the vendor's fee), address book (island and atoll, street, phone), order notes, then pay by **card (BML)** or **wallet**, with a discount code where one applies. Gift cards are wallet money once redeemed (rule 12).
- **After paying**: one order **per vendor** in the basket, grouped under the one checkout; order numbers; an in-app notice and an email receipt; **My orders** with status and history.
- **Order statuses**: pending payment → paid → processing → ready to collect / dispatched (with a tracking note) → collected / delivered; cancelled (by the customer before dispatch, or by the vendor with a reason) → refunded (to wallet, or to the card through the existing Finance refund).

## 5. What a vendor gets (v1)

- **Products**: title in three languages (the categories pattern), description, category, photos (public media), price, compare-at price, GST-inclusive flag, SKU, stock and whether to track it, weight and size (for courier), variants, status (draft / active / archived), optional link to a library item.
- **Orders queue**: new, processing, ready, dispatched; mark each step; print a packing slip; message the customer through the existing message threads.
- **Delivery settings**: which methods they offer, fees per zone, collection address and hours.
- **Shop page**: name, slug (fixed at creation, like author pages), logo, banner, description, contact, accent colour, delivery terms.
- **Earnings and payouts**: sales less commission, available after the return window, payout requests to their bank details; the same maturing and request pattern as writers, on the vendor's own tables.

## 6. What the office gets (v1)

- **Vendors**: invite (create with an owner account), suspend, commission rate, custom host (later), members.
- **Everything**: every product (unpublish), every order (intervene, refund), the delivery zones template vendors start from, GST rate and whether prices include it, default commission, return window, the bookshop on/off switch.
- **Reports**: sales by vendor and period, orders by status, low stock, payouts due, stored-value liabilities already on `/admin/commerce`; all with CSV.
- **Notices**: new vendor order (optional), payout requested, order needs attention (stock ran out between checkout and payment).

## 7. Money rules, unchanged

- Access to anything paid depends on the **BML webhook**, never the return URL (rule 12). Card orders become *paid* on the webhook; wallet orders immediately.
- Wallet and ledgers are append-only; refunds are reversals.
- Discount codes reduce a price and never buy gift cards; each code says who funds it (Akuru or the vendor), and the vendor's share is computed the way writers' is (Akuru-funded: on full price; vendor-funded: on the discounted price).
- One BML payment per checkout (payable `bookshop_checkout`); the webhook marks every order under it. Stock is decremented on *paid*; a product that sold out between checkout and payment marks the order *needs attention* rather than overselling silently.
- GST: Maldives GST applies to goods; v1 records the rate and whether a price includes it, and the receipt shows the tax line and the vendor's TIN. **The owner confirms the rate and the registration.**

## 8. Tables (all additive; morph aliases registered in the same slice per ADR-005)

`vendors`, `vendor_members`, `vendor_delivery_methods` (method, zone, fee, days), `product_categories`, `products`, `product_images`, `product_variants`, `carts`, `cart_items`, `customer_addresses`, `bookshop_checkouts` (one per payment: user, totals, discount, payment_id), `orders` (per vendor: number, checkout_id, vendor_id, user_id, status, delivery method and address snapshot, fees, totals), `order_items` (product and variant snapshot: name, price, qty), `order_events` (append-only status trail), `vendor_earnings`, `vendor_payouts`, `vendor_bank_details`. No `academic_year_id`: commerce, not a term's record (the precedent of every commerce table).

## 9. Slices, in order (one PR each, walked in a browser)

| # | Slice | What a person can do at the end |
|---|---|---|
| B0 | **Rename** the Knowledge Library to *Akuru Digital Library* in every label (EN/DV/AR), keep `/library` as the address. Docs-plus-labels. | The header, shelf and pages say Akuru Digital Library. |
| B1 | **Vendors and products.** `Domains/Bookshop`; vendors and members; office vendor screen; vendor portal with products, photos, categories, stock; public `/shop` listing, product page, `/shop/<vendor>` page; "Shop" in the header and the signed-in menu. | The office invites a vendor; the vendor lists a product with photos; anyone finds it on the shop and on the vendor's page. |
| B2 | **Cart and checkout.** Cart, addresses, delivery methods and fees, discount codes, BML and wallet, one order per vendor, webhook confirmation, stock decrement, receipt, My orders, notices to buyer and vendor. | A customer buys two vendors' items in one basket, pays by card, and both orders show as paid. |
| B3 | **Fulfilment.** Vendor order queue with the status steps, packing slip, tracking note, customer cancellation before dispatch, vendor cancellation with reason, refunds. | A vendor moves an order to dispatched; the customer is told and sees it in My orders. |
| B4 | **Money to vendors.** Commission, earnings maturing after the return window, payout requests and office decisions, vendor and office reports with CSV, GST on receipts. | A vendor sees what they are owed and requests a payout; the office pays and records it. |
| B5 | **Storefront polish.** Featured, search, best-selling sort, e-book cross-links, wishlist, product reviews (optional), vendor accent colour and banner, custom-host mapping for a vendor page. | The shop looks like a shop, and a vendor's page can answer to their own host. |
| B6 | **Later**: public vendor onboarding (application → approval, like writers), vendor-funded promotions, subdomain for the whole shop, cash on delivery or bank transfer with office confirmation. | Parked in `BACKLOG.md` until the owner asks. |

Each slice ships with Pest tests (feature + architecture green), a walk in
`scripts/smoke/` (`shop.mjs`, `vendor.mjs`), STATUS.md entry, morph
aliases, baselines (public routes, Blade only if in the public zone —
the shop's public pages follow the library's Blade-in-public-zone
precedent; the vendor and office screens are Inertia).

## 10. Decisions the owner makes (not guessed)

1. **Path now, subdomain later** — as recommended in §2? (`/shop` vs `shop.akuru.edu.mv` from day one.)
2. **GST**: rate, whether listed prices include it, and the TIN to print on receipts.
3. **Default commission** for vendors (the library's writers default to 70/30 in the writer's favour; a shop is usually lower for Akuru, e.g. 10–15%).
4. **Delivery**: the zones and fees vendors start from (Malé–Hulhumalé–Villimalé flat; atolls by courier or boat), and whether Akuru's office is a collection point for every vendor.
5. **Payment methods at launch**: card and wallet (recommended); bank transfer with office confirmation, or cash on delivery, are B6.
6. **Return window** (days) before a vendor's earning matures, and the returns policy text.
7. **Who is a vendor in v1**: the named first vendor(s); invitation only.
8. **Names**: "Akuru Online Bookshop" for the whole shop; does a vendor's page carry "at Akuru Online Bookshop" under their own name?

## 11. What this plan reuses

`InitiatePayablePaymentAction` and the `PaymentConfirmed` listener pattern
(Finance); `DebitWalletAction`, `CreditWalletAction`, `ResolveDiscountAction`
and funding sources (Commerce); `StorePublicMediaAction` /
`ResolvePublicMediaUrlAction` (Media); `SendUserNotificationAction`
(Notifications); message threads (Notifications) for vendor–customer
questions; `NavigationMap` for the menus; `Csv` for exports; the
Blade-in-public-zone and Inertia-elsewhere split; the seeded smoke markers
and walks.
