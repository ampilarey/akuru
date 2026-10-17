# Blogs in the Digital Library — plan v1 (2026-10-17)

> The owner's brief (2026-10-17): give students who want to write the chance
> to publish under their own blog, and sometimes to earn from it. It should
> read like a blog. **Everything is published only after it is checked.**
> Each writer's page can be customised. Students and full writers both have
> blogs, under different rules.
>
> The owner's decisions are in **§13**. Everything else is the default an
> implementer takes unless the owner says otherwise. **BL1 and BL2 are
> built** (2026-10-17: BL1 STATUS §5se; BL2 in three parts, §5sf–§5sh),
> BL3 in two parts (§5si, §5sj), BL4 (§5sk), BL5 (§5sl), BL6 in two parts
> (§5sm, §5sn) and BL7 (§5so). **Every slice is built (2026-10-18).** What
> remains is the owner's (§14) and the *Later, on request* list. This document is the whole
> brief for the agent that builds them.

Written against `main` at `51b9c58` (#777). Every name below was read from
the tree. Where a slice must verify something before relying on it, the
plan says so.

---

## 1. Shape of the thing

A **blog** is a writer's page in the Digital Library, laid out as a blog:
- a banner and a header;
- pinned posts, then the latest posts, newest first;
- series of posts, and topics;
- a follow button, and a feed.

A **post** is a new kind of Library item (`content_type = post`). It is
written in the editor articles already use, checked, and published.

Two kinds of blogger share one system, by a `kind` on the writer's profile:

| | Student blogger | Full writer |
|---|---|---|
| Who | An Akuru student (a `students` row linked to the account: a school pupil or a course learner) | An approved author, usually an adult |
| How they join | *Start a blog*: a short request; a parent consents if under 18; no ID card | The writer application as it is today, with an ID card |
| What they publish | Posts, in series | Posts, plus books, research and paid articles, as today |
| Who checks a post | Their class teacher first, then the office; the office alone if no class teacher | The office; research also goes to peer review, as today |
| Name and photo | A pen name or first name; a photo only with the parent's photo consent | Full name and photo, if they wish |
| Page design | Themes, colours and sections from the gallery | The same; custom CSS is out for now (§12) |
| Comments | Every comment checked first; none for under-13s | Every comment checked first |
| Earning | Tips and Akuru's featured-post rewards | Tips, rewards, and sales of paid works as today |
| Where money goes | Under 18: the parent's Akuru wallet. 18 or over: their own | Their own, as today |
| Badge | *Student writer*, and the school name if the parent allows | *Writer* |

**Growing up:** at 18, or when approved as a full writer earlier, a student's
`kind` changes. They keep the same page, posts, followers and earnings. The
parent's part in consent and money ends.

## 2. How it fits what exists

- **The writer's profile** (`writer_profiles`, `WriterProfile`) already has:
  - a display name, slug, bio, qualifications, expertise;
  - a photo, featured items, social links;
  - a status and a default commission.

  The blog adds columns to it (§8). The public author page
  (`/library/authors/{slug}`, `public.library.author`,
  `resources/views/public/library/author.blade.php`) becomes the blog page.
  Its address stays; the old one keeps working.
- **Posts are Library items.** They reuse:
  - `SaveWriterItemAction` and `SubmitLibraryItemForReviewAction`;
  - `ReviewLibraryItemSubmissionAction` (decision and comment, recorded in
    `library_item_reviews`);
  - `PublishLibraryItemAction`;
  - the TipTap editor (B3) and `App\Support\Html\HtmlSanitizer`;
  - `LibraryItemStatus` (`draft`, `submitted`, `changes_requested`,
    `rejected`, `published`, `archived`);
  - the trilingual phrase books.

  A post is `access_type = free_public` by default.
- **The writer portal** (`/write`, Inertia) gains *My blog*: posts, series,
  the page designer, comments, followers and tips.
- **The office** (`/admin/library`, Inertia) gains four lists:
  - posts waiting;
  - comments waiting;
  - page changes waiting;
  - start-a-blog requests.

  These sit beside the existing submissions queue.
- **The page designer** reuses the Bookstore's storefront designer idea
  (B4/B5): a theme as data, a contrast check, sections as data, a live
  preview. `Theme` and `Contrast` live in
  `app/Domains/Bookshop/Support/`, and the Library may not import another
  domain's code (rule 3). **BL3 moves them to `App\Support\Theme\`** and
  points the Bookstore at the new place, with no change in behaviour.
- **Money** reuses the Library's ledger:
  - `WriterEarning`, matured by `MatureWriterEarningsAction` after the
    refund window;
  - payouts by `RequestWriterPayoutAction` and `DecideWriterPayoutAction`;
  - the Library's payment paths (`StartLibraryCheckoutAction`: BML
    through the payments contract, or the wallet).

  Rule 12 holds: access and earnings follow the BML **webhook**, never the
  return URL. The ledger is append-only.
- **People** gives the age and the parent:
  - `ResolveStudentForUserAction` (a user's `students` row);
  - `students.date_of_birth`;
  - the guardian link (`ListGuardianChildrenAction`,
    `GuardianCanAccessStudentAction`);
  - consents (`RecordConsentAction`, `HasActiveConsentAction`,
    `ConsentType`), where `photo_media_use` already exists.
- **Academics** gives the class teacher: `students.class_id` →
  `classes.class_teacher_id`, which stores `users.id`. A new Academics
  action returns it (§10).
- **Notices** go through `NotifyLibraryUserAction`, in the app, by email and
  by SMS, with the office's switches on `/admin/library/settings`.
  `NotifyReadersOfNewWorkAction` already tells a writer's readers about new
  work. Followers join them.
- **Comments** follow the Bookstore's product questions
  (`ProductQuestion`, `ModerateQuestionAction`): a status, a moderator, a
  note.
- **Pages:** the public Library is Blade (`resources/views/public/library/*`),
  as the public site may be. The blog's public pages follow it and are
  added to the `blade_screens` baseline, as the Bookstore's storefront pages
  were. Every screen people sign in to is Inertia.

## 3. Reader features

### 3.1 Finding blogs
- **`/library/blogs`**: the blogs front page.
  - Latest posts from every blog, newest first.
  - *Featured this month* at the top.
  - Filters by topic and language, and *Student writers* or *Writers*.
  - A list of blogs.
- *Blogs* in the Library's menu and the site search (R5). Posts in the
  sitemap.
- The routes are registered **before** `library/{slug}`, the Library's
  catch-all.

### 3.2 A blog: `/library/authors/{slug}`
- The writer's banner, picture (or initials), blog title and tagline.
- The badge (§1), and the follow button with the follower count.
- The sections the writer turned on, in the writer's order (§5):
  - pinned posts;
  - latest posts, paged;
  - series;
  - topics;
  - published works;
  - about;
  - the tip box.
- The page's own feed, `/library/authors/{slug}/feed`, as RSS.

### 3.3 A post: `/library/{slug}`, as for every Library item
- Title, cover, date, reading time and topic tags.
- The series it belongs to, with *previous* and *next*.
- The text, sanitised as articles are.
- *Follow*, *Share*, *Tip the writer*.
- Approved comments, and a comment box for signed-in readers.
- *More from this blog*.

### 3.4 Following
- Signed-in readers follow a blog.
- A new post tells them in the app, and by email if they chose.
- *My Library* gains *Following*.

### 3.5 Comments
- Signed-in readers only.
- Every comment is checked before it shows, on every blog.
- **No comments on an under-13's blog** (D13).
- Plain text, up to 1,000 characters.
- The blog's owner may reply; the reply is checked too.
- A *Report* link on every published comment sends it back to the office.

### 3.6 Tipping
- *Tip the writer*: preset amounts (MVR 5, 10, 25, 50; the office sets
  them), paid by card through BML or from the wallet.
- A short thank-you note from the reader is optional and checked like a
  comment before the writer sees it.
- The tip box shows only on blogs whose owner turned it on.

## 4. Writer and student features

### 4.1 Starting a blog
- **Student:** *Start a blog* on My account (or the Learn home):
  - a pen name;
  - a blog title;
  - a line about what they will write.

  If the student is under 18, the request waits for a parent:
  - each linked, verified guardian sees it in the family portal and may
    consent;
  - one consent is enough;
  - consent is recorded as a People consent (`blog_publishing`, a new
    `ConsentType`);
  - the parent may also allow the photo (`photo_media_use`, existing) and
    the school name on the badge.

  Then the office approves the request. A student with no verified
  guardian cannot be approved under 18. The request says so.
- **Full writer:** the existing writer application. An approved writer has
  a blog at once.
- An approved blogger holds the `writer` role and the Writing workspace, as
  writers do today.

### 4.2 Writing a post
- *New post* in `/write`:
  - title, cover image, text in the article editor;
  - topic tags, language, series;
  - *pin to my page*;
  - comments on or off.
- *Submit for checking* sends it to the reviewer (§6).
- A post waiting cannot be edited; one sent back can, and goes again.
- A published post can be edited. **The change is checked before it
  replaces the live text** (D3): the live post stays as it was until the
  change is approved.

### 4.3 Series
- A series has a title, a slug and an order.
- Posts join a series; the post page shows *Part n of m*.

### 4.4 My blog
- Posts by state.
- Comments waiting on my posts (read only for a student; the office
  decides).
- Followers.
- Reads per post.
- Tips and rewards, with a CSV.

## 5. Customising the page (`/write/blog/design`)

The designer is the Bookstore's approach, smaller.

| Part | Choices |
|---|---|
| Theme | a gallery of themes (§8), each a set of colours, fonts and shape |
| Accent colour | free choice, refused if it fails the contrast check against the theme |
| Heading font | from the Library's self-hosted set (Latin, Thaana, Arabic) |
| Banner | an uploaded image, cropped to 4:1, checked like any upload (MD1) |
| Picture | an uploaded photo (only with photo consent for an under-18), or initials on the accent colour |
| Header | blog title, tagline (up to 120 characters), *about me* (up to 1,500 characters, plain text with line breaks) |
| Languages | the languages the writer writes in, shown as chips |
| Sections | on or off, and their order: pinned, latest, series, topics, published works, about, tips |
| Links | up to five links (full writers; students only with the parent's consent) |

- The preview shows the page as readers will see it, in the page's
  language, left to right and right to left.
- **Every change is a draft, sent for checking** (D14). The live page
  stays as it was until the office approves the change:
  - one approval covers the whole set of changes;
  - a refused change comes back with the office's note.
- No custom CSS, no script, no embed (§12).

## 6. Checking (verification)

Everything published is checked first (D3).

| What | First check | Then |
|---|---|---|
| A student's post | the student's class teacher (new teacher queue under *Teaching*) | the office |
| A student's post, no class teacher (a course learner) | — | the office |
| A full writer's post | — | the office |
| A change to a published post | as for a new post | — |
| A page change (banner, about, picture) | — | the office |
| A start-a-blog request | the parent, under 18 | the office |
| A comment, a reply, a tip's note | — | the office |

- A teacher may approve (it goes on to the office), send back with a note
  (it returns to the student) or refuse (with a note).
- The office decides as it does for submissions today.
- Each step is a `library_item_reviews` row (decision, comment, who, when),
  so the trail is the item's.
- The office may turn the teacher step off (D6).
- **Writing guidelines** (a CMS page the owner writes, like the Library
  policy pages) are linked from *New post* and from every refusal.

## 7. Money

All of it goes through the Library's ledger. Rule 12 holds throughout.

### 7.1 Tips
- A tip is a payment to Akuru through the Library's payment paths (card
  through BML, or the wallet).
  - Earning follows the **webhook**, never the return URL.
  - A tip is never discounted.
  - A gift card pays as a wallet balance pays.
- On payment, a `library_tips` row (append-only) and a `WriterEarning` row
  are written. The earning is the tip less **Akuru's share (default 10%,
  the office sets it)**, then matures after the refund window like any
  earning.
- A refunded tip reverses its earning with a new row, never an edit.

### 7.2 Featured-post rewards
- The office marks a post *Featured this month* and may give it a reward:
  - a `WriterEarning` row of kind `reward`, funded by Akuru;
  - an amount the office types, at most the setting's cap;
  - **off by default** (D12).

### 7.3 Payouts
- Writer payouts are **off** until the tax treatment is confirmed
  (the `payouts_enabled` Library setting; BACKLOG A9). Earnings accrue
  meanwhile, for students as for writers.
- When payouts are on:
  - **A student under 18 is paid only to a parent's Akuru wallet**
    (`CreditWalletAction`, source `library_payout`), never to a bank.
    - The parent is the one who consented.
    - The payout names both.
  - A student of 18 or over, and a full writer, are paid as writers are
    today.
- The owner may allow wallet payouts before bank payouts (D11).

### 7.4 What a student never sees
- Bank details.
- The writer agreement's money clauses, which their parent reads at
  consent.
- Any price on their own posts: posts are free (D16).

## 8. Data (all additive; morph aliases in the same slice, ADR-005)

| Table / column | What | Notes |
|---|---|---|
| `writer_profiles.kind` | `writer` or `student` | string, default `writer`; existing rows stay `writer` |
| `writer_profiles.blog_title`, `tagline` | the header | nullable |
| `writer_profiles.theme` | json: theme key, accent, heading font | read through `App\Support\Theme\Theme` |
| `writer_profiles.sections` | json: on/off and order | defaults to all on |
| `writer_profiles.banner_media_file_id` | the banner | private media until the page change is approved, then public |
| `writer_profiles.pending_changes` | json: the page draft | the live columns change only on approval |
| `writer_profiles.show_photo`, `show_school` | the parent's choices | for students |
| `writer_profiles.guardian_user_id`, `guardian_consented_at` | the consenting parent | for under-18s |
| `writer_profiles.tips_enabled`, `comments_enabled` | the writer's switches | comments forced off under 13 |
| `writer_profiles.followers_count` | a cached count | kept by the follow action |
| `blog_requests` | a student's *start a blog* request | user, pen name, title, line, status, decided by/at, note |
| `library_items.content_type = post` | a new `LibraryContentType` case | no column change |
| `library_items.series_id`, `series_position`, `pinned` | series and pinning | nullable |
| `library_items.pending_body`, `pending_title` | a published post's change, waiting | the live text changes on approval |
| `library_post_series` | a writer's series | writer, title, slug, sort |
| `library_follows` | reader → writer | unique pair; created_at |
| `library_comments` | a comment or reply | item, user, parent, body, status (`pending`, `published`, `refused`, `hidden`), moderated by/at, note |
| `library_tips` | a tip | append-only: payer, writer, item (nullable), amount, payment, status, note, note_status; `academic_year_id` if `AcademicBackboneTest` calls for it |
| `writer_earnings.kind` | `sale`, `tip` or `reward` | default `sale`; existing rows are sales |
| `library_blog_themes` | the theme gallery | key, names in three languages, the theme json, active |

- Morph aliases: `library_post_series`, `library_follow`,
  `library_comment`, `library_tip`, `blog_request`.
- `library_comments.status` and `blog_requests.status` go into
  `StatusValuesAreWritableTest`'s check.
- Deleting a writer with comments, tips or followers is refused, as the
  money tables refuse a delete while money hangs off the row today (STATUS §5ii).

## 9. Risks, and what each slice does about them

- **Children's safety.**
  - Consent before anything is public.
  - A pen name by default.
  - A photo only with photo consent.
  - No links without consent.
  - No comments under 13, and every comment checked.
  - No contact details on a student's page, ever: the page has no field
    for them.
  - The office's *hide* is immediate, on any post, comment or page.
- **Money for minors.**
  - Earnings accrue to the student's ledger.
  - Payouts go only to the consenting parent's wallet while the student is
    under 18.
  - Tests prove a bank payout is refused for an under-18.
- **Checking load.**
  - The teacher step spreads it.
  - The office's queues show the oldest first, with counts on the Library
    office home.
  - A setting caps posts per student per week (default 3) so a queue
    cannot flood.
- **A live page changing unchecked.** Drafts for posts and pages; the live
  row changes only in the approving action. Tests prove an unapproved
  change never reaches the public page.
- **Theme code moving domains** (BL3). A move with the Bookstore's tests
  rerun; no behaviour change.
- **The catch-all route.** `library/blogs…` registered before
  `library/{slug}`; a slug `blogs` refused for items.

## 10. Architecture and quality (CLAUDE.md rules)

- **Rule 3:** the Library reads People and Academics only through actions.
  - New: `People\Actions\ResolveStudentAgeForUserAction` (age from
    `date_of_birth`).
  - New: `People\Actions\ListConsentingGuardiansAction`.
  - New: `Academics\Actions\ResolveClassTeacherForStudentAction`.

  It credits a parent's wallet only through `Commerce\Actions\CreditWalletAction`.
- **Rule 5:** thin controllers; every decision is an action.
- **Rule 11:**
  - one ledger (`writer_earnings`);
  - one wallet (Commerce);
  - one review trail (`library_item_reviews`);
  - one consent record (People).
- **Rule 12:**
  - tips follow the webhook;
  - ledgers append-only;
  - no discount on a tip.
- **ADR-005:** morph aliases in the slice that adds the column.
- **Conventions:**
  - Inertia + React for every signed-in screen; public pages Blade as the
    Library's are, in the baseline;
  - EN, DV and AR for every string, right to left where it should be;
  - CSV export on every list;
  - a `blog` phrase book.

## 11. Slices, in order (one PR each, walked in a browser)

Each slice: code + tests + arch green + walk + STATUS entry + BACKLOG row
touched + no files outside its scope.

### BL1 — Student blogs can be started, with a parent's consent

> **Built 2026-10-17 (STATUS §5se).** Two departures, by the code as it
> stands:
> - The parent's photo choice is the blog's own (`show_photo` on the
>   profile), not the school's `photo_media_use` consent, which covers the
>   school's media.
> - *My blog* (`/my-blog`) is in the student's and the course learner's
>   menus, not on My account, which is only the home of someone with no
>   other workspace.
>
> Until BL2 and BL3 the writer portal refuses a student's draft, page
> change and bank details.

- `writer_profiles.kind` and the student columns.
- `blog_requests`, *Start a blog*.
- The parent's consent in the family portal (`blog_publishing`; photo and
  school choices).
- The office's request list. Approval grants `writer` and makes the
  profile.
- `ResolveStudentAgeForUserAction`, `ListConsentingGuardiansAction`.
- Tests:
  - no approval under 18 without a verified guardian's consent;
  - the parent's choices respected;
  - a full writer's application unchanged.
- Walk `blog-start.mjs`:
  - a pupil asks under `/dv`;
  - the parent consents in the portal;
  - the office approves;
  - the pupil sees *My blog*.

### BL2 — Posts, checked, and the blog pages

> **Split in two, 2026-10-17.** **BL2a is built** (STATUS §5sf): posts and
> series on *My blog*, *own work* and the weekly cap, the class teacher's
> queue (`/teach/blog-posts`, under *Teaching*), the office's step through
> the existing Library office queue, publishing, and posts kept off the
> book shelf. **BL2b is built** (STATUS §5sg): `/library/blogs`, the blog
> layout of the author page (pinned, latest, series, then published works),
> *part n of m* with previous and next on the post page, *Blogs* in the
> Digital Library menu and footer, posts in the site search, the blogs'
> front in the sitemap, the shelf's own `library/…` words refused as slugs,
> and the walk `blog-posts.mjs` extended to the blog pages. **BL2c is built**
> (STATUS §5sh): an edit to a published post kept beside the live text
> (`pending_title`, `pending_body`) and checked, as a new post is, before it
> replaces it (D3). Series and language stay as published; the pin changes
> at once, being the writer's page rather than the text.
>
> Two departures in BL2a, both deliberate:
> - *New post* is on *My blog* (`/my-blog`), for students and full writers
>   alike, not in `/write`: one screen for a blog, and the writer portal
>   stays the books' and articles'. A full writer's menu gains *My blog*.
> - Topic tags and *comments on or off* wait for BL2b and BL4.
- `content_type = post`; series and pinning.
- *New post* in `/write`.
- The teacher queue (`ResolveClassTeacherForStudentAction`) and the
  office's posts list.
- Edits to a published post as drafts.
- `/library/blogs`, the blog layout of the author page, the post page with
  series navigation.
- *Blogs* in the menu and the search; the sitemap.
- Tests:
  - the review order (teacher, then office; office alone without a class
    teacher);
  - nothing public before approval;
  - a live post unchanged until its edit is approved;
  - the catch-all route;
  - three languages.
- Walk `blog-posts.mjs`:
  - a student writes a two-part series;
  - the teacher approves part 1 and sends part 2 back;
  - the office publishes;
  - the blog shows part 1 only, under `/ar` right to left.

### BL3 — The page designer

> **Split in two, 2026-10-18.** **BL3a is built** (STATUS §5si): the gallery
> (`library_blog_themes`, five themes), the designer at `/my-blog/design`
> (theme, accent checked for contrast, heading font, tagline, *about me*,
> languages, sections on or off and in order), the preview left to right and
> right to left, the whole set as a draft sent to the office, the office's
> *Blog page changes* list with its CSV, and the author page drawn from the
> approved design. **BL3b is built** (STATUS §5sj): the banner and the
> picture, uploaded as private media into the same draft and copied to public
> media only on approval; the links (up to five, https, the writer portal's
> sites); a student's picture only with the parent's photo choice; links on
> an under-18 student's blog only with a new parent consent, `blog_links`,
> which the parent gives or stops on the child's blog page in the family
> portal, and which is asked fresh wherever links show.
>
> Not done, deliberately: the banner is shown at 4:1 by the page (`object-cover`)
> rather than cropped on upload — the file is kept as sent, shrunk to 1,600
> pixels.
>
> Three departures, deliberate:
> - Only `Contrast` moved to `App\Support\Theme\`. The Bookstore's `Theme`
>   knows vendors, badges and the Bookstore's presets; moving it would carry
>   those into shared code. The blog has its own small `BlogPage` in the
>   Library, on the same contrast check. The Bookstore's 248 tests pass
>   unchanged.
> - The designer is at `/my-blog/design`, beside *My blog*, not
>   `/write/blog/design`.
> - The sections are pinned, latest, series, works and about. Topics come
>   with tags, tips with BL5.
- `Theme` and `Contrast` move to `App\Support\Theme\`; the Bookstore
  follows, with its tests rerun.
- `library_blog_themes` with five seeded themes.
- `/write/blog/design`: theme, accent, font, banner, picture, header,
  languages, sections, links.
- The preview.
- Page changes as drafts; the office's page-changes list.
- Tests:
  - a failing contrast refused;
  - a photo refused without consent;
  - links refused for a student without consent;
  - nothing live before approval;
  - the Bookstore's theme tests unchanged.
- Walk `blog-design.mjs`.

### BL4 — Follow, notices, feeds

> **Built 2026-10-18** (STATUS §5sk): `library_follows` and the cached
> `followers_count`; the follow box on a blog's page and on its posts (a
> guest is sent to sign in, a writer cannot follow themselves); *Blogs I
> follow* in My Library; a new post tells the followers and earlier readers
> once (`readers_notified_at`), by email only for followers who asked; RSS
> for each blog and for all blogs. The email goes by the reader's own choice,
> not the office's notice switches.
- `library_follows`; *Follow* and *Following* in My Library.
- New-post notices through `NotifyLibraryUserAction` (in-app; email by the
  reader's choice).
- The page's RSS feed and the blogs front page's.
- Tests: notices only to followers and earlier readers, once per post; the
  feed valid and only published posts.
- Walk `blog-follow.mjs`.

### BL5 — Comments, checked

> **Built 2026-10-18** (STATUS §5sl): `library_comments`; the comment box on
> a published post for signed-in readers; the owner's replies; *Report*,
> which takes a published comment off the page until the office decides; the
> office's *Blog comments* list (publish, refuse with a note, hide) with a
> CSV; the writer's comments switch on *My blog* with the comments listed
> there read only; no comments on an under-13's blog, nor where the record
> cannot tell the age. Readers see a commenter's first name and initial, never
> the full name. Per-post comment switches are left for later: the writer's
> switch covers the blog.
- `library_comments`; the comment box, replies and *Report*.
- The office's comments list (publish, refuse with a note, hide).
- Comments forced off under 13.
- Tests:
  - nothing shows before approval;
  - a reported comment is hidden until decided;
  - no comment box on an under-13's blog;
  - three languages.
- Walk `blog-comments.mjs`.

### BL6 — Tips

> **Split in two, 2026-10-18.** **BL6a is built** (STATUS §5sm):
> `library_tips`; `writer_earnings.kind` (and `library_tip_id`); the tip box
> on a blog's page and posts, the office's preset amounts, by card through
> BML (paid and earned only on the webhook) or from the wallet (at once);
> Akuru's share from the `tip_share_percent` setting; a refund reversed with
> a new tip row; the note checked by the office; *Tips* on My blog with a CSV
> and the office's *Blog tips* with a CSV. **BL6b is built** (STATUS
> §5sn): a student blogger is never paid to a bank — under 18 (or of an age
> the record cannot tell) into the consenting parent's Akuru wallet, only
> while the consent is in force; at 18 or over into their own wallet until
> BL7 makes them a writer; the payout asked for on *My blog*, marked for the
> wallet on the Library office, credited when the office marks it paid
> (`writer_payouts.method`, `wallet_user_id`). Payouts stay off until the
> owner turns them on (A9).
>
> One departure, deliberate: a refunded tip's **earning** is clawed back the
> way a refunded sale's is today (its status becomes `refunded`), not with a
> negative earning row, so earnings, maturing and payouts keep one rule. The
> tip itself is never edited: its refund is a new row.
- `library_tips`; `writer_earnings.kind`.
- The tip box, paid through the Library's payment paths.
- Earnings on the webhook, less Akuru's share.
- Refund reversal.
- The tip note, checked.
- *Tips* in My blog with a CSV; the office's tips list with a CSV.
- Tests:
  - the return URL alone grants nothing;
  - the webhook writes one tip and one earning;
  - a refund reverses with new rows;
  - the share from the setting;
  - an under-18's payout refused to a bank and paid to the consenting
    parent's wallet when payouts are on.
- Walk `blog-tips.mjs`, with the payment fake.

### BL7 — Featured posts, rewards, growing up, insights

> **Built 2026-10-18** (STATUS §5so): *Featured this month* on the blogs'
> front, set from the office's new *Blog insights*; the reward, an
> Akuru-funded `reward` earning, off by default, capped, once per post; reads
> per post on *My blog* and in the insights; the insights' totals and the age
> of each checking queue, with a CSV; the daily `library:blogs-growing-up`
> that makes an 18-year-old's blog a writer's (same profile, so page, posts,
> followers and earnings stay; the parent's part ended; both told). Also the
> writing guidelines link (§6), shown on *My blog* once the owner sets
> `LIBRARY_BLOG_GUIDELINES_URL`.
- *Featured this month* and the reward (off by default, capped).
- Reads per post for the writer.
- Blog insights on the Library office (posts, follows, tips, the queues'
  age).
- At 18 a student becomes a writer: a daily check, the parent's part
  ended, a notice to both.
- Tests: the reward is an Akuru-funded earning; the move at 18 keeps
  page, posts, followers and earnings.
- Walk `blog-featured.mjs`.

**Later, on request (not in these slices):**
- premium (paid) posts;
- custom CSS for full writers (as the Bookstore's, office-approved);
- a weekly email digest;
- a class's blog run by a teacher;
- a blog competition with a judging round;
- an app push for new posts.

## 12. What this plan reuses, and what it refuses

**Reuses:** the names in §2.

**Refuses:**
- **Custom CSS or HTML from bloggers.** A child's page must not be a place
  to run code, and the office would have to read it. Themes are data.
- **Embeds** (video, social posts).
- **Direct messages between readers and bloggers.**
- **Any contact field on a student's page.**
- **Wallet-to-wallet transfers.** BACKLOG B13 keeps them out; a tip is a
  payment to Akuru, recorded as the writer's earning, never a transfer
  between wallets.

## 13. Decisions

| # | Question | Decision | By |
|---|---|---|---|
| D1 | Inside the Library or its own block? | **Inside the Digital Library, with its own blog front** (`/library/blogs`) | owner, 2026-10-17 |
| D2 | Who blogs | **Students and full writers**, one system, a `kind` each | owner, 2026-10-17 |
| D3 | Checking | **Everything is checked before it is published**: posts, edits, pages, comments, tip notes | owner, 2026-10-17 |
| D4 | Earning | **Yes**: tips and featured-post rewards; sales as today for full writers | owner, 2026-10-17 |
| D5 | Customising the page | **Yes**, from themes, colours, sections and a banner (§5) | owner, 2026-10-17 |
| D6 | Who checks a student's post first | The class teacher, then the office; a setting turns the teacher step off | default |
| D7 | Under-18s | A verified parent's consent first; the photo and the school name are separate choices | default |
| D8 | A minor's money | Accrues to the student; paid only to the consenting parent's Akuru wallet while under 18 | default |
| D9 | A student's public name | A pen name or first name; never the full name unless the parent allows | default |
| D10 | Minimum age | None, with consent; under 13, no comments | default |
| D11 | Payouts | Off as today until the tax treatment is confirmed; wallet payouts may be allowed first | default |
| D12 | Featured-post rewards | Off by default; the office sets an amount up to a cap | default |
| D13 | Comments | Signed-in readers; every one checked; off under 13 | default |
| D14 | Page changes | Checked before they go live | default |
| D15 | Turning 18 | The student becomes a writer, keeping page, posts, followers and earnings | default |
| D16 | Reading posts | Free to everyone (`free_public`); premium posts later | default |
| D17 | Tips | MVR 5, 10, 25, 50, set by the office; Akuru keeps 10%, set by the office | default |
| D18 | Posts per student | At most 3 waiting or published per week, set by the office | default |
| D19 | Address | `/library/blogs` for the front; a blog at the author page's address | default |

A default stands until the owner changes it. The slice that first depends
on one records it in its STATUS entry.

## 14. What the owner still owns (not code)

1. **Writing guidelines** for bloggers: what may be written, what is
   refused, how comments are judged. A CMS page, seeded as a first draft by
   BL2 for the owner to rewrite.
2. **The parent consent text** for under-18s, seeded as a first draft by
   BL1.
3. **Tip amounts and Akuru's share** (D17), and whether rewards are on (D12).
4. **The tax treatment of writer earnings**, which keeps payouts off (D11,
   BACKLOG A9). It now covers students' tips too.
5. **Who in the office checks posts and comments**, and how fast. The
   queues show their age.
