# Vendor kit — Fitrah (vendor no. 1)

The first vendor of the Akuru Bookstore (named 2026-09-26; BOOKSHOP_PLAN §13 decision 9),
an independent educational-items shop selling inside the bookshop. Details
supplied by the owner on 2026-09-25; more to follow. This file is the
office's checklist for creating the vendor on production once slice B1
deploys, and the source for the staging seed the browser walks use.

**Not in this file, ever:** bank details and ID documents — the vendor
enters those in her own portal.

## Identity

| Field | Value |
|---|---|
| Shop name | **Fitrah** |
| Tagline | iman.noor.ihsan |
| Address | `/shop/fitrah` (slug `fitrah`, fixed at creation) |
| Owner (vendor member, role owner) | Fathimath Inaaya |
| Owner account | email `f7920288@gmail.com`, mobile `7920288` — the sign-in identity; the office creates the account (or links it if one exists) and she sets her password on first sign-in |
| Line under the name | "at Akuru Bookstore" (decision 11) |

## Branding

| Element | Value |
|---|---|
| Logo | `database/seeders/fixtures/vendors/fitrah-logo.jpg` (1562×1562, dusty-blue square, "fitrah" in coral pink, tagline in sandy yellow). Ask for an SVG or PNG on transparent for the header; the square works as the avatar and share image. |
| Palette — Sandy Yellow | `#F2C778` |
| Palette — Dusty Blue | `#7B9AA5` |
| Palette — Coral Pink | `#EBAD99` |
| Lettering | a rounded slab/typewriter serif for the name, a monospace-style serif for the tagline |

### Proposed theme (for the storefront designer, B4)

The three brand colours are soft and close in lightness, so they cannot
carry body text on each other (coral on dusty blue is a display pairing,
not a reading one — the designer's contrast check will say so). Proposed
mapping, to be confirmed with the vendor:

| Theme slot | Value | Used for |
|---|---|---|
| Primary | Dusty Blue `#7B9AA5` | storefront header band, hero background, section headings' underline |
| Accent | Coral Pink `#EBAD99` | buttons, badges, links on light backgrounds |
| Secondary | Sandy Yellow `#F2C778` | announcement bar, highlights, sale badges |
| Page background | Cream `#FBF7F1` | pages |
| Card background | White `#FFFFFF` | product cards |
| Text | Ink `#2F3A40` | body and headings on light backgrounds |
| Text on primary | Cream `#FBF7F1` | text over the dusty-blue band |
| Heading font | Bree Serif (rounded slab, close to the logo) | headings |
| Body font | Inter | body |
| Tagline / accent font | Courier Prime | the tagline, small labels |
| Shape | soft corners, filled buttons, flat cards with a hairline border | |

Bree Serif and Courier Prime are added to the plan's approved font list
(§6.2) for this.

## Still to collect

- Legal name and TIN (or "not GST-registered").
- Customer contact: phone, email, Viber, collection address, opening hours.
- Delivery: collect / Malé–Hulhumalé–Villimalé fee / atolls fee / free over.
- Returns window and conditions.
- "About us" text and social links (Instagram, Facebook, TikTok).
- First products: name, description, category, price, stock, photos; for books author/publisher/ISBN/language; for educational items age or grade and subject. A spreadsheet is fine; 10–20 items to launch.

## On staging

`SmokeMarkerSeeder::vendorCycle()` (B1a) plants Fitrah — slug `fitrah`,
code `FIT`, tagline — with three sample products and a synthetic staging
owner, `vendor@akuru.edu.mv`. The real owner's email is not seeded
anywhere. `vendor.mjs` walks it. The logo and theme arrive with the
designer (B4).

## On production (after B1a is pulled)

1. Run once: `php artisan db:seed --class=BookshopCatalogueSeeder --force`
   and `php artisan db:seed --class=BookshopPolicyPagesSeeder --force`.
2. Read the Vendor Agreement draft (`/page/vendor-agreement`) and edit it
   in the page editor.
3. At `/admin/bookshop`, *Invite a vendor*: shop name **Fitrah**, address
   `fitrah`, code `FIT`, tagline `iman.noor.ihsan`, owner **Fathimath
   Inaaya**, email `f7920288@gmail.com`, mobile `7920288`.
4. If her email has no Akuru account yet, the screen shows a one-time
   password once. Send it to her with the sign-in address. She signs in,
   is asked to set her own password, opens *My shop*, accepts the
   agreement, and lists her products.
