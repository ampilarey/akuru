# Phone-first — the rule, how it is held, and the audit (2026-10-20)

Most people reach Akuru on a phone: a parent checking a fee, a student
opening a lesson, a reader looking for a book. So every screen is designed
at **390 px wide first** and widened for tablets and desks — never designed
for a desk and squeezed afterwards. The owner asked (2026-10-20): *"make sure
everything follows mobile 1st rule and make this a habit for future
updates."*

## 1. The rules

1. **Phone layout is the default.** Write the 390 px layout with plain
   classes, and add the wider ones with `sm:` / `md:` / `lg:`. Never write a
   desk layout and correct it with `@media (max-width: …)` or Tailwind's
   `max-sm:` … variants.
2. **What the page is for is on the first screen.** The shelf, the course
   list, the results, the child's fees: on a 390 × 844 phone they start
   above the bottom bar. A hero, an intro or a filter form does not push
   them below. Mark that element `data-phone-main`.
3. **Filters fold.** On a phone the search box stays, and the rest goes
   under a **Filter and sort** button (the Bookstore's pattern,
   `public/shop/index.blade.php`): served open, so it works without script;
   folded by script on a phone unless a filter is in use, with a count of
   the filters that are.
4. **Records before the form.** A page that lists records and lets you add
   one (absence notes, requests, plans, behaviour) shows the records first
   and folds the form under a **New …** button on a phone — `PhoneFold`
   (`resources/js/Components/PhoneFold.jsx`) in an Inertia page.
5. **Tables become cards.** Every `<table>` is `table-stack` with a
   `data-label` on each cell (`app.css`): below `sm` each row reads as a
   card of label–value lines, with no sideways drag. The one exception is a
   grid that cards would scatter (a timetable): it carries
   `data-phone-scroll` and scrolls sideways inside its own box, never the
   page.
6. **Thumb-sized and readable.** Tap targets reach 44 px and text inputs 16 px
   on a phone (`app.css`, STATUS §5nq). Nothing scrolls sideways.
7. **Both directions.** Every phone check is done in Dhivehi too: right to
   left must not break the layout.

## 2. How the rule is held

- **CI:** `tests/Architecture/PhoneFirstStylesTest.php`:
  - no new desktop-first CSS (`max-width` media queries are pinned per file
    and may only go down; `max-*:` variants stay at none);
  - no new page with a table that is not `table-stack`. The pages still to do
    are listed in `tests/Architecture/Baselines/tables_not_stacked_on_phones.php`,
    and the list may only shrink.
- **CI:** `tests/Architecture/RecordsBeforeFormsTest.php`: a page whose posting
  form comes before its list must fold it (`PhoneFold`) or be listed, with
  the reason, in `Baselines/forms_before_lists.php`.
- **CI:** `tests/Architecture/BrandColoursExistTest.php`: no class names a
  brand colour the Tailwind config does not define.
- **Walk:** `scripts/smoke/phone-first.mjs` (in `all.mjs`) loads every public
  page, and the student's, parent's and teacher's record pages, in English
  and Dhivehi on a phone. It fails a page that scrolls
  sideways, and a page whose `data-phone-main` starts below the first screen.
- **Definition of done** (CLAUDE.md): a slice that adds or changes a screen
  is walked at 390 px, in English and in Dhivehi, before it is done.
- The older phone walks stay: `mobile`, `phone-targets`, `admin-mobile`,
  `vendor-mobile`, `public-rtl`.

## 3. The audit (2026-10-20)

Every public page was taken at 390 × 844 in English and Dhivehi (40 each),
and the student's, parent's and teacher's screens from their menus (42). Nothing
scrolled sideways. What was not phone-first:

| # | Where | What a phone sees | Slice |
|---|---|---|---|
| A1 | `/library`, `/research`, `/articles` (one page) | Search, sort and eight filters fill the first screen; the first book is at about 1,000 px | MF2 |
| A2 | `/courses` | Four full-width filters before the first course | MF2 |
| A3 | `/lending` | Six fields before the first book | MF2 |
| A4 | Portal and teacher pages with a form and a list: absence notes, requests, events, teaching plans, materials, behaviour | The whole "new" form comes first; the person's own records start below it | MF3 |
| A5 | 114 Inertia pages with a table not `table-stack` | Squeezed columns that wrap mid-word ("2026-␤10-17") or are cut off | MF4–MF6 |
| A6 | `/substitutions/requests` (old Blade) | *New request* is white on white, nearly invisible | MF3 |
| A7 | Seven files with `max-width` rules, seven on the home page alone | Desk layouts patched for phones | ratchet; the home page rewritten phone-first 2026-10-20 (STATUS §5tc) — 12 rules in 6 files left, rewritten as touched |
| A8 | Office pages with a form and a list (rooms, periods, invoices, leave balances, students and others), found walking MF4–MF6 | The *new* form before the list | MF7 |

Already phone-first, and the pattern the others follow: the Bookstore's
*Filter and sort* fold, the app's bottom tab bar, `table-stack` on 54 pages,
44 px targets and 16 px inputs, right to left at phone width.

## 4. Slices

| Slice | What |
|---|---|
| MF1 | The rule (this page, CLAUDE.md), `PhoneFirstStylesTest`, `phone-first.mjs` |
| MF2 | A1–A3: the Library, Courses and Lending filters fold; their lists carry `data-phone-main` — **done 2026-10-20 (STATUS §5sw)** |
| MF3 | A4, A6: records first, the form under *New …* on a phone (`PhoneFold`); the invisible buttons (`brandBlue` was never a colour; `BrandColoursExistTest`) — **done 2026-10-20 (STATUS §5sx)** |
| MF4 | A5, first batch: the 27 `Portal/` and `Academics/` pages — **done 2026-10-20 (STATUS §5sy)**; 88 pages left |
| MF5 | A5: `Courses/` (29) and `ExamsGrades/` (11) — **done 2026-10-20 (STATUS §5sz)**; 48 pages left |
| MF6 | A5: the rest — HR, Finance, Website, People and the others — **done 2026-10-20 (STATUS §5ta)**: every table stacked or a named grid; the baseline is empty |
| MF7 | A8: office pages put the list before the *new* form — **done 2026-10-20 (STATUS §5tb)**: 34 pages folded; `RecordsBeforeFormsTest` holds it, 24 pages kept on purpose with their reasons |
