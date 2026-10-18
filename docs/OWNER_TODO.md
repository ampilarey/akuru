# What the owner has to do — one checklist (2026-10-18)

Every planned slice is built and merged. What is left **cannot be done from
an agent session**: it needs the server, a password, a bank or phone
account, or the owner's own judgement. This page lists it in the order to
do it. The detail behind each item lives in `docs/OWNER_ACTIONS.md` (the
item number is given) or the plan named. Tick an item here and in that
document when it is done.

**Rules that hold throughout:**
- Keys and passwords go into production's `.env` through cPanel File
  Manager only. Never put them in a chat, an email or the repository.
- After every `.env` change, run `php artisan config:cache`.
- Never run `php artisan route:cache`.
- Never run the demo or smoke seeders on production. The seeders named
  below are the only ones meant for it.

## After every merge the agent reports — the pull

```
cd /home/akuruedu/akuru-institute && git pull origin main && composer install --no-dev --optimize-autoloader --no-interaction && php artisan migrate --force && php artisan config:cache && php artisan route:clear && php artisan view:clear && php artisan cache:clear && php artisan queue:restart
```

## A. On the server (the shell on the host), once

| ☐ | What | How | Detail |
|---|---|---|---|
| ☐ | **The scheduler cron** | The minute cron with `php artisan schedule:run` is in place (2026-10-03). Check it still runs. It drives `payments:reconcile` (a lost BML webhook), `library:blogs-growing-up` (02:15 daily) and the reminders. `storage/logs/schedule.log` should grow each minute. | OWNER_ACTIONS 2, 3 |
| ☐ | **The Library policy pages** | `php artisan db:seed --class=LibraryPolicyPagesSeeder --force` | OWNER_ACTIONS 17 |
| ☐ | **The Bookstore catalogue and policy pages** | `php artisan db:seed --class=BookshopCatalogueSeeder --force`, then `php artisan db:seed --class=BookshopPolicyPagesSeeder --force` | OWNER_ACTIONS 18 |
| ☐ | **Uploaded pictures show** | If `public/storage` is missing, run `php artisan storage:link`. Blog banners and pictures need it. | BLOG_PLAN BL3 |
| ☐ | **The leftover route cache** | `/en/catalog/courses` answered 404 once (2026-10-03). If `bootstrap/cache/routes-v7.php` exists, delete it, then run `php artisan route:clear`. | KNOWN_ISSUES, WHATS_LEFT O15 |

## B. In production's `.env`, then `php artisan config:cache`

| ☐ | What | How | Detail |
|---|---|---|---|
| ☐ | **SMS live** | Set `SMS_LIVE=true`, `SMS_USE_DHIRAAGU=true`, `DHIRAAGU_SMS_USERNAME` and `DHIRAAGU_SMS_PASSWORD`. Open `/en/sign-in`, give your own number and check the code arrives. Then set a password and sign in again; this time it should ask for the password. Each message costs Dhiraagu's rate. | OWNER_ACTIONS 24 |
| ☐ | **Rotate the exposed SMS key** | In the Dhiraagu account, replace the key that was partly exposed. Put only the new one in `.env`. | OWNER_ACTIONS 24 |
| ☐ | **The blog writing guidelines address** | First write the page (item D below). Then set `LIBRARY_BLOG_GUIDELINES_URL=/page/<its slug>`. *My blog* shows the link once this is set. | BLOG_PLAN §6, §14.1 |

BML is already done: the keys are on the server, and one real payment went
through on 2026-10-03. `BML_WEBHOOK_ALLOW_UNSIGNED` stays unset on
production.

## C. In the browser, signed in as the office

| ☐ | What | Where | Detail |
|---|---|---|---|
| ☐ | **Archive the payment test course** | Courses: *Payment test* (slug `payment-test`). Archive or delete it. | OWNER_ACTIONS 2 |
| ☐ | **A report card reaches Ready** | Generate one report card. Within a minute it should read *Ready*, not *draft*. | OWNER_ACTIONS 3 |
| ☐ | **The office's email and phone for purchase notices** | `/admin/bookshop` → *Notices*, and `/admin/library/settings`. Until they are set, the office hears only in the app. | OWNER_ACTIONS 23 |
| ☐ | **Fitrah's ID card** | Fitrah's owner uploads both sides on `/vendor` (*Verify your identity*). Check them on `/admin/bookshop` → *Identity cards*, then press **Verified**. Do the same on `/admin/library` for any writer approved before 2026-09-30. | OWNER_ACTIONS 25 |
| ☐ | **Invite Fitrah** | After reading the Vendor Agreement (D below): *Admin → Akuru Bookstore*, with the details in `docs/vendors/FITRAH.md`. Send Inaaya the one-time password the screen shows, and the sign-in address. | OWNER_ACTIONS 18 |
| ☐ | **The print desk** | `/admin/bookshop/print`: enter the real papers, the colour and black-and-white prices per sheet, the finishing, the quantity tiers, the smallest order and the lead days. Look at the five templates. Switch the desk on, then place one test order yourself: approve the proof, pay, and print it from the queue. | OWNER_ACTIONS 26 |
| ☐ | **Who checks the blogs** | Decide who in the office works the post and comment queues on `/admin/library` and how quickly. *Blog insights* shows how old each queue is. | BLOG_PLAN §14.5 |

## D. Read and rewrite (your words, not the agent's)

| ☐ | What | Where | Detail |
|---|---|---|---|
| ☐ | **The seven Library policy pages** | `/page/<slug>` in the page editor: Publishing Terms, Writer Agreement, Reader Terms, Gift Card Terms, Wallet Terms, Copyright Policy and Promotion Policy. Start with the first two, because writers agree to them. Remove the "first draft" line when each is right. | OWNER_ACTIONS 17 |
| ☐ | **The Vendor Agreement** | `/page/vendor-agreement` | OWNER_ACTIONS 18 |
| ☐ | **The blog writing guidelines** | No draft has been seeded yet. Make a new CMS page, for example slug `blog-guidelines`. It should say what may be written, what is refused, and how comments are judged. Then set its address (B above). | BLOG_PLAN §14.1 |
| ☐ | **The parent consent wording** | What a parent agrees to when a child under 18 starts a blog. It is on the family portal's consent screen, and the words are in `resources/lang/{en,dv,ar}/blog.php`. Read it, and tell the agent what to change. | BLOG_PLAN §14.2 |

## E. Numbers and switches (each is built and **off**; "not yet" is a fine answer)

| ☐ | What | Where | Detail |
|---|---|---|---|
| ☐ | **Bookstore rewards** | `/admin/bookshop` → *Rewards*: the share (default 1%), the smallest order that earns, and the most one order earns. Then **Turn rewards on**. | OWNER_ACTIONS 20 |
| ☐ | **Referral credit** | `/admin/bookshop` → *Referral credit*: the credit to each side (default MVR 25) and the smallest first order (default MVR 100). Then **Turn referral credit on**. | OWNER_ACTIONS 21 |
| ☐ | **Blog tips** | `/admin/library/settings`: the tip amounts and Akuru's share of each tip. | BLOG_PLAN D17 |
| ☐ | **Featured-post rewards** | `/admin/library/settings`: on or off, and the most one reward may be. | BLOG_PLAN D12 |

## F. Decisions only you can make

| ☐ | What | Why it waits | Detail |
|---|---|---|---|
| ☐ | **Tax treatment of writer earnings** | Writer and student payouts stay off until an accountant confirms how sales, tips and rewards are taxed. | BACKLOG A9, WHATS_LEFT D1 |
| ☐ | **A native reader for the Dhivehi and Arabic** | Every DV/AR string is a first pass. One example: the menu word `ސަލާމް` used for *Absences*. | BACKLOG A10, WHATS_LEFT D2 |
| ☐ | **The old Blade screens** | Analytics, the Qur'an progress pages, e-learning and the old layouts are still English-only. Retire them or translate them; this is a navigation decision first. | BACKLOG C21, WHATS_LEFT D3 |
| ☐ | **Branch protection on `main`** | You said "keep this for later" (2026-10-03). Settings are in `docs/BRANCH_PROTECTION.md`; it needs a repo admin. | OWNER_ACTIONS 6 |
| ☐ | **Linked separate logins** | Only if wanted, as its own track. | SIGN_IN_PLAN D7, WHATS_LEFT D4 |

## G. The proof it all works on the real host

| ☐ | What | How | Detail |
|---|---|---|---|
| ☐ | **Walk staging** | On `test.akuru.edu.mv` only, never on production: `php artisan db:seed --class=SmokeMarkerSeeder`, then `SMOKE_BASE_URL=https://test.akuru.edu.mv node scripts/smoke/all.mjs`. Paste the summary to the agent, which records it in STATUS. | OWNER_ACTIONS 7 |

## H. The agent can still build these — only if you ask (not planned)

None of these is started, and none is needed for go-live. Say which one,
and it is planned and built one slice at a time like the rest.

| What | What it would be | Detail |
|---|---|---|
| **Print products beyond name tags** | Other sheet sizes (A3), cards, stickers by the roll, photo prints. Vendors other than Akuru printing. School bulk orders through quotes and credit, with a *for a class* mode. | NAME_TAGS_PLAN §11, WHATS_LEFT §5 |
| **OCR for scanned PDFs in the Library** | Today a scanned PDF gives no text, and the uploader is told to paste it. This would add page images and text recognition. Ghostscript is already on the host, behind `PdfRasterizerInterface`; an OCR engine or service would still be needed. Two-column PDFs and reversed right-to-left text could come in the same work. | BACKLOG B1, B2 |
| **Translating the old Blade screens** | Analytics (41 strings), the Qur'an progress pages (49), e-learning (35) and the old layouts (17) are still English-only. They can be translated or retired. Decide *the old Blade screens* in F first (retire or translate); then it is built screen by screen. | BACKLOG C21, WHATS_LEFT D3 |
| **Any parked item in the backlog** | Every idea raised and set aside, with why it was parked. Open rows have no strike-through. §B13 lists what was ruled out on purpose (DOI, subscriptions, offline reading, DRM, audiobooks and so on), so nobody asks twice. | `docs/BACKLOG.md` §B, §C |
