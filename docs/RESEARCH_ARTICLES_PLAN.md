# Research, articles and the Digital Library: one home (plan)

**Status:** audit done 2026-09-29; the owner answered the three decisions
the same day (§4) and asked for this plan. **All five slices shipped
2026-09-29** (STATUS §5ko–§5kt); every finding F1–F12 is closed (§1.4).
What remains is the owner's production step: run
`php artisan library:import-website-research --force` once after R2's
deploy and paste back its table (STATUS §5kp).
**Owner's brief (2026-09-29):** "Does research and educational articles
come under digital library?" → re-audit → "1) both, but the author has
option to set; 2) peer review is a must, build infrastructure needed for
that; 3) need news editor."

Read with `docs/LIBRARY_PLAN.md` (§5.2–5.3 content types, §7.5–7.6 editor
and reviewer, §12.2 the research workflow, §43.8 what a writer may see) and
STATUS §5ki–§5kk (the website's four-product frame). Sections: 1 what exists
today (the audit, with file paths), 2 the target, 3 what the two systems
have that the other lacks, 4 the decisions, 5 the slices, 6 what does not
change, 7 how to audit this document.

Rules that bind every slice: CLAUDE.md rule 1 (one slice per PR), rule 3
(the Website domain never imports `Library\Models\*` — it calls Library
Actions), rule 9 (additive migrations; the `posts` rows are not deleted in
the slice that stops reading them), rule 11 (one home for a kind of
record), and the definition of done (tests, a browser walk, STATUS).

## 1. What exists today (audit, 2026-09-29)

Three things are called "Research" or "Articles", in two domains that do
not talk to each other.

### 1.1 Website research — `Website` domain

- **Storage:** `posts` rows with `type = research`
  (`App\Domains\Website\Models\Post`, `Enums\PostType::Research`). Columns
  used: `title, slug, abstract, body, citation_note, authors` (JSON: linked
  teacher profiles and outside names), `pdf_document_id` (a **public** media
  file), `is_published, published_at`.
- **Public:** `/research` (year and teacher filters, CSV export),
  `/research/{slug}` with a PDF **download** link
  (`Http\Controllers\PublicSite\ResearchPostController`,
  `views/public/research/*`). In the sitemap
  (`Actions\BuildPublicSitemapAction`) and the site search
  (`PublicSite\SearchController`, posts only).
- **Admin:** Website CMS → Research (`/admin/public-site/research`,
  `Http\Controllers\Admin\PublicSite\ResearchPostController`, Inertia
  `Website/Research*.jsx`, `Actions\SaveResearchPostAction`). The office
  publishes directly; **no review of any kind**.
- **Menu:** About ▾ → Research; footer About → Research (STATUS §5ki, §5kk).
- **Data (local):** 4 rows, all smoke-seeded. Production count unknown —
  the implementer checks before slice R2.

### 1.2 Website articles and news — `Website` domain

- **Storage:** `posts` with `type = article` or `news`; `post_categories`.
- **Public:** `/articles`, `/articles/{slug}`, `/news`, `/news/{slug}`
  (`PublicSite\PostController`). The home page shows the latest news
  (`HomeController`); News is in About ▾ and the footer.
- **Admin:** **none.** `routes/web_localized.php` has admin routes for
  research, pages, courses, daily content, leads and the funnel — nothing
  for news or articles. `Admin/PublicSite/` holds no post controller. The
  office cannot write a news item or an article today; both public pages
  can only be filled by a seeder.
- **Data (local):** 0 news, 0 articles, 0 categories.

### 1.3 Library research and articles — `Library` domain

- **Storage:** `library_items` with `content_type` = `research` or
  `article` (`Enums\LibraryContentType`; also `book`, `course_material`).
  Research fields: `abstract, citations, affiliation, research_field,
  suggested_reviewer, declarations, declared_at, pdf_media_file_id`
  (**private** media, read in the protected reader). Authors:
  `library_item_authors` (`name, user_id, sort_order`) — **no link to a
  teacher profile**.
- **Workflow (L7, §12.2), what is built:** writer submits from `/write`
  (`WriterPortalController`, `Library/Write.jsx`; declarations gate the
  submit) → office sees it under `/admin/library` (`Library/Admin.jsx`) and
  types **one reviewer's email** (`AssignResearchReviewerAction`; grants the
  `reviewer` role) → reviewer opens `/review` (`ReviewerPortalController`,
  `Library/Review.jsx`, `ListMyReviewAssignmentsAction` — title, abstract
  and body only, never sales or other reviewers) → recommends
  accept / revise / reject with a comment (`SubmitResearchReviewAction`,
  appended to the editorial trail the writer reads) → office decides
  (`ReviewLibraryItemSubmissionAction`), which **refuses to publish research
  without an accept** *if* the setting `research_review_required` is on
  (`ResolveLibrarySettingAction`, `/admin/library/settings`).
- **Gaps in that chain**, found reading the code:
  - `PublishLibraryItemAction` itself has no review check, so the office can
    publish a research item **directly** from the admin item form and skip
    review. The "must" (decision 2) is not a must yet.
  - The requirement is a **switch** the office can turn off. Decision 2
    says it is not optional.
  - One reviewer, picked by typing an email; no reviewer pool, no minimum
    count, no due date, no reminder, no reviewer conflict-of-interest
    declaration, no notification to the reviewer that work is waiting.
  - "Revise" ends the reviewer's assignment; when the writer resubmits,
    nothing re-opens the same reviewer's assignment or tells them.
  - No status the writer or office can read at a glance ("with reviewer",
    "revision requested", "accepted, awaiting publish").
- **Public:** the one shelf `/library` with a `content_type` filter,
  `peer_reviewed` and `open_access` filters (B5), item page, protected
  reader, author page (`/library/authors/{slug}`, writers only).
  **Not in the sitemap, not in the site search.**
- **Data (local):** 24 published research items, 24 review assignments,
  60 books, 0 articles.

### 1.4 The findings, numbered

| # | Finding | Where |
|---|---|---|
| F1 | ~~Two research systems; a paper in one is invisible to the other~~ | §1.1, §1.3 — **closed, R2, §5kp** |
| F2 | ~~"Research" appears three times to a visitor: About menu, footer, library filter — different lists~~ | nav, footer, `/library` — **closed, R5, §5kt** |
| F3 | ~~Website Articles page exists with no way to write one~~ | §1.2 — **closed, R2, §5kp** |
| F4 | ~~News is on the home page and in two menus with no editor~~ | §1.2 — **closed, R4, §5ks** |
| F5 | ~~Website research skips review entirely~~ | §1.1 — **closed, R2 + R3a, §5kp/§5kq** |
| F6 | ~~Library research review can be bypassed (direct publish) and switched off~~ | §1.3 — **closed, R3a, §5kq** |
| F7 | ~~Reviewer assignment is one email, no pool / due date / reminder / COI~~ | §1.3 — **closed, R3b, §5kr** |
| F8 | ~~Resubmission after "revise" does not return to the reviewer~~ | §1.3 — **closed, R3a, §5kq** |
| F9 | ~~Library items missing from sitemap and site search~~ | §1.3 — **closed, R2 sitemap §5kp, R5 search §5kt** |
| F10 | ~~Library research has no download, even when open access; website research has only download, no reader~~ | §1.1, §1.3 — **closed, R1, §5ko** |
| F11 | ~~Library authors cannot be linked to a teacher profile; the website's can~~ | §1.3 — **closed, R1, §5ko** |
| F12 | ~~No "year" filter on the library shelf; the website research page has one~~ | §1.1 — **closed, R1, §5ko** |

## 2. The target

**The Digital Library is the one home for research papers and educational
articles.** The website keeps News (with an editor, F4) and everything
about the institute. A visitor finds Research and Articles under *Digital
Library*, once. The old website addresses redirect, so no link breaks.

**Every research paper is peer-reviewed before it is public** — a
writer's, a teacher's, the office's own. There is no switch. The office's
job is to route and decide, not to bypass.

**The author chooses how a paper is offered** (decision 1): read in the
protected reader, downloadable as a PDF, or both. Open-access research
defaults to both; paid research defaults to reader only. The item page
shows a *Read* button, a *Download PDF* button, or both.

**Authors on a library item can be a teacher** (their public profile
page), a writer (their author page), or an outside name — as the website's
research already allowed.

## 3. What each system has that the other lacks (to carry across)

From the website research into the library (slice R1/R2): teacher-profile
authors (F11), year filter (F12), PDF download (F10), CSV export of the
research shelf (the library shelf already has one), the sitemap and search
entries (F9).

From the library, kept: peer review, declarations, paid / free, the
protected reader, categories and tags, writer author pages, reading
insights, EN/DV/AR.

## 4. Decisions (the owner, 2026-09-29)

| # | Question | Decision |
|---|---|---|
| D1 | Institute research: PDF download or protected reader? | **Both, and the author sets it** per item. Default: open access → read + download; paid → read only. |
| D2 | May the office publish research without peer review? | **No. Peer review is a must.** Remove the switch; gate every publish path; build the missing reviewer infrastructure (F6–F8). |
| D3 | News editor? | **Yes, build one.** News stays a website post. |
| D4 | Website Articles page | Retire it: redirect to the library's article shelf. (Follows from D1–D2 and §1.2: nothing was ever written there.) — *default, no owner objection recorded* |
| D5 | Minimum reviewers per research item | **One accept** keeps today's behaviour; the office may assign more. The office setting is *how many accepts are required* (default 1, never 0). — *default* |
| D6 | Anonymity | Reviewer never sees the author's name or the writer's earnings (§43.8, already so); the writer never sees the reviewer's name (already so). Single-blind. — *default, matches §43.8* |

## 5. The slices

Order matters: R1 makes the library able to hold what the website has; R2
moves the content and redirects; R3 hardens review (D2); R4 builds the news
editor (D3); R5 cleans the menus and closes SEO. Each is one PR with tests,
a walk in `scripts/smoke/`, a STATUS section and a line here marking it
shipped.

### R1 — The library can hold institute research (one PR) — **shipped, STATUS §5ko**

Additive only (rule 9).

- **Authors:** `library_item_authors` gains nullable
  `instructor_profile_id` (FK to the HR public-profile table the website
  research uses — see `HR\Actions\ReadPublicInstructorProfileAction`).
  `SaveLibraryItemAction` and the writer's editor accept an author row as
  `{name}` (outside), `{user_id}` (writer) or `{instructor_profile_id}`
  (teacher). The item page links a teacher author to their profile page,
  a writer author to their author page. Cross-domain: the Library asks HR
  through an Action for the profile's name and URL; it never imports
  `HR\Models\*`.
- **Delivery choice (D1):** `library_items` gains `delivery` enum
  `reader | download | both` (string-backed PHP enum, default by access
  type as §2 says). Writer editor and office form show it as three radios
  under "How readers get it", only for `research` and `article` (books
  stay reader-only — the protected reader is the book's copy protection).
  `PresentLibraryItemAction` exposes `can_download`;
  `PublicLibraryController` gets a `download` route that serves the
  private PDF through the existing access check
  (`ResolveLibraryAccessAction`) — paid items only after purchase, free
  ones to anyone — with a `Content-Disposition: attachment`. Log it as a
  reading event kind `download` so insights see it.
- **Year filter (F12):** `ListLibraryItemsAction` accepts `year`; the shelf
  shows a year dropdown when `content_type=research`.
- **Tests:** an author row of each of the three kinds saves and presents
  with the right link; `delivery` defaults per access type and the author
  can change it; download of a paid item is refused without a grant and
  served with one; download of a free public item needs no login; the year
  filter narrows the shelf. Architecture: the Library imports no HR model.
- **Walk:** `library.mjs` gains: a writer sets *both*, a reader downloads
  the PDF, the shelf filters research by year.

### R2 — Move website research into the library, redirect the old doors (one PR) — **shipped, STATUS §5kp**

- **Migration command** `php artisan library:import-website-research`
  (idempotent; dry-run flag; prints a table): for each `posts` row with
  `type = research`, create a `library_items` row — `content_type =
  research`, `access_type = free_public`, `delivery = both`, `status =
  published` if the post is live else `draft`, `published_at` kept,
  `title, slug, abstract, body, citations ← citation_note`; the public PDF
  copied into private media and set as `pdf_media_file_id` (then
  `library:sync-pages` extracts its pages for the reader); `authors` JSON →
  `library_item_authors` rows (teacher → `instructor_profile_id`, external
  → `name`). Record `posts.id` on the item (`imported_post_id`, nullable)
  so the command can re-run without duplicating. **Slug clash:** if a
  library item already has the slug, keep the library's and suffix the
  import with `-paper`; the redirect map handles the old address.
- **Imported papers were never peer-reviewed.** They publish as-is on
  import — they are the institute's already-public papers — but the item
  page shows *Peer-reviewed* only when a reviewer accepted (already so,
  the `peer_reviewed` filter), so they read as "published", not "reviewed".
  Record this in the STATUS section.
- **Redirects** (in `routes/web_public.php`): `/research` → `/library?content_type=research`;
  `/research/{slug}` → the imported item's `/library/{slug}` (301, via the
  `imported_post_id` link; 404 if no match); `/research/export` → the
  library shelf's CSV with the research filter; `/articles` →
  `/library?content_type=article`; `/articles/{slug}` → 410 Gone (nothing
  was ever published there). Route names `public.research.*` and
  `public.articles.*` stay, pointing at the redirects, so nothing that
  builds a URL breaks.
- **Retire** the admin research CMS: routes `admin.research.*`, the
  controller, `SaveResearchPostAction`, `ListResearchPostsAction`,
  `PresentResearchPostAction`, `Website/Research*.jsx`, and the
  `cms_research` nav entry. The `posts` table and `PostType::Research`
  stay (rule 9); a later cleanup slice may drop them after production has
  run on the library for a while.
- **Sitemap:** the research and article loops in
  `BuildPublicSitemapAction` now list library items (through a Library
  Action returning `slug, updated_at, content_type`) instead of posts.
- **Production step:** the pull line runs the import once (`--force`);
  its printed table goes into STATUS as the verification evidence (rule 9's
  "gates, not suggestions").
- **Tests:** the import creates items with authors, PDF and pages, is
  idempotent, and maps a slug clash; each old address redirects where §2
  says; the admin routes are gone (`WriteRoutesAreGuardedTest` baseline
  shrinks); `PublicRouteNamesTest` still resolves `public.research.index`;
  sitemap lists library research and articles and no posts of those types.
  Update `ResearchPostTest`, `AdminResearchScreensTest`,
  `NoNewBladeScreensTest` baseline (two Blade views retired) and the
  feature-testing list (`ListFeatureWalkthroughAction`: the website
  Research row becomes a Library row).
- **Walk:** `website.mjs`: `/research` lands on the library shelf filtered
  to research; an old paper's link lands on its library page with *Read*
  and *Download*.

### R3 — Peer review is a must (one PR, or two if the reviewer inbox grows) — **shipped, STATUS §5kq (R3a) and §5kr (R3b)**

D2. Everything below is in the Library domain.

- **Gate every publish path.** `PublishLibraryItemAction` refuses a
  `research` item unless `LibraryReviewAssignment` rows with
  `recommendation = accept` number at least the required count (D5). The
  check lives in one place — a new `AssertResearchReviewedAction` (or a
  method on the item) — called by `PublishLibraryItemAction`,
  `ReviewLibraryItemSubmissionAction` and the import (R2 imports are
  exempt by an explicit `imported` flag, and only there).
- **Remove the switch.** `research_review_required` leaves
  `ResolveLibrarySettingAction`, `AdminLibrarySettingsController`,
  `Library/Settings.jsx` and its test; in its place a setting
  `research_reviews_required` (integer, min 1, default 1). Migration note
  in STATUS; LIBRARY_PLAN §42 line updated.
- **Reviewer pool.** A `reviewer` is a role already; add
  `/admin/library/reviewers` — list users with the role, add by email,
  remove; a reviewer row shows open and done counts and average days to
  report. Assignment on `Library/Admin.jsx` becomes a picker over the pool
  (still allows a new email, which adds them to the pool).
- **Assignment fields:** `library_review_assignments` gains `due_at`
  (default 14 days, editable on assign), `reminded_at`, `coi_declared_at`
  (reviewer confirms no conflict of interest before the item's body is
  shown — the same declaration pattern as the writer's, §11.5), and
  `round` (see below).
- **Revision loop (F8).** When a reviewer recommends *revise*, the item
  goes back to the writer as `changes_requested` (an item status that
  already exists for the editor path — reuse it). When the writer
  resubmits, every assignment on the item whose last recommendation was
  *revise* re-opens as `assigned` with `round + 1`, and the reviewer is
  notified. Accepts from an earlier round do **not** count toward the
  required number — an accept is per round.
- **Notifications** through the existing `NotifyLibraryUserAction`: to the
  reviewer on assignment and on a resubmission; a reminder 3 days before
  `due_at` and on the day (a scheduled command
  `library:remind-reviewers` in `Library/Console`, scheduled in
  `routes/console.php` next to `library:remind-readers`); to the writer on each recommendation (the
  comment, never the reviewer's name — §43.8, D6); to the office when the
  required accepts are reached ("ready to publish").
- **Status the humans can read.** `PresentLibraryItemAction` (writer side)
  and the office list expose `review_state`: `awaiting_reviewer`,
  `with_reviewer (n of m)`, `revision_requested`, `accepted_awaiting_publish`,
  `rejected`. Shown as a chip on `/write` and `/admin/library`.
- **Reviewer inbox** (`/review`, `Library/Review.jsx`): the COI step, the
  due date, the round number, the item's body, the reviewer's own earlier
  report on this item, and the writer's revision note when there is one.
  Never the author's name, price, sales or the other reviewers.
- **Tests:** direct publish of an unreviewed research item is refused from
  every path (action, admin controller, writer); the count setting cannot be
  set below 1; a *revise* re-opens the assignment on resubmit and the old
  accept no longer counts; COI must be declared before the body is served;
  reminders fire at the right days and once; notifications reach the right
  people with the right content and no reviewer name reaches a writer.
  Architecture: still no cross-domain model import.
- **Walk:** `peer-review.mjs` extended: office adds a reviewer to the pool,
  assigns two, one says *revise*, the writer revises and resubmits, the
  same reviewer sees round 2 and accepts, the second accepts, the office
  publishes; before both accepts the office's publish button is disabled
  and the action refuses.

### R4 — A news editor (one PR) — **shipped, STATUS §5ks**

D3. Website domain, Inertia, trilingual (the C9 pattern).

- **Admin:** Website CMS → News at `/admin/public-site/news`: list (title,
  status, published date, featured/pinned, category; CSV export), form
  (title, slug, summary, body with `RichTextEditor`, cover image via
  public media, category, tags, featured, pinned, publish date, meta
  description), preview. `SaveNewsPostAction` in the Website domain writes
  `posts` with `type = news`. Categories: `/admin/public-site/news/categories`
  (name, slug, order, active) — `post_categories` already exists.
- **Permission:** the existing website CMS gate (the same one `cms_pages`
  uses).
- **Public:** `/news` and `/news/{slug}` stay as they are (they already
  render posts); check RTL and the three languages on the two Blade views
  and fix what the walk finds.
- **Home page:** already reads the latest news (§5kl shows the empty line
  until one exists).
- **Tests:** create, edit, publish/unpublish, pin, feature; a draft is not
  public; the CSV; the gate refuses a non-CMS user; `NoNewBladeScreensTest`
  unchanged (Inertia). Update the feature-testing list with a *Website →
  News* row.
- **Walk:** `admin-pages.mjs` (or a new `news.mjs`): the office writes a
  news item with a cover, publishes it, and it appears on `/news` and on
  the home page.

### R5 — One place in the menus, and search (one PR) — **shipped, STATUS §5kt**

- **Header (W1's `nav.blade.php`):** *Digital Library* becomes a dropdown
  like About — Books · Articles · Research · Authors · Gift cards — each a
  filtered shelf link. About ▾ loses Research and Articles. The phone menu's
  Digital Library row gains the same four as small links under it. Footer
  (W3): the Digital Library group is Browse books · Articles · Research ·
  Authors · Gift cards · Write for Akuru; About loses Research and
  Articles.
- **Site search** (`PublicSite\SearchController`): a fourth result group,
  *Library*, from `Library\Actions\ListLibraryItemsAction::execute(['q' =>
  …])` (published only), showing type, title, author and price / Free.
- **Home page:** the *New in the library* row (W2) gets a small type label
  per card (Book / Article / Research) so the row's variety is visible.
- **Tests:** `SiteFrameTest` — the library dropdown's five links and their
  filters; About holds neither Research nor Articles; search returns a
  library item for its title and not a draft. `SiteFooterAndStatsTest`
  updated for the new group.
- **Walk:** `website.mjs`: Digital Library ▾ → Research lands on the
  filtered shelf; a search for a library title finds it.

### Later, not in this plan

Journals and issues, DOIs, ORCID, double-blind, reviewer payments, a
citation exporter (BibTeX) — LIBRARY_PLAN §38's post-L7 backlog. Dropping
`posts.type = research` and its columns: a cleanup slice after production
has run R2 for a while (rule 9's third deploy).

## 6. What does not change

- Books, course materials, the protected reader, purchases, gift cards,
  wallet, writer earnings, promotions — untouched.
- The writer's declarations at submit (§11.5) stay; R3 adds the reviewer's.
- News stays a website post; Pages, Courses CMS, Daily content, Leads —
  untouched.
- `PostType` keeps its three cases; `LibraryContentType` keeps its four.
- The `posts` table and its research rows stay until a later cleanup.
- Domain boundaries: Website calls Library Actions (sitemap, search,
  redirects); Library calls HR Actions (teacher profiles). No model
  crosses.

## 7. How to audit this document

Each §1 claim names a file; each finding F1–F12 is re-checkable by opening
it. Before starting a slice, re-run the counts in §1 against the branch
(`Post::research()->count()`, `LibraryItem::where('content_type','research')`,
`library_review_assignments`) and grep `routes/web_localized.php` for
`admin.research` and `admin.news` — if a later PR has moved something, fix
this document first, then start. When a slice ships, mark its heading
**shipped, STATUS §…** as SIGN_IN_PLAN does, and strike the findings it
closes in the §1.4 table.
