# Print products beyond name tags — plan (2026-10-20)

The name-tag print desk (NAME_TAGS_PLAN, NT0–NT7) is built: the office
sets papers, finishing, layouts and templates; a customer designs or
uploads, approves a watermarked proof, pays, and Akuru prints from the
queue. NAME_TAGS_PLAN §11 left these for later: other sheet sizes, cards,
stickers, photo prints, other vendors printing, and school bulk orders.
The owner asked for them on 2026-10-20 ("do all"). This page sets out the
plan. Each decision has a **default** that stands until the owner changes
it, as NAME_TAGS_PLAN §13 did.

## 1. What the desk assumes today (audited against the code, 2026-10-20)

| # | Assumption | Where |
|---|---|---|
| F1 | Every sheet is A4. A layout is "tags per A4", and it must fit 210 × 297 mm. | `PrintLayout::fitsA4()`, `SavePrintLayoutAction`, `AdminPrintController` (`max:210`, `max:297`) |
| F2 | A design is at most 210 × 297 mm. | `PrintDesign::validate` (line 41) |
| F3 | An uploaded sheet must have A4's shape. | `PrintUpload::A4_RATIO` |
| F4 | A paper has one price for black and white and one for colour, per sheet, with no size. | `print_paper_options.price_bw`, `price_colour` |
| F5 | There is one print product, *Name tags — A4*, made by the desk the first time it is opened. | `PrintDesk::ensure()` |
| F6 | A template is a `name_tag`, a `subject_tag` or `both`. | `print_templates.kind` |
| F7 | The customer's door is `/shop/print/name-tags`. | the Bookstore's print routes |
| F8 | Only Akuru prints (vendor kind `akuru`), and only Akuru earns nothing on a print sale. | NAME_TAGS_PLAN D1, NT1 |
| F9 | Bulk buying exists apart from print: a school asks for a quote (`QuoteRequest`) and pays on credit (`ShopCreditAccount`, P8c). A print job cannot be put on a quote. | `app/Domains/Bookshop/Models/QuoteRequest.php`, COMMERCE_PARITY_PLAN P8c |

What is **not** assumed, and so carries over unchanged: the renderer
already takes a page size (`print-sheet.blade.php`, `$page` in mm); the
proof, the queue, reprints, the cart line and the money path do not care
what is printed.

## 2. Decisions (defaults)

| # | Question | Default |
|---|---|---|
| PD1 | Sheet sizes | **A4 and A3**, and **A5** for cards and photos. The office says which papers come in which size. |
| PD2 | Price per size | ~~Each paper has a price per size~~ **Each paper is stock of one size, with its own prices** (as built in PP1). A size with no priced paper is not offered. |
| PD3 | What is sold | Four print products: **name tags** (today), **cards**, **stickers on sheets** and **photo prints**. Each is a Bookstore product in *Print*. |
| PD4 | Cards | **Flat cards only**: business cards (85 × 55 mm), place and gift cards (A6), invitations (A5). Folded cards are parked. Cards print with **3 mm bleed** and crop marks. |
| PD5 | Stickers | **On A4 and A3 sticker sheets**, rectangles and circles, cut by a *kiss-cut* finishing. **Rolls are parked** until Akuru has a roll printer and cutter. |
| PD6 | Photo prints | **Upload only** (PNG or JPEG; PDF where Ghostscript is on the host, as NT5). Sizes: 4 × 6 in, 5 × 7 in, A5, A4 and A3. *Fill* (crop to the size) or *fit* (white border). Below 150 dpi at the chosen size is warned; below 72 is refused. Priced **per print**, not per sheet. |
| PD7 | Proof | **Mandatory for every product**, watermarked, never downloaded (NAME_TAGS D6 carries over). |
| PD8 | For a class | The office, and a teacher for the classes they teach, can fill a name list **from a class**. The names come from People through a contract (rule 3). A parent or other customer cannot pull a class list; they paste or upload names as today. |
| PD9 | Bulk quotes | A print job of **20 sheets or more**, or one from a customer with an active credit account, can **ask for a quote** instead of checking out. It joins a `QuoteRequest`; the office prices it; an accepted quote becomes an order on the account's credit (P8c). |
| PD10 | Other vendors printing | **Not yet.** Akuru prints (D1). The slice is written (PP7) but built only when a vendor asks to print through the Bookstore. |
| PD11 | Lead time and minimum | **Per product**, set by the office; the desk's settings are the default. |

## 3. Slices, in order (one PR each, walked in a browser, at 390 px, in English and Dhivehi)

### PP1 — Sheet sizes (A4, A3, A5)

> **Built 2026-10-20 (STATUS §5ti)**, with one change from the plan: **a
> paper is stock of one sheet size**, with its own two prices
> (`print_paper_options.sheet_size`), instead of a `print_paper_prices`
> table. A3 gloss is its own paper, as a print shop buys it. Today's price
> columns stay as they are, and every paper made before reads as A4. A job
> whose layout changes size moves to that size's first paper. Found and
> fixed on the walk: the job pages scrolled 24 px sideways on a phone (the
> grid had no phone column), on A4 too.

- Additive: `print_layouts.sheet_size` (`a4` by default, so every layout
  stays as it is); a `print_paper_prices` table (paper, sheet size,
  price black and white, price colour); existing paper prices copied in as
  their A4 row. The old price columns stay for the cleanup deploy (rule 9).
- Layouts fit *their* sheet (F1); designs fit the layout's sheet (F2); a
  sheet upload must have its sheet's shape (F3); the quote reads the
  paper's price for the layout's size (F4).
- Office: the papers page gains a price per size; the layouts page gains
  the size.
- Tests: an A3 layout of 24 tags fits and renders A3 pages; a paper with
  no A3 price is not offered on an A3 layout; old jobs price as before.

### PP2 — Print products and their doors

> **Built 2026-10-20 (STATUS §5tj)**, with these choices: name tags stays
> on and off with the desk's own switch (it has no button of its own); the
> other three are put on sale one by one, only while the desk is on and only
> with an active layout of their own, and the desk switched off takes them
> all off. **PD11 (per-product lead time and minimum) moves to the slice
> that first needs a different one** — every product shares the desk's
> settings until then.

- `print_templates.kind` gains `card`, `sticker` and `photo`; layouts and
  templates belong to a product; `PrintDesk::ensure()` makes the four
  products (F5), each off until the office switches it on.
- `/shop/print` lists the products that are on; `/shop/print/{product}`
  is the existing configurator, showing that product's templates, layouts
  and papers (F7). The name-tag address keeps working.
- Per-product lead time and minimum (PD11).
- Tests: a product that is off is not listed and its door is 404; a
  product's configurator offers only its own templates and layouts.

### PP3 — Cards

> **Built 2026-10-20 (STATUS §5tk)**, with these corrections: the card
> layouts are business cards **8 per A4** and **18 per A3**, A6 place and
> gift cards **2 per A4** and A5 invitations **1 per A4** — with a 3 mm
> bleed, the cards need 6 mm between them, so the plan's 10, 4 and 2 per A4
> do not fit. With a bleed, crop marks go at the sheet's edges only, in line
> with every cut. Cards gained three fields: role, phone and email. The
> designer still draws the cut size; a box or picture placed against an edge
> prints into the bleed.

- Bleed (3 mm) in the design model and the renderer, with crop marks;
  card layouts (business card 10 per A4, 24 per A3; A6 4 per A4; A5 2 per
  A4); five Akuru card templates.
- Tests: a bleed element prints past the trim and the crop marks sit on
  the trim; a card template takes a name list like a tag.

### PP4 — Stickers on sheets

- Layouts with a shape (rectangle or circle) that the designer and the
  proof show; a sticker paper kind; *Kiss-cut* as a finishing
  (`cuts_sheet` false, so the sheet stays whole).
- Tests: a circle layout clips the design in the proof; kiss-cut prices
  per sheet.

### PP5 — Photo prints

- Upload, size, *fill* or *fit*, dpi warning and refusal (PD6), per-print
  pricing, prints imposed on the fewest sheets for the queue.
- Tests: a 4 × 6 photo at 100 dpi is warned, at 60 refused; ten 4 × 6
  prints are imposed on A4 sheets; the price is per print.

### PP6 — For a class, and bulk quotes

- A class roster contract from People (names in the three languages, no
  other fields); the configurator's *From a class* for the office and a
  class's teachers (PD8).
- *Ask for a quote* on a job of 20 sheets or more, or from a credit
  customer; the quote screen shows the job and its proof; an accepted
  quote is an order on credit (PD9).
- Tests: a teacher sees their own classes only; a parent has no *From a
  class*; a quoted job orders at the quoted price, never the list price.

### PP7 — Other vendors printing (only when a vendor asks; PD10)

- A vendor's own print desk (papers, prices, layouts) and queue on
  `/vendor`; their print sales earn like any other sale.

## 4. What the owner owns

- The sizes, papers and prices per size, and which products to switch on
  (the office, on `/admin/bookshop/print`).
- Whether Akuru will buy a roll printer (stickers by the roll, PD5) or
  print folded cards (PD4).
- Any change to the defaults above.
