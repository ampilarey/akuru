# The Institute's Settings, simplified — plan v1 (2026-10-15)

> The owner's brief (2026-10-15): a recommendation from another assistant
> to simplify the system admin's Settings area. The owner asked for it to
> be checked against how the system admin is set up, then for a plan, not
> the work. **Nothing here is built yet.** One slice, **ST1**, when the
> owner says go.

Written against `main` at `d35feea` (#773). Every name below was read from
the tree.

---

## 1. What is there today

The system admin (`super_admin`) works in the **Institute** workspace
(ADR-040). On a phone its bar is Home, Website, Shops, Settings, System
and Alerts. `WorkspaceMap::tabsFor('institute')` names the four middle
tabs, and six is the most a 390 px phone carries.

The **Settings** tab is the `panel_settings` group in
`app/Support/Navigation/NavigationMap.php`, and lists four unlike things:

| Item | Where it goes | What it is |
|---|---|---|
| System settings | `/admin/settings` | server status cards, a Clear caches button, an *Application info* table, and six *Quick links* |
| Library settings | `/admin/library/settings` | one long form of money rules, notices, contacts and a peer-review count |
| Bookstore settings | `/admin/bookshop#settings` | a jump to the bottom of the Bookstore office page (1,347 lines) |
| Translations | `/admin/translations` | the full translation editor |

## 2. What the recommendation got right, and what it did not

The recommendation (six items) was checked against the code on 2026-10-15.

**Kept:**
- System Settings as a health page.
- The Bookstore's settings on their own page.
- Library settings in labelled blocks.
- Finance and HR settings left as they are.
- Translations left as its own page.

**Changed, with the reason:**
1. **No "School rules" group.** The recommendation wanted Finance
   settings, HR settings and the attendance policy in the Institute's
   Settings tab.
   - ADR-040 decides against it: signing in as the system admin "shows the
     Institute and nothing of the school". A system admin who needs school
     screens gives themselves the dean's role on *Roles & access*, "by
     design, not a gap".
   - `WorkspacesTest` enforces it: the system admin's menu must not contain
     school screens.
   - The system admin *can* open those three screens (they hold
     `finance.manage`, `hr.manage` and `registers.manage`). They are
     already in the School workspace's menu, where the educational admin
     works.
   - Putting them in the Institute is a change to ADR-040, a decision of
     its own, not part of this slice (§8).
2. **No sub-headings inside the tab.** The menu and the phone tab's sheet
   can only show items that link somewhere; `NavigationMap::hrefs()` checks
   each one is a real route. Labelled groups would need a new kind of menu
   entry, drawn by both shells and their tests. Without the school group,
   two of the three remaining groups would hold one item each, so plain
   ordering does the same job (§4).
3. **The analytics screens keep a way in.** The System Settings quick links
   are the *only* links to `/analytics` and `/analytics/reports`.
   `AdminPagesAreReachableTest` checks only routes named `admin.*`, so
   removing the links would orphan them silently. They move to the System
   tab instead (§3.1).
4. **The print desk joins the Settings tab.** Its page
   (`/admin/bookshop/print`) holds the print prices, papers, layouts and
   the on/off switch. The recommendation left it out.
5. **The research-review count is not money.** It is a publishing rule, so
   it gets its own small block (§3.3).
6. **Two stale hints are fixed** while the page is open (§3.1).

## 3. The slice: ST1

### 3.1 System Settings becomes a health page

Files: `resources/js/Pages/Settings/Index.jsx`,
`app/Domains/Settings/Actions/ResolveIntegrationStatusAction.php`,
`app/Domains/Settings/Http/Controllers/Admin/SettingsController.php`.

- **Kept as they are:**
  - the four status cards (mail, SMS, BML; cache / session / queue);
  - the Clear caches button. It must still rebuild a cached configuration,
    not drop it (`ClearApplicationCachesAction`, admin audit finding 13).
- **Application info** (app name, environment, URL, PHP and Laravel
  versions) becomes one `<details>` line, *About this server*, closed by
  default.
- **Quick links removed:** the `LINKS` constant, the `links` prop and the
  card. Users, enrolments, courses and the website's pages are already in
  the Institute menu.
- **Analytics moves to the System tab:** two items in `panel_system`,
  *Analytics* (`/analytics`) and *Analytics reports*
  (`/analytics/reports`). Both are Blade screens, so they open with a full
  page load. Their retirement stays the owner's decision (WHATS_LEFT D3);
  this only keeps their way in.
- **Two stale hints fixed:**
  - The BML card's warning still says *Set .env BML_WEBHOOK_SECRET*. BML
    has had no webhook secret since 2026-09-30 (STATUS §5lw), and a
    configured API key now counts as webhook-ready, so the warning can no
    longer show. The dead branch and its two phrases go.
  - The SMS card's hint names `SMS_GATEWAY_API_KEY`. Production uses the
    Dhiraagu username and password (OWNER_ACTIONS 24), so the hint names
    either.
- **Read-only:** mail, SMS and BML show status only. Secrets stay in the
  server's `.env`; no edit fields.

### 3.2 The Bookstore's settings get their own page

Files: `resources/js/Pages/Bookshop/Admin.jsx`, a new
`resources/js/Pages/Bookshop/Settings.jsx`,
`app/Domains/Bookshop/Http/Controllers/AdminBookshopController.php`,
`routes/web_localized.php`.

- **New screen** at `GET /admin/bookshop/settings`, named
  `admin.bookshop.settings`, behind `bookshop.manage` like the office.
- **Five controls move**, as the existing components, unchanged:
  - `NoticeSwitches` (notices, office email and phone);
  - `CodSwitch` (cash on delivery);
  - `Rewards`;
  - `Referrals`;
  - `ShopOpenSwitch` (the shop open or closed, with its message).
- **The saves are untouched.** Each posts to its own route
  (`admin.bookshop.notices.save`, `.cod`, `.rewards`, `.referrals`,
  `.open`). Each returns with `back()`, so it lands on the new page with no
  change to the save code.
- **The props move with them.** `notices`, `cod_on`, `rewards`,
  `referrals` and `shop_open` leave `AdminBookshopController::index` for
  the new method.
  - `index` is baselined at 37 lines (`long_controller_methods.php`); it
    shrinks, and the baseline drops to the new length.
  - The new method stays under the 36-line rule.
- **On the office page,** `<section id="settings">` gives way to one short
  line linking to the new page. The `SectionNav` chip for it points there.
- **The anchor goes.** `/admin/bookshop#settings` was the menu's only link
  with an anchor. The anchor-restoring code in `resources/js/app.jsx` and
  the note in `BuildNavigationAction::routeFor` stay, because they are
  general. Their comments stop naming this example. `NavigationMap::path()`
  keeps its anchor handling, and `StaffScreensHaveADoorTest`'s anchor case
  moves to a made-up href.

### 3.3 Library settings in three blocks

File: `resources/js/Pages/Library/Settings.jsx`. The same form, fields,
validation and save (`PUT /admin/library/settings`). Only the layout and
the labels change.

| Block | Fields |
|---|---|
| **Money** | refund window, writer's share, minimum payout, smallest and largest gift card, gift card expiry, the payouts switch |
| **Publishing** | research reviews required |
| **Notices** | email notices, SMS notices, office email, office phone |

- Each block is a `<fieldset>` with a `<legend>`.
- Each label shrinks to a few words, for example *Refund window (days)*,
  *Writer's share (%)*, *Gift card expiry (months)*. The longer explanation
  moves under the field as a hint.
- The payouts note (off until the tax and accounting treatment is
  confirmed) stays under the switch.

### 3.4 The Settings tab, in order

`panel_settings` in `NavigationMap.php`:

1. System settings — `/admin/settings`
2. Library settings — `/admin/library/settings`
3. Bookstore settings — `/admin/bookshop/settings` (`bookshop.manage`)
4. Print desk — `/admin/bookshop/print` (`bookshop.manage`)
5. Translations — `/admin/translations`

Unchanged: no new tab, no new menu mechanism, no school screens. The print
desk stays where it is under Shops too, with its two children. A screen
may sit in two groups, as Library settings already does.

### 3.5 Not touched

- `resources/js/Pages/Finance/Settings/Index.jsx`
- `resources/js/Pages/HR/Settings/Index.jsx` — payroll, `payroll.approve`
  and `PAYROLL_ENABLED` stay as they are
- the translations editor
- every route gate
- `WorkspaceMap`

## 4. Language

Every new or changed label is a key in English, Dhivehi and Arabic, in the
book the page already reads:
- `admin`: System Settings and the Library settings;
- `nav`: the menu labels (`print_desk` and `bookshop_settings` exist);
- `shop`: the Bookstore settings page.

`TranslationParityTest`, `NoPageIsLeftInEnglishTest` and
`PhraseBooksTranslateEachPhraseTest` hold them. Every page stays right to
left under `/dv` and `/ar`.

New keys, at least:
- `admin`:
  - `system_settings_about`
  - `system_settings_sms_note` (reworded)
  - `library_settings_block_money`, `library_settings_block_publishing`,
    `library_settings_block_notices`
  - a short label and a hint for each Library field
- `nav`: `analytics`, `analytics_reports`
- `shop`:
  - `settings_title`, `settings_intro`
  - `settings_link` (the office page's line)

Removed (dead after the slice):
- `system_settings_quick_links`, `system_settings_link_*`
- `system_settings_bml_no_webhook`, `system_settings_bml_webhook_note`

## 5. Tests

**Changed:**
- `tests/Feature/Nav/WorkspacesTest.php`: the system admin's hrefs carry
  `/admin/bookshop/settings`, `/admin/bookshop/print` and `/analytics`. The
  `parts.3.sections` assertions follow the new order. Still no school
  screens.
- `tests/Feature/Admin/AdminHubTest.php`: the Institute home's Settings
  card lists the five, Bookstore settings at its new address.
- `tests/Feature/Admin/AdminPanelAuditTest.php`: the menu's hrefs.
- `tests/Feature/Nav/NavigationIsGroupedByRoleTest.php`: its anchor
  comment.
- `tests/Feature/Routes/StaffScreensHaveADoorTest.php`: the anchor case on
  a made-up href.
- `tests/Feature/Settings/IntegrationStatusBadgesTest.php`: no `links`
  prop; the BML webhook branch gone; the SMS hint names either set of keys.
- `tests/Feature/Bookshop/ShopRewardsTest.php`,
  `ShopReferralsTest.php`, `AuditFollowUpsTest.php`: they read `rewards`,
  `referrals` and `notices` from the office page; they read them from
  `admin.bookshop.settings` instead.
- `tests/Feature/Localization/LibraryOfficeSpeaksThreeLanguagesTest.php`:
  the new Library keys.

**New:** `SettingsSimplifiedTest`:
- System Settings has no quick links and an *About this server* line,
  closed;
- the five Bookstore controls on the new page, each save returning to it,
  refused to an account without `bookshop.manage`;
- the office page no longer carries them, and links to the new page;
- Library settings saves the same fields to the same URL, in three blocks;
- the Settings group's five items in order, and the analytics pair in the
  System group;
- the system admin's menu still carries no school screen.

**Unchanged and must stay green:** the Architecture suite,
`AdminPagesAreReachableTest`, and `ClearCachesKeepsConfigCachedTest`.

## 6. Walks

- `admin-mobile.mjs`, `admin.mjs`, `admin-layout.mjs`, `admin-pages.mjs`,
  `once-props.mjs`: their steps on `/admin/settings`, the Settings tab and
  `/admin/bookshop#settings` follow the new pages.
- `cod.mjs`, `followups.mjs`: they press the cash-on-delivery and notice
  switches on the office page; they press them on
  `/admin/bookshop/settings`.
- `library-office-language.mjs`: the three blocks under `/dv`.
- **New:** `settings-tab.mjs`, at phone width as the system admin:
  - the Settings tab's sheet shows the five items;
  - System Settings opens with *About this server* closed;
  - the Bookstore settings page turns cash on delivery off and on, landing
    back on itself;
  - the Library settings save under `/dv`.

## 7. Deploy

No migration. The built assets are in the commit. The standard pull
command. Nothing changes in `.env`.

## 8. Not in this slice — the owner's, if wanted

- **School settings in the Institute.** If the system admin should reach
  Finance settings, HR settings and the attendance policy from the
  Institute without taking the dean's role, ADR-040 changes first: a new
  ADR, the `WorkspacesTest` rule rewritten, then a slice.
- **Sub-headings in the phone tab's sheet.** If the Settings tab ever
  grows past six or seven items, a heading entry in the navigation map,
  drawn by both shells.
- **Retiring the analytics screens.** WHATS_LEFT D3.

## 9. Size

One PR. Five screens and the navigation map, about 40 phrases in three
languages, nine tests changed and one new, seven walks changed and one
new. Walked at phone width before merge.
