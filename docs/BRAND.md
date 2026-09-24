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

| File | Colours | Background |
|---|---|---|
| `akuru-logo.svg` | wine wordmark, dark-gold mark and subtitle | white, beige |
| `akuru-logo-on-dark.svg` | white wordmark, gold mark and subtitle | wine, dark gradients |
| `akuru-logo-white.svg` | all white | any dark background |
| `akuru-mark.svg` | the mark alone, dark gold | where the wordmark is already set in text |
| `akuru-logo-800.png` | as `akuru-logo.svg` | email, structured data, anywhere SVG is refused |

## App icons

`public/images/`: `favicon-16x16.png`, `favicon-32x32.png`,
`apple-touch-icon.png` (180), `pwa-192.png`, `pwa-512.png`; and
`public/favicon.ico` (16, 32, 48). The white mark on wine, kept inside the
maskable safe zone (a circle of 80% of the side) so a launcher that crops to a
circle keeps it whole. Linked with `?v=3` so browsers drop the old icons.

## Provenance and limits

The only artwork in the repository was a 200 × 109 pixel PNG. The vectors were
traced from it: ink coverage per pixel, interpolated bicubically to 12×,
thresholded, and traced with potrace, one layer per colour. They are sharp on
screen at any size and faithful to the shapes; at very large sizes the
diagonal of the cap shows a faint texture inherited from the small original.
**For print or signage, export vectors from the original design file** and
drop them in under the same names — nothing else needs to change.
