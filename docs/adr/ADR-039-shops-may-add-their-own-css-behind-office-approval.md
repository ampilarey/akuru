# ADR-039: Shops may add their own CSS, cleaned, confined and approved by the office

## Context

BOOKSHOP_PLAN §6.8 said that per-vendor CSS and a theme marketplace were **not planned**. The reason given was that structured customisation keeps every vendor page safe, fast and accessible. That is the B4/B5 theme, which is data only: colours, approved fonts, scale and shape, rendered as CSS custom properties.

On 2026-09-26 the owner asked for both: "Need this — per-vendor custom CSS / a theme marketplace." The owner decides the product, so the question is not *whether* but *how to do it without losing what the plan was protecting*. That covers three things:

- The visitor's privacy.
- The Akuru frame (header, cart, checkout, the "at Akuru" line). §6.6 keeps this Akuru's.
- The accessibility floor.

Raw vendor CSS on a public page carries four known risks:

1. **Leaking.** Attribute selectors combined with `background: url(…)` can exfiltrate what is on the page, or track visitors.
2. **Loading from elsewhere.** `@import` and `@font-face` pull in resources from another server.
3. **Spoofing.** A full-screen `position: fixed` or absolute layer can paint over Akuru's header or checkout with something else.
4. **Escaping the `<style>` element**, or using CSS escapes to slip past a filter.

## Decision

A shop's own CSS is allowed on these terms (slice B10c, `Support/CustomCss`).

- **Refused, with a reason the shop sees:**
  - `url(`, `image-set(`, `@import`, `@font-face`, `@charset` and `@namespace`
  - script-like CSS: `expression(`, `javascript:`, `behavior`, `-moz-binding`
  - `<`, any backslash, and `position: fixed`
  - unbalanced braces, nested rules, any at-rule other than `@media`, `@supports` and `@keyframes`
  - more than 20,000 bytes

  The whole submission is refused, not silently repaired, so what the office reviews is exactly what goes live.
- **Confined.** Every selector is rewritten to sit under `.storefront`, the shop's part of its own page. `html`, `body` and `:root` become `.storefront` itself, and the same applies inside `@media` and `@supports`. When a shop has CSS live, `.storefront` gets `position: relative; isolation: isolate; contain: paint`. As a result nothing the shop draws or positions can leave its own box and cover the frame.
- **Approved by people.**
  - Only the shop's owner can submit (staff cannot).
  - A submission shows in the shop's preview straight away. Visitors only see it after the office approves it.
  - The office reviews the CSS on `/admin/bookshop` next to the preview and checks it does not hide Akuru's line, prices or policies. The office can approve it, send it back with a note, or take live CSS down at any time.
  - Removing CSS never needs approval, because a plainer page is always safe.
- The theme gallery (the "marketplace") comes in B10d. It reuses the same cleaned, approved CSS, so gallery themes add no new trust boundary.

## Consequences

- Shops can go past the structured theme: spacing, typography details, card treatments, animations.
- The office takes on a review task. Every CSS change waits for a person, and that queue is visible on `/admin/bookshop`.
- Images in CSS are not possible, because `url(` is refused. A shop that wants a background image uses the storefront's own image fields and sections, which go through the media pipeline.
- Accessibility is no longer guaranteed by construction. The contrast check covers the theme's colours but not colours set in custom CSS. The office's review is now the safeguard for that, and the hint text asks reviewers to check it.
- A cleaner bug is a security bug. `StorefrontCustomCssTest` pins each refused construct and the confinement, and any change to `CustomCss` should add a case there.
