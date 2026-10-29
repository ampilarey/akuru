# What the owner has to do — one checklist (2026-10-21)

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
| ☐ | **Text recognition for scanned PDFs** (only if wanted) | Install Tesseract with its English, Dhivehi and Arabic data (`tesseract --list-langs` should show `eng`, `div` and `ara`; on a shared host ask the hosting company). Ghostscript must be set too (`DOCUMENTS_GS_PATH`). Then switch it on (E below). | STATUS §5tg |
| ☐ | **Reread the Library's PDFs once** | After the pull that brings two-column reading (2026-10-20): `php artisan library:sync-pages --all`. Books saved before are read again, column by column. | STATUS §5tr |
| ☐ | **Who reads copyright reports** | Reports that a Library work copies someone's own arrive on `/admin/library/copyright` (2026-10-20), and in the app of everyone with the Library office's permission. Decide who answers them, and how fast. Upholding one takes the work off the shelf and tells its writer. | STATUS §5ts |
| ☐ | **Backups: check one restore** | After the pull that brings backups (2026-10-29), run `php artisan akuru:backup` once, then make an empty database in cPanel and follow *Restore → Check a backup* in `docs/deployment.md`. A backup counts only once a restore has worked. | STATUS §5up |
| ☐ | **The leftover route cache** | `/en/catalog/courses` answered 404 once (2026-10-03). Since 2026-10-21 `/admin/settings` says so in red if a cached route file is on the host; *Clear all caches* removes it. If the 404 comes back, note the address and the account. | KNOWN_ISSUES, WHATS_LEFT O15, STATUS §5ul |

## B. In production's `.env`, then `php artisan config:cache`

| ☐ | What | How | Detail |
|---|---|---|---|
| ☐ | **SMS live** | Set `SMS_LIVE=true`, `SMS_USE_DHIRAAGU=true`, `DHIRAAGU_SMS_USERNAME` and `DHIRAAGU_SMS_PASSWORD`. Open `/en/sign-in`, give your own number and check the code arrives. Then set a password and sign in again; this time it should ask for the password. Each message costs Dhiraagu's rate. | OWNER_ACTIONS 24 |
| ☐ | **Backups off the server** | Make a bucket at an S3-compatible store (Backblaze B2 is the cheapest), then set `BACKUP_DISK=backups`, `BACKUP_S3_KEY`, `BACKUP_S3_SECRET`, `BACKUP_S3_BUCKET`, `BACKUP_S3_ENDPOINT` (and `BACKUP_S3_REGION`). Also set `BACKUP_PASSPHRASE` to a long passphrase and **save a copy in your password manager**: no backup opens without it. Until then the copies stay on the same server as the data, encrypted with `APP_KEY`. | docs/deployment.md, STATUS §5up |
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
| ☐ | **Other shops printing** (only if wanted) | `/admin/bookshop/print` → *Shops that print* → *Approve to print*. The shop then prices its own papers on `/vendor/print`, and customers can choose it on a design's page. | STATUS §5uh–§5uj |
| ☐ | **Library bundles** (only if wanted) | `/admin/library/bundles`: pick two or more paid works, a price below their total, and switch it on. It shows on the offers page. | STATUS §5un, ADR-046 |
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
| ☐ | **Text recognition** (off) | In `.env`: `LIBRARY_OCR_TESSERACT_PATH=` the path `which tesseract` prints, and `LIBRARY_OCR_ENABLED=true`, then `php artisan config:cache`. Upload one scanned PDF in the Library: its pages should appear within minutes. For books saved before, run `php artisan library:sync-pages`. | STATUS §5tg |
| ☐ | **Languages** (on by default) | `/admin/settings` → *Languages*: untick Dhivehi or Arabic to take it off the site until it is ready. Its addresses then open in English. Tick it again to bring it back unchanged. | STATUS §5su |

## F. Decisions only you can make

| ☐ | What | Why it waits | Detail |
|---|---|---|---|
| ☐ | **Tax treatment of writer earnings** | Writer and student payouts stay off until an accountant confirms how sales, tips and rewards are taxed. | BACKLOG A9, WHATS_LEFT D1 |
| ☐ | **A native reader for the Dhivehi and Arabic** | Every DV/AR string is a first pass. The menu's *Absences* now reads ހާޒިރުނުވުންތައް, as its page does (2026-10-20, STATUS §5tq); please confirm ސަލާމް ނޯޓް for a parent's *absence note* and ސަލާމް ބެލުން for *Review notes* — leave-note wording, kept. | BACKLOG A10, WHATS_LEFT D2 |
| ☑ | **The old Blade screens** | Decided 2026-10-20 ("do all"): **translated**, not retired; retiring stays possible. All done: analytics and the old menu (§5td), the Qur'an progress pages (§5te), e-learning (§5tf). | BACKLOG C21 |
| ☐ | **Branch protection on `main`** | You said "keep this for later" (2026-10-03). Settings are in `docs/BRANCH_PROTECTION.md`; it needs a repo admin. | OWNER_ACTIONS 6 |
| ☑ | **Linked separate logins** | Already built (E7, 2026-09-11): *My account → Linked accounts* (`/account/linked`) links a second login by its password, both ways, and switches between them. Checked 2026-10-21; the plans had listed it as missing. | SIGN_IN_PLAN D7, WHATS_LEFT D4 |
| ☑ | **Leave counted in working days** | Decided 2026-10-21 by best practice (ADR-045): weekends and the calendar's holidays are not leave. If staff contracts count calendar days, set `HR_LEAVE_COUNTS=calendar` in `.env`. | KNOWN_ISSUES, STATUS §5um |

## G. The proof it all works on the real host

| ☐ | What | How | Detail |
|---|---|---|---|
| ☐ | **Walk staging** | On `test.akuru.edu.mv` only, never on production. On the staging host, once: `php artisan db:seed --class=SmokeMarkerSeeder --force`. Then, from a computer with this repo, Node and Playwright: `SMOKE_BASE_URL=https://test.akuru.edu.mv SMOKE_I_KNOW_THIS_WRITES=yes SMOKE_RESEED_CMD='ssh <staging> "cd <app folder> && php artisan db:seed --class=SmokeMarkerSeeder --force"' node scripts/smoke/all.mjs`. About 100 of the 160 walks run there. The 58 that check their data through the local database are skipped and say so. Leave out `SMOKE_RESEED_CMD` and the 17 shop walks that need a reseed are skipped too. Also set `BOOKSHOP_BANK_ACCOUNT_NUMBER` in staging's `.env` for the checkout walk. Paste the summary to the agent, which records it in STATUS. | OWNER_ACTIONS 7, STATUS §5uf |

## H. The agent can still build these — only if you ask (not planned)

Everything planned is built, the print products (PP1–PP7) and the Library's
bundles, two-column PDFs and OCR included. What is left waits on something
only you can supply:

| What | Why it waits | Detail |
|---|---|---|
| **Stickers by the roll, folded cards** | Only if Akuru buys a roll printer, or prints both sides. | PRINT_PRODUCTS_PLAN PD4, PD5 |
| **Paid themes between shops** | Money moving from one shop to another is a decision of its own. | BOOKSHOP_PLAN, BACKLOG C7 |
| **Anything in the backlog's open rows** | Open rows have no strike-through. §B13 lists what was ruled out on purpose (DOI, subscriptions, offline reading, DRM, audiobooks and so on). | `docs/BACKLOG.md` |
