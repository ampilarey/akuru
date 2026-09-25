# Vendor kit — Fitrah (vendor no. 1)

The first vendor of the Akuru Online Bookshop (BOOKSHOP_PLAN §13 decision 9),
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
| Line under the name | "at Akuru Online Bookshop" (decision 11, pending) |

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

`SmokeMarkerSeeder` (from B1) plants Fitrah with the logo above, the
proposed theme and a few sample products, so `vendor.mjs` and `shop.mjs`
walk a real store. On production the office creates the vendor through
`/admin/bookshop` from this kit; nothing personal is seeded there.
