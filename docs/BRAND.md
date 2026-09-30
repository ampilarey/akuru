# Akuru Institute — brand

The palette the product uses everywhere (`tailwind.config.js`), and since
2026-09-24 the logo and the app icons too (STATUS §5gc).

## Colours

| Name | Hex | Use |
|---|---|---|
| Wine | `#7C2D37` | primary — the wordmark, buttons, links, app theme colour, icon background |
| Wine dark | `#3D1219` → `#5A1F28` | the gradients behind the staff bar, footer and login panel |
| Gold | `#C9A227` | accent — the mark on wine and dark backgrounds |
| Gold dark | `#A8861F` | the mark and "INSTITUTE" on white or beige (the lighter gold is too faint on white) |
| Beige | `#F9F4EE` | page background |
| White | `#FFFFFF` | the wordmark and mark on dark; icon glyph |

The logo was blue (`#0878D8`) and grey (`#585858`) until 2026-09-24, and
matched none of this.

## Logo files

All in `public/images/logos/`. Use the Blade component
`<x-akuru-logo variant="default|on-dark|white" />` rather than a path.

Since 2026-09-29 these come from the **owner's own vector artwork** (uploaded
as `Copy of logo for website.svg`, the owner's pick of two designs: gold
emblem, wine wordmark, wine "INSTITUTE" bar). The design tool's white
background was removed and each file cropped to its artwork. The artwork's own
colours are **wine `#6E1E25`** and **gold `#C9A227`**; the site's UI keeps
Wine `#7C2D37` above — close, and the logo is the owner's to keep as drawn.

| File | Colours | Background |
|---|---|---|
| `akuru-logo.svg` | gold emblem, wine AKURU, wine bar with white INSTITUTE | white, beige |
| `akuru-logo-on-dark.svg` | gold emblem, white AKURU, gold bar with deep-wine INSTITUTE | wine, dark gradients (footer, staff bar) |
| `akuru-logo-white.svg` | all white; INSTITUTE knocked out of the bar (an SVG mask) | any dark background, one-colour print |
| `akuru-mark.svg` | the emblem alone, gold | where the wordmark is already set in text |
| `akuru-logo-800.png` | as `akuru-logo.svg`, transparent | email, structured data, anywhere SVG is refused |

**2026-09-30 (STATUS §5lv):** the owner uploaded a revised artwork, with the
AKURU wordmark redrawn slightly bolder and as wide as the INSTITUTE bar; the
emblem is unchanged. All five files above were rebuilt from it.

**Building the files.** `scripts/brand/logo-pack.mjs` takes the SVG the design
tool exports and writes all five files above: it drops the white background,
crops to the ink, and makes the on-dark, white (with the INSTITUTE knockout)
and emblem versions by colour. With `--pack <dir>` it also writes the
downloadable pack: every variant as SVG and as transparent PNG at 600, 1200 and
2400 wide, and the emblem in gold, white and wine at 512 and 1024. The pack is
not kept in the repository.

    SMOKE_CHROMIUM=<chromium> node scripts/brand/logo-pack.mjs "public/images/logos/<upload>.svg" --pack <dir>

## App icons

`public/images/`: `favicon-16x16.png`, `favicon-32x32.png`,
`apple-touch-icon.png` (180), `pwa-192.png`, `pwa-512.png`; and
`public/favicon.ico` (16, 32, 48). The white emblem on wine `#7C2D37` (redrawn
from the new artwork on 2026-09-29; the two favicons fill more of the square so
the emblem reads at 16 px), kept inside the
maskable safe zone (a circle of 80% of the side) so a launcher that crops to a
circle keeps it whole. Linked with `?v=4` so browsers drop the old icons and logos.

## Provenance

2026-09-29: the logo files are the owner's vector artwork, so they are sharp at
any size, for screen and print. The earlier files (2026-09-24) were traced
from a 200 × 109 pixel PNG; they are replaced. 2026-09-30: rebuilt from the
revised artwork (the wordmark only).

To change the logo again:
1. upload the new SVG to `public/images/logos/`;
2. run `scripts/brand/logo-pack.mjs` on it;
3. delete the upload;
4. bump the logo's `?v=` in the component, `AppShell.jsx` and `PrintCards.jsx`
   (now `?v=5`).

If the emblem changed too, the app icons must be redrawn as well. Then bump
their `?v=` in the public layout, `partials/pwa.blade.php` and
`manifest.webmanifest` (still `?v=4`).
