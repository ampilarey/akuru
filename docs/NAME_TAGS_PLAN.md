# Name tags and print products in the Akuru Bookstore — plan v1 (2026-10-12)

> The owner's brief (2026-10-11): in the Bookstore, a customer designs
> their own name tags or picks a ready design or uploads one, chooses how
> many tags fit an A4 sheet, the paper, colour or black and white, the
> number of sheets, and a set of subject tags (bought with school name
> tags), adds it to the cart, pays, and collects or has it delivered.
>
> The owner's decisions (2026-10-12) are in **§13**. Everything else here
> is the default an implementer takes unless the owner says otherwise.
> **Nothing here is built yet.** This document is the whole brief for the
> agent that builds it; it should not need to ask.

This plan is written against the code as it stands on `main` at
`a526ad1` (STATUS §5rl). Every name below — action, table, route, phrase
book, test helper — was read from the tree, not guessed; where a slice
must verify something before relying on it, the plan says so.

---

## 1. Shape of the thing

A **print product** is something Akuru makes to order on paper from a
design the customer approved: today name tags and subject tags on A4;
the same machinery takes labels, cards and certificates later. It is sold
in the Bookstore like any product — same cart, checkout, payment
(BML, wallet, bank slip, cash on delivery), purchase notices, receipts,
*My orders*, collection or Akuru's courier — and made by Akuru's own
print desk.

What is new is the **configurator** (design → options → price → proof)
and the **print job** (the approved file, the production steps, the
reprint). What is not new is everything after *Add to cart*.

| Customer | Office (Akuru print desk) |
|---|---|
| Picks a template, fills the fields, or designs freely, or uploads a design | Keeps the templates, papers, finishing, layouts, subject sets, lead time, prices |
| Types one name, or pastes a class list | Reviews the queue, downloads the print-ready PDF, marks printed / finished |
| Chooses layout, paper, colour, finishing, sheets | Reprints a defective job without charging again |
| Approves a watermarked proof — never downloads it | Switches the service off when the printer cannot print |
| Pays, collects or is delivered to | Sees print lines inside the Akuru fulfilment queue it already has |

## 2. How it fits the Bookstore that exists

Read before coding; these are the seams the slices attach to.

- **Products.** `App\Domains\Bookshop\Models\Product` has `track_stock`,
  `lead_days`, `tax_class`, trilingual titles. A print product is one
  `Product` row per kind (*Name tags — A4*), `track_stock = false`, owned
  by the Akuru vendor (§4.1), category *Print*. The product page is the
  configurator's door; the configuration is not on the product.
- **Cart.** `SaveCartItemAction::add(Cart, string $productSlug, ?int
  $variantId, int $quantity)` writes `cart_items` (`cart_id, product_id,
  product_variant_id, quantity, quote_item_id, saved_at`).
  `PresentCartAction` prices a line as `variant?->price ?? product->price`
  through `SalePrice::apply`. `StartBookshopCheckoutAction` reserves stock
  only where `track_stock` is true and copies lines to `order_items`
  (`title, variant_name, sku, unit_price, quantity, line_total, tax_class,
  tax_amount, quote_item_id`). Print lines carry a `print_job_id` and are
  priced from the job (§5), with **quantity = sheets** so every existing
  quantity rule (change in cart, line total, GST) keeps working.
- **Orders.** `OrderStatus`: pending_payment, paid, cash_due,
  needs_attention, processing, ready, dispatched, delivered, cancelled,
  expired. `AkuruFulfilmentAction` already lists orders Akuru packs
  (`fulfilled_by = 'akuru'`), oldest first, with their next steps. Print
  orders join that queue; production states are the job's, not new order
  states (§6).
- **Delivery.** `DeliveryKind::CollectAkuru` and `AkuruCourier` exist;
  the Akuru courier fee and `collect_akuru` handling days are settings
  (`bookshop_akuru_*`, `config/bookshop.php` `akuru`). Print orders use
  only these two kinds.
- **Documents.** `DocumentRendererInterface::render(string $template,
  array $data): string` (HTML, Blade under `resources/views/documents/`),
  `PdfConverterInterface` (`enabled()`, `fromHtml()` via headless Chrome,
  only where `DOCUMENTS_CHROME_PATH` is set — ADR-012 amended), the
  `documents` table (`documentable_type/id, media_path, document_type,
  title, uploaded_by`) and `ReadGeneratedDocumentAction`. The proof and
  the print file are documents on the job.
- **Uploads.** `StorePrivateMediaAction::execute(UploadedFile, ?int
  $uploadedBy, array $allowedMimes, ?int $maxBytes, ?int $shrinkPhotosTo)`
  refuses by kind and size in the page's language (MD1). Customers'
  images and designs are private media.
- **Fonts.** `config/bookshop.php` `storefront.fonts` already decides
  Thaana: *Faruma* (shipped under `public/fonts`) and *Noto Sans Thaana*;
  Arabic *Amiri*, *Cairo*; Latin faces are listed. C15 slice 5 dropped
  every third-party font link, so a font the designer offers is
  self-hosted or not offered.
- **Office.** `/admin/bookshop/*` under `role:super_admin|bookshop_manager`
  and `can:bookshop.manage`; pages under `resources/js/Pages/Bookshop/`,
  phone cards and CSV on every list (C15 6d), the `shop` phrase book
  (`resources/lang/{en,dv,ar}/shop.php`) for Bookstore words.
- **Notices.** `NotifyBookshopUserAction` and the purchase notices (P5),
  receipts by SMS link (P8a).
- **Settings.** `GetSettingAction` / `SetSettingAction` for knobs the
  office moves without a deploy (lead time, minimum order, tiers,
  availability).

## 3. Customer features

### 3.1 The door

`/shop/print/name-tags` (and the product's own page, which links there).
Three ways in, as cards: **Start from a design**, **Design your own**,
**Upload your design**. A signed-out visitor may design; the proof and
the cart need an account (the cart already merges a guest cart at sign-in;
a job made as a guest attaches to the cart, then to the account).

### 3.2 Start from a design (templates)

- Templates are made by Akuru (the first set ships with the slice) and
  by the office in the same designer (§7.2). Each has a title in three
  languages, a preview image, a default layout, and a design (§5.1) whose
  elements are **locked** except the fields.
- The customer sees the gallery (filter: name tags, subject tags, both),
  picks one, and fills its fields: name, class, school, year, and for
  subject tags the subject set (§3.5). Colours: a template may expose a
  palette of alternative colour schemes; fonts: the field texts may be
  switched among the approved fonts (§5.4). Nothing else moves in this
  mode.
- The preview updates live in the browser from the same design the
  server will print (§5.2).

### 3.3 Design your own (free-form)

The full designer (§7.1): text, images, shapes, background, move, resize,
rotate, layers, align, snap, undo. Field placeholders (`{name}`, `{class}`,
`{subject}`) let a free design still take a list. A customer can start
free from blank or from a template ("Open in the designer" unlocks a copy).

### 3.4 Upload your design

PNG or JPEG (PDF where the host can rasterise it, §5.6): one tag's
artwork, repeated per the layout, or a whole A4 sheet already laid out.
The upload is **preflighted** before the cart: pixel size against the
printed size (warn under 300 dpi, refuse under 150), page size for a
sheet, colour content for a black-and-white order, transparency. Warnings
are shown and must be acknowledged; refusals say why, in the page's
language (`print.upload_*` phrases through the Media refusals).

### 3.5 One name or a list

- **Same tag × N**: one child's details on every tag of the sheet.
- **A list**: paste or type rows (name, class — one per line, or
  tab/comma separated; a CSV file is accepted too), one tag each, as many
  sheets as the list needs. The designer shows how many sheets that is
  and the price before anything is added.
- **Subject sets**: the office keeps a set per grade (*Grade 1: Dhivehi,
  English, Maths, Islam, Qur'an, EVS, …*, each name in three languages).
  The customer ticks a grade and gets every subject in the set with the
  child's name on each; subjects may be unticked or added.
- The list is data on the job; the design is one; the renderer repeats it.

### 3.6 Options and price

- **Layout**: tags per A4 from the office's layouts (§4.5) — the usual
  label-sheet grids (2×4 = 8, 2×5 = 10, 3×4 = 12, 3×7 = 21, 3×8 = 24) and
  any the office adds. The layout fixes each tag's size; the designer's
  canvas is that size.
- **Paper**: from the office's paper options, each with a price per
  sheet in black and white and in colour (§4.2).
- **Colour** or **black and white**.
- **Finishing**: lamination, cut into tags, rounded corners, hole punch —
  each a price per sheet, any combination, from the office's list (§4.3).
- **Sheets**: typed, or fixed by the list; quantity breaks from the
  office's tiers; a minimum order.
- The price is shown as a breakdown — paper, colour, each finishing,
  sheets, tier discount, GST — and recomputed on the server at every
  step; the browser never prices.

### 3.7 The proof

- *Proof* renders the job through the one renderer to HTML, then to PDF
  (the print file) and to a **watermarked raster** per sheet (the proof the
  customer sees). Both come from the same HTML, so what is approved is
  what is printed.
- The customer must approve the proof before *Add to cart* (decision 6).
  The proof page shows each sheet as an image with *PROOF — Akuru
  Bookstore* across it at screen resolution; **no download**, no route a
  customer can reach returns the PDF, and the image route is signed and
  short-lived. The print-ready PDF is served only under `bookshop.manage`.
- Approving **locks** the job: design, list, options and price are frozen,
  the proof document is stored, and only a new job (a copy) can change
  anything — the same lock PB1 gave a sent broadcast.

### 3.8 Cart, checkout, delivery

- *Add to cart* adds one line: the print product, `quantity = sheets`,
  `unit_price = price per sheet` from the job, `print_job_id` on the line.
  Changing the quantity in the cart changes the job's sheets and reprices
  it (and un-approves it: a different sheet count is a different proof —
  the cart says so and offers *Re-approve*). A line whose job is not
  approved cannot check out.
- Checkout as today. Only *Collect from Akuru Institute* and *Akuru
  courier* are offered for a cart with a print line; the handling days
  come from the print desk's lead time (§4.6), shown as *ready in N
  working days*.
- Payment as today (BML webhook, wallet, bank slip, cash on delivery);
  rule 12 untouched. Nothing is printed before the order is `paid` (or
  `cash_due` where the office allows cash for print — default **no**).

### 3.9 After the order

- *My orders* shows the job's production steps (§6) and the thumbnail of
  the approved proof (watermarked, as before).
- **My designs** (`/shop/account/designs`): every job the customer made,
  approved or not, with *Order again* (a copy, re-proofed at today's
  price) and *Open in the designer*. A design can be renamed and deleted
  (soft) while no order holds it.
- **Book list upsell**: on a school book list (`VendorCollection`, B9 /
  §5ld) and on its *Add all to cart* result, a card: *Add name tags and
  subject tags for this child* → the configurator with the grade's
  subject set preselected.

### 3.10 Language

Every screen reads the new `print` phrase book in EN/DV/AR, right to left
under `/dv` and `/ar`; every refusal is in the page's language; a tag's
text is set in the direction of its own script (a Thaana name on an
English template is right to left inside its box). The subject set's
names are stored in three languages and printed in the language the
customer chose for the tags, not the page's.

## 4. Office features (`/admin/bookshop/print`)

One Inertia page group under the Bookstore office, gated by
`bookshop.manage`, each list with phone cards and CSV. A *Print* tile on
the Bookstore office home (`Bookshop/Admin.jsx`) and a row in
`NavigationMap`.

### 4.1 The Akuru vendor

Print products belong to a vendor of **kind `akuru`**: the Institute
itself. Made on first use by `PrintDesk::ensure()` (slug `akuru`, code `AKU`, `fulfilment =
'akuru'`, `delivery_by = 'akuru'`, `status = active`), not deletable,
never paid out — `RecordVendorEarningAction` (B6, `Actions/Money`) records
no earning for kind `akuru`, so the whole price
is Akuru's and the commission report shows it as *Akuru's own sales*.
The vendor page `/shop/akuru` exists like any shop's (the storefront
designer applies); its *Akuru partner* badge is implicit.

### 4.2 Paper options

Name (3 languages), weight (gsm), kind (plain, card, glossy, sticker,
other), price per sheet black and white, price per sheet colour, active,
sort order. A paper in use by an approved job may be retired, never
deleted (the job keeps a snapshot of what it was priced on).

### 4.3 Finishing options

Name (3 languages), price per sheet, active, sort order, and a flag *cuts
the sheet* (a cut job is handed over as loose tags; the queue says so).

### 4.4 Subject sets

Grade label (3 languages), ordered subjects each in three languages,
active. Kept in the Bookshop domain as the office's own list — **not** a
link to Academics' subjects (rule 3; a school's own subject list is a
different thing from what a shop sells).

### 4.5 Layouts

Name, columns, rows, tag width and height (mm), page margins, gutters,
optional label-sheet code (*A4 2×4*), active. Seeded with the five
standard grids; the office may add. A layout in use is retired, not
deleted.

### 4.6 Settings (`SetSettingAction`, keys `bookshop_print_*`)

Service on/off (`enabled`), lead time in working days, minimum sheets per
order, quantity tiers (JSON: `[{sheets: 10, percent: 5}, {sheets: 50,
percent: 10}]`), cash on delivery allowed for print (default off),
proof-image watermark text, maximum list rows (default 500), maximum
upload size. The *on* switch is refused while `PdfConverterInterface::
enabled()` is false, with the reason (§9 R1).

### 4.7 Templates

List (title, kind, layout, status, uses), *New* opens the designer (§7)
in template mode: full free-form editing, marking which elements are
fields, the palette of colour schemes, the preview image (rendered from
the design, not uploaded). States: draft, active, retired. A template in
use by jobs is retired, never deleted; jobs keep their own copy of the
design, so a template edit never changes an approved job.

### 4.8 The print queue

Paid orders with print lines, oldest first, with: the customer, the
job(s), sheets, paper, colour, finishing, lead-time due date, state, and
the buttons *Print file* (the PDF, `bookshop.manage` only), *Printed*,
*Finished*, *Reprint*, *Problem*. A job's steps write `order_events` so
the order's own tracking shows them, and the order moves to `ready` (or
`dispatched` through the existing delivery flow) when every job on it is
finished. *Reprint* adds a `print_job_reprints` row (reason, by whom)
and puts the job back to `printing` — no new charge. The queue is also
visible inside `/admin/bookshop/akuru` as part of Akuru's fulfilment.

## 5. The design, the render, the file

### 5.1 The design model

One JSON shape for templates, free designs and uploads, versioned
(`"v": 1`):

```json
{
  "v": 1,
  "tag": {"w": 70, "h": 37},
  "background": {"color": "#FFFFFF", "image": null},
  "elements": [
    {"id": "e1", "type": "text", "x": 5, "y": 6, "w": 60, "h": 12,
     "text": "{name}", "font": "noto-sans-thaana", "size": 14,
     "weight": 700, "color": "#7A1F2B", "align": "center",
     "dir": "auto", "rotate": 0, "locked": false, "field": "name"},
    {"id": "e2", "type": "image", "x": 2, "y": 2, "w": 10, "h": 10,
     "media": "private/print/…/logo.png", "fit": "contain", "locked": true},
    {"id": "e3", "type": "rect", "x": 0, "y": 30, "w": 70, "h": 7,
     "fill": "#E9D8B4", "stroke": null, "radius": 2, "locked": true}
  ],
  "palette": {"scheme": "maroon", "schemes": {"maroon": {"primary": "#7A1F2B", "secondary": "#E9D8B4"}}},
  "fields": [{"key": "name", "label": {"en": "Name", "dv": "ނަން", "ar": "الاسم"}, "required": true}]
}
```

Units are **millimetres** on the tag; the layout places tags on the page.
`dir: "auto"` sets a text's direction from its first strong character
(Thaana and Arabic right to left). Element types: `text`, `image`,
`rect`, `ellipse`, `line`. A template's `locked` elements cannot be
changed in field mode; the designer unlocks everything.

### 5.2 One render, three outputs

`RenderPrintSheetAction` turns (design, layout, list, options) into HTML
through `DocumentRendererInterface::render('print-sheet', $data)` — a
Blade template `resources/views/documents/print-sheet.blade.php`: an A4
page per sheet, `@page { size: A4; margin: 0 }`, each tag absolutely
positioned at its layout cell, each element absolutely positioned inside
the tag, fonts by `@font-face` (§5.4), images as `data:` URIs (private
media read by the action, never a URL Chrome would have to fetch), crop
marks in the gutters where the layout is not a pre-cut sheet.

From that HTML:
- the browser preview — the React designer draws the same model with the
  same fonts, so it matches; the HTML is never shown raw to customers;
- the **print file** — `PdfConverterInterface::fromHtml()` (Chrome);
- the **proof images** — `PageImageInterface::fromHtml(string $html, int
  $page, int $widthPx): string` (PNG), a new Support contract beside the
  PDF one, implemented by `ChromePageImage` with `--screenshot` on the
  same HTML plus a watermark layer the action adds to the HTML before
  shooting (so the watermark is in the pixels, not an overlay a browser
  can peel off). Same `DOCUMENTS_CHROME_PATH`, same tmp dir discipline.

Both files are stored as private documents on the job (`document_type`
`print_proof` and `print_file`, new `DocumentType` cases), rendered
**once** at approval. Nothing re-renders after approval; a reprint prints
the stored file.

### 5.3 Vector where it can be, raster where it must

Text and shapes stay vector in the PDF (Chrome prints them as such);
images are embedded at their uploaded resolution. The proof PNG is 96 dpi
on purpose — enough to check, useless to print.

### 5.4 Fonts

Self-hosted only (C15 slice 5). Each font the designer offers is a
`@font-face` the browser loads from `/fonts/…` and Chrome loads from a
`file://` path to the same file in `public/fonts` (the print HTML is
written to disk and opened as a file, so relative URLs do not resolve —
**NT0 verifies this**). The set, each with its licence file beside it:

| Script | Faces | Licence |
|---|---|---|
| Thaana | Faruma (shipped), Noto Sans Thaana | Faruma as shipped (decision 14 of the Bookstore plan); Noto: SIL OFL 1.1 |
| Latin | Figtree (shipped), Poppins, Nunito, Fredoka, Baloo 2, Lora, Merriweather, Pacifico | all SIL OFL 1.1, added through `@fontsource/*` like the three already in `package.json` |
| Arabic | Amiri, Cairo (shipped) | SIL OFL 1.1 |

*MV Boli* is Microsoft's and is **not** offered. *Faruma* has no digits, so a tag's numbers are set in Noto Sans Thaana or the design's Latin face (NT0 finding). Fonts are declared once
in `config/print.php` (`fonts.<id> => [family, file, scripts]`) and read
by the designer, the renderer and the test that checks every file and
licence is present.

### 5.5 Preflight for uploads

Images: dimensions from GD (`getimagesize`, no SDK), dpi at the target
size = pixels ÷ (mm ÷ 25.4); warn < 300, refuse < 150; a sheet upload is
checked against A4 proportion ±2%; a colour image on a black-and-white
job warns. Results are stored on the job (`preflight` JSON) and shown on
the proof page.

### 5.6 PDF uploads

Chrome cannot screenshot a PDF, so a PDF proof needs a rasteriser.
`PdfRasterizerInterface::toPng(string $pdf, int $page): string` with a
Ghostscript implementation enabled only where `DOCUMENTS_GS_PATH` points
at `gs`; otherwise the upload form accepts PNG and JPEG only and says so.
For a PDF the print file is the upload itself (after a `%PDF` check and a
page count that must equal the sheets ordered); the proof is its raster.
This is NT5's last step and is honest about the host.

## 6. States

### 6.1 `print_jobs.status`

`draft` → `proofed` (files rendered) → `approved` (locked) → `ordered`
(in a paid order) → `printing` → `finished` → (order goes `ready`) ;
`cancelled` from any state before `printing` (order cancellation cancels
its jobs). `approved` ← `proofed` ← `draft` can go back only by **copy**:
changing an approved job is refused (`print.error_locked`), *Change* makes
a new draft from it.

Timestamps: `proofed_at, approved_at, ordered_at, printing_at,
finished_at, cancelled_at`; `printed_by`, `finished_by`.

### 6.2 The order

No new `OrderStatus`. A paid order with print jobs sits in `paid` →
`processing` (first job marked printing) → `ready` (all finished) →
collection or `dispatched` → `delivered`, written by the existing order
step actions; the queue's buttons call them. `order_events` rows record
each job step in the customer's language-neutral form (type + data, the
page names it).

## 7. The designer

### 7.1 The free-form designer (React)

`resources/js/Pages/Shop/Print/Designer.jsx` with components under
`resources/js/Components/Print/`: a canvas (SVG, the tag at zoom), a
toolbar (text, image, rect, ellipse, line, background), a properties
panel (position, size, rotation, font, size, weight, colour, align,
direction, radius, stroke, layer, lock), a layers list, undo/redo (a
history of design snapshots, capped), snapping to the tag's edges, centre
and other elements, keyboard nudges, and a *Fields* step that marks a
text as `{name}`, `{class}`, `{school}`, `{subject}`, `{year}` or a custom
field. Built on React 19 and pointer events — **no new drawing library**
(the storefront designer and drag-to-order in B5/B7 set the pattern; the
model is small enough). Phone: the canvas fits the width, the panels
stack below, targets are 44 px (C15 6a).

The same component in **field mode** hides the toolbar and layers and
shows only the fields, the palette and the font switch (§3.2).
Template mode (office) is the full designer plus field marking, palette
editing and *Save as template*.

### 7.2 Autosave and drafts

A job draft is saved to the server on every change (debounced), so a
closed tab loses nothing and a guest's draft follows the cart. Drafts
older than 30 days with no order are pruned by the existing abandoned-
cart command's sibling, `print:prune-drafts`.

## 8. Data (all additive; morph aliases in the same slice, ADR-005)

| Table | Purpose | Notes |
|---|---|---|
| `print_layouts` | tags per A4 | `name, columns, rows, tag_w_mm, tag_h_mm, margin_top/left_mm, gutter_x/y_mm, sheet_code, active, sort_order` |
| `print_paper_options` | papers and their two prices | `name, name_dv, name_ar, gsm, kind, price_bw, price_colour, active, sort_order` |
| `print_finishing_options` | finishing and price per sheet | `name ×3, price, cuts_sheet, active, sort_order` |
| `print_subject_sets` | a grade's subjects | `label ×3, subjects (json: [{en,dv,ar}]), active, sort_order` |
| `print_templates` | designs the office offers | `title ×3, kind (name_tag, subject_tag, both), print_layout_id, design (json), status (draft, active, retired), created_by, uses_count, sort_order` (no preview image: drawn from the design, NT2) |
| `print_images` | Akuru's artwork for templates | `media_file_id (public media), alt, sort_order, uploaded_by` (NT2) |
| `print_jobs` | a customer's configured job | `user_id (nullable until the cart has an owner), cart_id (nullable), print_product_id → products, print_template_id (nullable), print_layout_id, print_paper_option_id, colour (bool), finishing (json ids + snapshot of names and prices), sheets, mode (same, list), list (json rows), subject_set_id (nullable), design (json), source (template, freeform, upload), upload_media_path, preflight (json), price_per_sheet, price_breakdown (json), proof_document_id, print_document_id, status, the timestamps in §6.1, approved_price_snapshot (json), academic_year_id (rule 10, nullable, the current year at creation), name (the customer's label for *My designs*), deleted_at` |
| `print_job_reprints` | append-only | `print_job_id, reason, by_user_id, created_at` |
| `cart_items.print_job_id` | the line's job | nullable FK, additive |
| `order_items.print_job_id` | the sold line's job | nullable FK, additive |
| `vendors.kind` | new value `akuru` | the column exists (U1); the enum/validation gains the value |
| `documents.document_type` | new cases | `print_proof`, `print_file` |

Money: `decimal(10,2)` MVR like the rest of the Bookshop. Morph aliases:
`print_layout, print_paper_option, print_finishing_option,
print_subject_set, print_template, print_job, print_job_reprint`.
`print_jobs.status` goes into `StatusValuesAreWritableTest`'s baseline;
no raw reads of soft-deleted rows outside the baseline.

Settings keys (`settings` table): `bookshop_print_enabled,
bookshop_print_lead_days, bookshop_print_min_sheets,
bookshop_print_tiers, bookshop_print_cash_allowed,
bookshop_print_watermark, bookshop_print_max_rows,
bookshop_print_max_upload_kb`.

## 9. Risks, and what each slice does about them

- **R1 — the production host may not run Chrome.** The PDF path needs an
  executable Chrome or Chromium at `DOCUMENTS_CHROME_PATH`; shared cPanel
  hosts often have none. Mitigations, in order: NT0 proves the render in
  this container with Playwright's Chromium; the service switch (§4.6)
  stays off until the host can print; if the host cannot, the owner's
  options are a Chromium build in the account's home directory (no root
  needed), or a small render worker elsewhere that the app calls through
  the same interface. The plan does not assume which; it refuses to turn
  the service on without one.
- **R2 — Thaana in the PDF.** Chrome shapes Thaana correctly where the
  font has the glyphs; the risk is the font not loading from `file://`.
  NT0 renders a Thaana name with fili through the real converter and
  checks the PDF's text layer and a screenshot.
- **R3 — colour fidelity.** Screen RGB is not printer CMYK; the proof
  page says colours print close, not identical. No ICC work.
- **R4 — copyright.** Templates ship with Akuru's own artwork and OFL
  fonts only; the upload step shows a one-line declaration (*I have the
  right to print this*) recorded on the job like the Library's writer
  declarations.
- **R5 — the free-form designer's size.** It is the biggest front-end
  piece in the Bookstore; it is its own slice (NT6) after the field mode
  has shipped, and its lazy chunk (C15 slice 1) keeps it off every other
  page.
- **R6 — a job changed after approval.** The lock (§6.1) and
  `ChangePrintJobAction` refusing with `print.error_locked`; tests prove
  Save, re-proof and quantity change on an approved job either copy or
  refuse.
- **R7 — money.** Prices are recomputed server-side at approval and
  snapshotted; the cart line copies the snapshot; checkout re-reads the
  snapshot, never the live price list, so an office price change after
  approval does not reprice a cart (the cart shows *price held until
  <approved_at + 7 days>*; after that the line asks for re-approval).

## 10. Architecture and quality (CLAUDE.md rules)

- Everything lives in `App\Domains\Bookshop` (`Models`, `Actions\Print\*`,
  `Enums\PrintJobStatus`, `Http\Controllers\ShopPrintController`,
  `AdminPrintController`, `Jobs\RenderPrintJobJob`), with the two new
  Support contracts (`PageImageInterface`, `PdfRasterizerInterface`) and
  their Chrome / Ghostscript services beside `ChromePdfConverter`,
  bound in `AppServiceProvider` (rule 4: no process calls inside the
  domain; rule 11: the one renderer).
- No import of Academics, People or Courses models (rule 3); the book
  list upsell reads Bookshop's own `VendorCollection`.
- Controllers: authorize → validate → DTO → Action → response (rule 5).
- Every new status column's values are writable (`StatusValuesAreWritableTest`);
  every new polymorphic column is in `config/morph-map.php` (ADR-005).
- Pest for every slice; `tests/Architecture` green; a source test that
  no refusal in `Actions/Print` is English (`refusalEnglishIn`), every
  `print.*` phrase in three languages (`PhraseBooksTranslateEachPhraseTest`
  will hold them), no page left in English (`NoPageIsLeftInEnglishTest`).
- Tests **fake** the three Support contracts (`FakePdfConverter` returns
  `%PDF-fake…`, `FakePageImage` a 1×1 PNG, `FakePdfRasterizer` likewise)
  so CI, which has no Chrome, stays green; one test per slice runs the
  real Chrome when `DOCUMENTS_CHROME_PATH` is set (local only, skipped
  otherwise) — that is NT0's test, kept.
- Walks under `scripts/smoke/` in the runner (`all.mjs`), local only
  where they plant data, run with `DOCUMENTS_CHROME_PATH=/opt/pw-browsers/
  chromium-*/chrome-linux/chrome` so the proof really renders.
- Money rule 12 untouched: nothing prints before the webhook confirms.
- An ADR for: the Akuru vendor kind and its money; the proof/print file
  pair and the "never downloads" rule; the two new Support contracts.

## 11. Slices, in order (one PR each, walked in a browser)

Each slice: code + tests + arch green + walk + STATUS entry + BACKLOG row
touched + no files outside its scope. Names are the ones to use in
commits, STATUS and the walks.

### NT0 — The print path works on this host (half a day)

> **Built 2026-10-12 (STATUS §5rn).** Chrome loads the fonts by `file://` path and embeds them by name; Thaana with fili, Arabic and Latin all render. Found: Faruma has no digits (set digits in Noto Sans Thaana or a Latin face — NT2); the proof watermark fits at 48 px. The production host still needs a Chrome (§14).

- `PageImageInterface` + `ChromePageImage`; `config/print.php` with the
  font table; the OFL fonts added through `@fontsource` and copied to
  `public/fonts` by the build like Faruma; `print-sheet.blade.php` with a
  hard-coded two-tag sample (a Thaana name with fili, an Arabic word, a
  Latin name, a logo as `data:` URI).
- A test, local-only, that renders through the real Chrome: the PDF
  begins `%PDF`, has one page, its text layer contains the Thaana string;
  the PNG is A4-proportioned and non-blank. A CI test with the fakes.
- Walk: none (no screen). STATUS §: what rendered, the fonts, the
  `file://` finding, and the production host question stated for the
  owner.
- **Stop here if Thaana does not render**; the plan's §5 changes, not the
  slices after it.

### NT1 — Papers, finishing, layouts, subject sets, settings, the Akuru vendor (office)

> **Built 2026-10-12 (STATUS §5ro).** One change from the plan: the Akuru vendor, the Print category and the draft product are made by `PrintDesk::ensure()` the first time the office opens the desk, not by the migration — a seeded shop sat in every Bookstore list. The `print` phrase book arrived here.

- Migrations for the five office tables + settings keys; the `akuru`
  vendor kind and its row; the *Print* product category and the first
  product *Name tags — A4* (slug `name-tags-a4`), `track_stock = false`.
- Office pages: `Bookshop/Print/Settings.jsx` (switch, lead time,
  minimum, tiers, cash, watermark), `Papers.jsx`, `Finishing.jsx`,
  `Layouts.jsx`, `SubjectSets.jsx` — each a list with inline form, phone
  cards, CSV. The switch refuses while the converter is disabled.
- Money: earnings skip kind `akuru`; the vendor money report names it.
- Tests: CRUD and refusals in three languages; retire-not-delete; the
  switch refusal; no earning for Akuru's sale. Walk `print-office.mjs`:
  the Bookstore manager adds a paper and a subject set under `/dv`, tries
  the switch with the converter off and is refused in Dhivehi.

### NT2 — Templates and the designer in template mode (office)

- `print_templates`; the design model (§5.1) with a PHP validator
  (`PrintDesign::validate`) and the renderer action
  `RenderPrintSheetAction` for real designs (replacing NT0's sample);
  preview image rendered on save.
- The designer component (§7.1) complete enough for the office: text,
  image (private media), rect/ellipse/line, background, move/resize/
  rotate, layers, lock, fields, palette, undo. Phone layout.
- Five shipped templates (Akuru artwork: plain, maroon band, school
  crest placeholder, playful, subject strip) seeded as `active`.
- Tests: validator refusals; render of each shipped template through the
  fakes produces one tag per cell with the fields substituted; a locked
  element is not changed by field input. Walk `print-templates.mjs`: the
  office makes a template with a Thaana text, saves, sees its preview,
  retires it.

> **Built 2026-10-12 (STATUS §5rp).** As planned, with two corrections:
> **no preview image is stored** — the list and the designer draw a
> template from its design in the browser (`Components/Print/TagCanvas`),
> and the office opens a sample sheet from the server; the
> `preview_media_path` column in §8 is dropped. Pictures for templates
> are **public** media in a small library (`print_images`, at most 120),
> not private: they are Akuru's artwork, placed on designs customers see.
> Media is read through a new `ReadPublicMediaAction` (rule 3). The
> designer writes a field's token into its text when the field is
> chosen, so a tag prints the value and never the label.

### NT3 — The customer configures from a template and approves a proof

- `/shop/print/name-tags` door; gallery; field mode; same-tag × N and the
  list (paste, CSV, max rows); subject set; options and the live
  server-side price (`QuotePrintJobAction`); preflight of the list (empty
  rows, too long for the box → warning).
- Proof: `RenderPrintJobAction` → documents; the proof page with
  watermarked images by signed, short-lived URLs; *Approve* locks; *My
  designs*.
- Tests: pricing table-driven (paper × colour × finishing × tiers × min);
  a list of 25 names makes 4 sheets on a 2×4 layout; the proof images'
  route refuses without the signature and never serves the PDF to a
  customer; approve locks and any change refuses `print.error_locked`;
  autosave; a guest's draft follows the cart to the account. Walk
  `print-design.mjs`: under `/dv`, pick a template, type a Thaana name,
  pick glossy colour 2 sheets, see the price in Dhivehi, approve the proof,
  confirm no download button and that fetching the PDF route as the
  customer is 403.

> **Built 2026-10-12 (STATUS §5rq).** As planned, with these choices and
> finds: the customer's pages are Inertia in the shell (rule: no new
> Blade), open to guests, whose job rides on their basket and follows it
> to the account; the entry is stored as typed (`entry`) and expanded only
> by the renderer; a layout of another tag size scales the design
> (`PrintDesign::fit`); proof pictures are capped at 30 sheets. Two
> defects found by looking at a real proof: Chrome's new headless mode
> drew the page about 90 px short (fixed by a taller window and a GD crop
> in `ChromePageImage`), and a Latin name on a Thaana face fell to a serif
> (fixed by a per-script font stack, `PrintFontFaces::stack`). The cart
> refuses the print product added bare.

### NT4 — Cart, checkout, the queue, production and notices

- `cart_items.print_job_id`, `order_items.print_job_id`; the cart and
  checkout changes (§3.8), delivery kinds limited, lead time as handling
  days, price snapshot held 7 days, re-approval on quantity change.
- The queue (§4.8) in `Bookshop/Print/Queue.jsx` and inside `Akuru.jsx`;
  *Print file* download for `bookshop.manage`; Printed / Finished /
  Reprint / Problem; `order_events`; order to `ready`; SMS *ready to
  collect* through the existing notices; *My orders* shows the steps.
- Tests: checkout refuses an unapproved job; only the two delivery kinds;
  the order's line copies the snapshot; a webhook-confirmed payment puts
  the job in the queue and an unconfirmed one does not (rule 12); the
  steps move the order; reprint charges nothing; the print file is 403
  to the customer and 200 to the manager. Walk `print-order.mjs`: the
  customer adds the approved job, checks out with the fake payment path
  the Bookstore walks already use, the manager prints and finishes it,
  the customer's order reads *ready* in Arabic.

> **Built 2026-10-12 (STATUS §5rr).** As planned, with these choices: the
> basket's sheets are locked rather than repriced and un-approved (an
> approved job is locked everywhere; a change is a copy, approved anew); a
> print line cannot be saved for later; the line's total is the approved
> total exactly; the queue is its own screen `Bookshop/PrintQueue.jsx`
> under the print desk; the queue reads the customer from the order's
> address snapshot (rule 3); *ready*, *dispatched* and *delivered* are
> refused while a job on the order is unfinished.

### NT5 — Upload your design, with preflight

- PNG/JPEG upload (private), tag or sheet, preflight (§5.5), the
  declaration (R4), proof as in NT3. PDF through `PdfRasterizerInterface`
  + Ghostscript where present; otherwise the form says PNG/JPEG only.
- Tests: dpi warnings and refusals; A4 proportion; the declaration
  required; PDF refused without the rasteriser and accepted with the fake.
  Walk `print-upload.mjs`: a 72-dpi image is warned in Dhivehi, a 300-dpi
  one proofs.

> **Built 2026-10-12 (STATUS §5rs).** As planned, with these choices: an
> upload needs an account (the file is kept privately on it); a sheet
> upload off A4's shape is refused, not warned; the declaration and the
> warnings accepted are recorded when the proof is made and asked again on
> a copy; a PDF is always whole sheets, its pages setting the sheets. The
> Ghostscript rasteriser is tested here only through a fake — this
> container has no `gs`.

### NT6 — The free-form designer for customers, and *Open in the designer*

- Field mode's component gains the full toolbar for customers; start from
  blank or unlock a template copy; snapping, keyboard nudges, undo depth;
  phone polish; *My designs* edits.
- Tests: a free design with `{name}` still takes a list; locked elements
  from a template copy are unlocked in the copy and untouched in the
  template; the lazy chunk is separate. Walk `print-freeform.mjs`: the
  customer draws a text and a rectangle under `/ar`, rotates, saves,
  proofs.

### NT7 — The book list upsell, *Order again*, insights

- The card on a school book list and after *Add all*; *Order again*
  (copy + re-proof at today's price); the office insight *print sales by
  paper and month* on the Bookstore insights page; CSV.
- Tests and walk `print-upsell.mjs`.

**Later, on request (not in these slices):** other sheet sizes and
products (A3, cards, stickers by the roll, photo prints); vendors other
than Akuru printing; school bulk orders through quotes and credit (the
machinery exists; the configurator would need a *for a class* mode).

## 12. What this plan reuses

`SaveCartItemAction`, `PresentCartAction`, `StartBookshopCheckoutAction`,
the checkout's payment paths and `MarkCheckoutPaidAction`, `OrderView::
nextSteps` and the order step actions, `AkuruFulfilmentAction`,
`NotifyBookshopUserAction`, `CheckoutReceiptAction` and the receipt SMS link (P8a),
`StorePrivateMediaAction`, `ReadGeneratedDocumentAction`,
`DocumentRendererInterface` + `HtmlDocumentRenderer`,
`PdfConverterInterface` + `ChromePdfConverter`, `GetSettingAction` /
`SetSettingAction`, `Csv`, `NavigationMap`, the `shop` phrase book's
office words, the Bookstore walks' sign-in and fake-payment steps, the
smoke seed's Bookstore manager, `VendorCollection` for book lists, the
C15 phone-card and lazy-chunk conventions.

## 13. Decisions

| # | Question | Decision | By |
|---|---|---|---|
| D1 | Who prints | **Akuru** (the Institute's own print desk; vendor kind `akuru`) | owner, 2026-10-12 |
| D2 | Price list (papers, colour, finishing, tiers, minimum) | **Set by the office in the admin**; the slice ships sensible seeds, not prices | owner, 2026-10-12 |
| D3 | Lead time and finishing offered | **Set in the admin** | owner, 2026-10-12 |
| D4 | Templates | **Akuru makes the first set; the office can add** in the same designer | owner, 2026-10-12 |
| D5 | Fonts | **A choice of fonts** — the self-hosted OFL set in §5.4; Thaana: Faruma and Noto Sans Thaana; MV Boli excluded (licence) | owner, 2026-10-12; faces are the agent's default |
| D6 | Proof approval | **Mandatory for every order, and the customer never downloads it** — watermarked raster only; the PDF is the office's | owner, 2026-10-12 |
| D7 | Output | **Render PDF** through the existing Chrome converter; the service is off until the host can | owner, 2026-10-12 |
| D8 | Free-form designer | **Yes**, as NT6 after field mode (NT3) | owner, 2026-10-12 |
| D9 | Delivery for print orders | Collect from Akuru, or Akuru courier only | default |
| D10 | Cash on delivery for print | Off (a setting) | default |
| D11 | Price hold after approval | 7 days, then re-approve | default |
| D12 | Proof image resolution and watermark | 96 dpi, *PROOF — Akuru Bookstore* diagonal, text from settings | default |
| D13 | Draft retention | 30 days without an order | default |
| D14 | PDF uploads | Only where Ghostscript is on the host; otherwise PNG/JPEG | default |
| D15 | Quantity on the cart line | Sheets; every price is per sheet | default |
| D16 | Cutting into loose tags | A finishing option like the others, flagged `cuts_sheet` | default |

A default stands until the owner changes it; the slice that first depends
on one records it in its STATUS entry.

## 14. What the owner still owns (operator items, not code)

1. **A Chrome or Chromium on the production host**, named in
   `DOCUMENTS_CHROME_PATH` — or the decision to render elsewhere (R1).
   Until then the service switch stays off and the Bookstore shows no
   print product.
2. The real price list, papers and finishing, typed into the admin after
   NT1.
3. Ghostscript on the host (`DOCUMENTS_GS_PATH`) if PDF uploads are
   wanted (D14).
4. The first templates' artwork beyond Akuru's own marks, if any.
