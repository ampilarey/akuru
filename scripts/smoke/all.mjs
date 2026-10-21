/**
 * Every walk, one command, one answer.
 *
 * Ten walk scripts had accumulated in this directory and **nine of them were
 * referenced nowhere an operator would look** (eleven now — `library` was
 * written against this runner rather than beside it) — not in `OPERATOR_CHECKLIST.md`,
 * not in `OWNER_ACTIONS.md`, not in any runner. `OWNER_ACTIONS` item 7, *walk
 * the app on staging*, is the gate the whole go-live path waits on, and it
 * named exactly one of them.
 *
 * So this is the entry point: `node scripts/smoke/all.mjs` runs the lot against
 * whatever `SMOKE_BASE_URL` points at and prints one table. It exits non-zero
 * if any walk fails, so it can gate a deploy.
 *
 * **About an hour** locally for all 155 (56 minutes measured on 2026-10-20,
 * STATUS §5tu). It was six and a half minutes when it held ten walks, of which
 * `page-errors` was five: it loads 265 routes as six roles. Against a real web
 * server rather than `artisan serve` it should be quicker.
 *
 * ## Read-only and writing walks are separated, on purpose
 *
 * Six of these only look at screens. The other **thirty-three write**: they build a course for a student, run a scheduled intake, set and sit an assessment, issue a certificate, sell a course for wallet money, tag an Arabic skill activity, set and mark a recitation and map a halaqa, enrol a pupil in a halaqa and approve their milestone, issue and redeem a gift card and read a protected book, set homework, post a notice, message a teacher and poll a class, send a trip sign-up with a fee and close it, publish a calendar day and mark a pupil late, double-book a teacher and be refused, record and revoke a family's consent, ask the school for a day off and be answered, enrol a
 * stranger on a course, submit and approve an absence note, request that a
 * child be collected, book a parent-teacher meeting, publish an article to
 * the library, publish an exam and a term's report cards to families, bill a
 * family and take their cash, and approve a staff member's leave and run
 * payroll where the flag allows. That is exactly what makes them worth running — and exactly why
 * nobody should discover it by pointing the runner at a live school.
 *
 *   node scripts/smoke/all.mjs            every walk (the default)
 *   node scripts/smoke/all.mjs --read     the twenty-two that only look
 *   node scripts/smoke/all.mjs --write    the 136 that change data
 *   node scripts/smoke/all.mjs learn review     just those, by name
 *
 * A writing run against a host whose name does not look synthetic asks for
 * `SMOKE_I_KNOW_THIS_WRITES=yes` before it starts. That is a speed bump, not a
 * security control — it exists because the failure it prevents is an SMS to a
 * real family about a child who is not absent.
 *
 * ## What it needs
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *
 * Every walk reads markers that seeder plants, and it can now be run twice
 * (STATUS §5dz — it could not, for most of its life). Locally, a dev server on
 * `SMOKE_BASE_URL` and a Chromium at `SMOKE_CHROMIUM` if the bundled one is not
 * where Playwright expects. Dates the walks compute are in `SMOKE_TZ`, default
 * `Indian/Maldives` — the school's day, not the walker's (STATUS §5fz).
 */
import { execFileSync, spawn } from 'node:child_process';
import { existsSync, readdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const HERE = dirname(fileURLToPath(import.meta.url));
const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';

/**
 * Find a Chromium once, here, instead of failing ten times.
 *
 * Playwright looks for a browser at a path baked in at install time, and on a
 * host where the browsers were installed separately — which is the normal
 * arrangement for a CI image or a managed runner — that path is wrong. Each
 * walk then dies with the same wall of text about running `playwright install`,
 * and the runner prints it ten times over.
 *
 * Only consulted when `SMOKE_CHROMIUM` is unset, and it says what it picked, so
 * it is a convenience and never a mystery.
 */
function findChromium() {
    const root = process.env.PLAYWRIGHT_BROWSERS_PATH;
    if (!root || !existsSync(root)) {
        return null;
    }

    for (const entry of readdirSync(root).filter((name) => name.startsWith('chromium-')).sort().reverse()) {
        const candidate = join(root, entry, 'chrome-linux', 'chrome');
        if (existsSync(candidate)) {
            return candidate;
        }
    }

    return null;
}

if (!process.env.SMOKE_CHROMIUM) {
    const found = findChromium();
    if (found) {
        process.env.SMOKE_CHROMIUM = found;
        console.log(`Using ${found} (set SMOKE_CHROMIUM to override).`);
    }
}

/**
 * `writes` is the honest bit: does running this change data on the host?
 *
 * `page-errors` signs in as six roles and loads 265 screens; `sweep` and
 * `own-data` read planted markers. Everything else fills in a form somebody
 * else will act on.
 */
const WALKS = [
    { name: 'page-errors', writes: false, asks: 'Does every screen survive being looked at?' },
    { name: 'sweep', writes: false, asks: 'Does each screen show the row planted for it?' },
    { name: 'own-data', writes: false, asks: 'Does a family see their own child, and only their own?' },
    { name: 'create-sweep', writes: true, asks: 'Can a person make one of these records, through the form?' },
    { name: 'register', writes: true, asks: 'Can a stranger enrol themselves?' },
    { name: 'author', writes: true, asks: 'Can a person build a course and get it in front of a student?' },
    { name: 'intake', writes: true, asks: 'Can the office run a scheduled intake, and can a student get a seat in it?' },
    { name: 'learn', writes: true, asks: 'Can a student take a lesson?' },
    { name: 'assess', writes: true, asks: 'Does a question a teacher writes become a mark a student gets?' },
    { name: 'certify', writes: true, asks: 'Does a student who finishes a course get a certificate a stranger can verify?' },
    { name: 'buy', writes: true, asks: 'Can a student buy a course, and does paying open it?' },
    { name: 'arabic', writes: true, asks: 'Does an Arabic skill activity, tagged to a letter, end up on the skill reports?' },
    { name: 'quran', writes: true, asks: 'Does a recitation range reach the student as a passage and come back marked, and does a halaqa map onto an offering?' },
    { name: 'hifz', writes: true, asks: 'Does what survives of the Hifz app still work for the people who use it?' },
    { name: 'reader', writes: true, asks: 'Can a reader read a protected book and keep their place, and does a gift card become wallet money?' },
    { name: 'cms-courses', writes: true, asks: 'Can the office put a course on the website from the CMS alone — a category, the form, a cover, Publish?' },
    { name: 'id-scan', writes: false, asks: 'Does a photo of an ID card or passport fill in the registration form, read in the browser and never overwriting what was typed?' },
    { name: 'family', writes: true, asks: 'Does the daily habit work — homework, the noticeboard, a message and its reply, a class poll?' },
    { name: 'signup', writes: true, asks: 'Does a sign-up sheet with a fee reach one class, wait for the parent, bill the family, and close?' },
    { name: 'school-day', writes: true, asks: 'Does a late mark reach the office\'s lists and the family, and does the calendar show families what is theirs?' },
    { name: 'timetable', writes: true, asks: 'Does the timetable refuse a teacher in two places at once, and let the office overrule it with a reason?' },
    { name: 'consent', writes: true, asks: 'Can the office record what a family agreed to, and does revoking it close the public gate?' },
    { name: 'requests', writes: true, asks: 'Can a family ask the school for something and get an answer?' },
    { name: 'review', writes: true, asks: 'Does work a machine cannot mark come back marked?' },
    { name: 'rubric', writes: true, asks: 'Does a rubric a teacher builds mark a piece of work, criterion by criterion, and show the learner how?' },
    { name: 'course-copy', writes: true, asks: 'Can a teacher copy a whole course into a new draft, every module, lesson and block?' },
    { name: 'forum', writes: true, asks: 'Can a class talk about its course, and can its teacher answer, pin, lock and hide?' },
    { name: 'absence', writes: true, asks: 'Does the register believe a family who reported an absence?' },
    { name: 'pickup', writes: true, asks: 'Does a child get handed over, and only to the adult who asked?' },
    { name: 'meetings', writes: true, asks: 'Does a teacher find out who booked a meeting with them?' },
    { name: 'library', writes: true, asks: 'Can somebody become a writer and get something published?' },
    { name: 'peer-review', writes: true, asks: 'Does the research peer-review gate refuse, and can it be satisfied?' },
    { name: 'earnings', writes: true, asks: 'Does a sale become the writer\'s money, and does the payout gate say why not?' },
    { name: 'money', writes: true, asks: 'Can the school take money without a gateway, and give it back?' },
    { name: 'pronounce', writes: true, asks: 'Can a student hand in a recording, and does a human ear decide it?' },
    { name: 'recite', writes: true, asks: 'Can a student hand in a recitation, and does a teacher mark it?' },
    { name: 'exams', writes: true, asks: 'Does a mark a teacher types end up on a report card a family can open?' },
    { name: 'fees', writes: true, asks: 'Does a fee become an invoice a family can see, and cash a receipt they can open?' },
    { name: 'hr', writes: true, asks: 'Does a staff member\'s month — check-in, leave, appraisal, payslip — go through?' },
    { name: 'gate', writes: true, asks: 'Does a pupil\'s gate card get them recorded, by scanner and by camera, and does the family see it?' },
    { name: 'mobile', writes: false, asks: 'Does this work on a phone?' },
    { name: 'nav', writes: false, asks: 'Can each kind of person find their way, without reading a wall of links?' },
    { name: 'website', writes: false, asks: 'Does the website lead with the four products, on a desk and on a phone?' },
    { name: 'news', writes: true, asks: 'Can the office write the news, and does it reach /news and the home page?' },
    { name: 'events', writes: true, asks: 'Do the events and the gallery open from their own lists, and can a visitor register?' },
    { name: 'identity', writes: false, asks: 'Does each person see only their own workspace — a vendor their shop, a parent their children, nobody the rest?' },
    { name: 'two-factor', writes: true, asks: 'Can a person turn on two-step sign-in, and does a password alone then stop at the code?' },
    { name: 'password-reset', writes: true, asks: 'Does a password reset code reset only the account it was sent for — on both reset flows, and across them?' },
    { name: 'signin-language', writes: true, asks: 'Do signing in and resetting a password read in Dhivehi and Arabic, and does an account made by a code get a code from the checkout?' },
    { name: 'payment-language', writes: true, asks: 'Do the payment pages read in Dhivehi and Arabic — the checkout, back from the bank, a receipt, a lost reference — and does each payment go back to what it was for?' },
    { name: 'website-language', writes: true, asks: "Do the website's forms and its office say their messages in Dhivehi, and once — a waiting list, the contact form, an event, the reminders' subscription, the daily content, news and the funnel?" },
    { name: 'flash-once', writes: true, asks: 'Does each Bookstore page say its thanks once — the cart, and the wishlist from a product\'s page — in the layout\'s flash?' },
    { name: 'substitutions-language', writes: true, asks: 'Do the substitution requests and teacher absences work and read in Dhivehi — a change saved, a lesson taken, a substitute assigned, an absence recorded?' },
    { name: 'upload-language', writes: true, asks: 'Is an upload refused in the page\'s language — a PDF given to an image block on the course outline, in Dhivehi and Arabic — and an image still taken?' },
    { name: 'documents-language-cards', writes: true, asks: 'Are the student ID card and the transfer certificate written in the language of the page that asks — Dhivehi, Arabic, and still English under /en?' },
    { name: 'offices-language', writes: true, asks: 'Does the feature walk-through refuse a broken mark without a note in Dhivehi and Arabic, recording nothing, and still record a working one?' },
    { name: 'prayer-broadcast-locked', writes: true, asks: 'Is a sent prayer broadcast locked — no Preview, no Confirm, Save off — and said so in Dhivehi, while a draft can still be previewed?' },
    { name: 'print-office', writes: true, asks: 'Can the Bookstore manager price a paper, keep a subject set and try the print desk\'s switch, in Dhivehi?' },
    { name: 'print-templates', writes: true, asks: 'Can the Bookstore manager make a name-tag template in the designer — a Thaana name field, dragged, saved, a sample sheet, active, retired — in Dhivehi?' },
    { name: 'print-design', writes: true, asks: 'Can a customer make name tags from a template — a Thaana name, glossy colour, two sheets, the server\'s price — see a watermarked proof, approve it, and find it locked, in Dhivehi?' },
    { name: 'print-order', writes: true, asks: 'Can a customer buy an approved name-tag design — sheets locked, Akuru\'s two deliveries, the wallet — and can the office print and finish it from the queue until the order reads ready, in Arabic?' },
    { name: 'print-upload', writes: true, asks: 'Can a customer upload their own name-tag artwork — a 72-dpi picture refused and a 200-dpi one warned in Dhivehi, a 300-dpi one passing — and, with the declaration ticked, see Chrome\'s proof?' },
    { name: 'print-sizes', writes: true, asks: 'Can the office set up an A3 paper and layout, and does a job moved to that layout offer only the A3 paper — on a phone, in Dhivehi and English?' },
    { name: 'print-products', writes: true, asks: 'Can the office put Cards on sale beside name tags — refused Stickers without a layout, in Dhivehi — and does a customer find Cards on the print page and lose it when it comes off sale, on a phone?' },
    { name: 'print-cards', writes: true, asks: 'Can a customer find Akuru\'s five card designs and start a business card on a phone, asked for name, role, phone and email in Dhivehi, and see it priced?' },
    { name: 'print-stickers', writes: true, asks: 'Does a customer find Akuru\'s sticker designs, the round ones drawn round, and start a round name sticker on a phone in Dhivehi, priced from the sticker sheet?' },
    { name: 'print-photos', writes: true, asks: 'Can a customer upload a photo on a phone, see it judged at photo resolution, choose fill or fit, and have two 4 × 6 prints counted and priced, in Dhivehi?' },
    { name: 'print-class', writes: true, asks: 'Can a teacher fill a name-tag list from their own class on a phone in Dhivehi — one tag per pupil, with the class — while a parent gets no such choice and is refused the names?' },
    { name: 'print-quote', writes: true, asks: 'Can a school ask Akuru for a price on its name tags from the basket, the office price them under the print desk, and the school pay the quoted price — on a phone, in Dhivehi?' },
    { name: 'print-freeform', writes: true, asks: 'Can a customer design name tags freely in Arabic — a text and a rectangle drawn, Arabic typed, turned 12°, kept on reload — and see Chrome\'s proof of it?' },
    { name: 'print-upsell', writes: true, asks: 'Does a school book list offer the child\'s name tags in Dhivehi, with the grade\'s subjects chosen, can a design be ordered again at today\'s price, and does the office see print sales by paper and month?' },
    { name: 'pick-by-name', writes: true, asks: 'Does the office choose the pupil, the teacher and the account by name — on the behaviour, plan, fee adjustment and wallet credit forms — instead of typing an id?' },
    { name: 'blog-start', writes: true, asks: 'Can a student ask to start a blog in Dhivehi, a parent agree from My children, and the office approve — with the pupil\'s real name beside the pen name — so the student sees their blog?' },
    { name: 'blog-posts', writes: true, asks: 'Can a student write a two-part series in Dhivehi, their class teacher approve part 1 and send part 2 back with a note, and the office publish part 1 — readable under /ar, with part 2 not public and posts off the book shelf?' },
    { name: 'blog-design', writes: true, asks: 'Can a blogger design their page in Dhivehi — a theme, an accent checked for contrast, a tagline, the sections in their order — and does readers\' page change only once the office approves?' },
    { name: 'blog-follow', writes: true, asks: 'Can a reader follow a blog in Dhivehi, hear of its next post when the office publishes it, read it in the blog\'s RSS feed, and stop following from My Library?' },
    { name: 'blog-comments', writes: true, asks: 'Is a blog comment checked by the office before it shows, can the writer reply (checked too), does a report take it down, and is an under-13\'s blog closed to comments?' },
    { name: 'blog-tips', writes: true, asks: 'Can a reader tip a blog\'s writer from the wallet in Dhivehi (a card tip going to the bank or saying it could not start, granting nothing), does the writer see their share, and does the note reach them only once the office publishes it?' },
    { name: 'blog-payout', writes: true, asks: 'Is an under-18 blogger\'s payout, asked for on My blog in Dhivehi, paid by the office into the consenting parent\'s Akuru wallet and never a bank?' },
    { name: 'blog-featured', writes: true, asks: 'Can the office feature a blog post this month with an Akuru-funded reward, shown on the blogs\' front under /ar, does the writer see their readers, and does a student\'s blog become a writer\'s at 18?' },
    { name: 'language-switch', writes: true, asks: 'Can the office switch Arabic off on System Settings, so /ar addresses open in English and no menu offers it, and switch it back on?' },
    { name: 'phone-first', writes: false, asks: 'Is every public page phone-first: nothing scrolls sideways in English or Dhivehi, and what a page is for starts on the first phone screen?' },
    { name: 'public-rtl', writes: false, asks: 'Does the website read right to left in Dhivehi and Arabic, on a desk and on a phone?' },
    { name: 'rtl', writes: false, asks: 'Do English sentences read right on a Dhivehi or Arabic page, without the page\'s alignment changing?' },
    { name: 'course-screens-language', writes: true, asks: 'Do the screens teachers build courses on — and the Qur\'an screens, a learner\'s among them — read in Dhivehi and Arabic, with a name on every field?' },
    { name: 'middle-name', writes: true, asks: 'Is a student called by the whole name — first, middle and last — in the directory, its search and CSV, the class roster and a parent\'s list?' },
    { name: 'saved-in-language', writes: true, asks: 'Does a save on a Dhivehi or Arabic page say what it saved in that language, and come back to it?' },
    { name: 'writer-language', writes: true, asks: 'Do the Library\'s writer portal and peer review read in Dhivehi and Arabic, and is a refused submission said on its own row?' },
    { name: 'library-office-language', writes: true, asks: 'Does the Library office read in Dhivehi and Arabic, and is a refused assignment said on its own row?' },
    { name: 'front-door-language', writes: true, asks: 'Do the header, footer, home page, error pages, admissions and contact read in Dhivehi and Arabic, and is an applicant on the Dhivehi page thanked in Dhivehi?' },
    { name: 'course-pages-language', writes: false, asks: 'Do the course catalogue and a course page read in Dhivehi and Arabic — statuses, levels, durations, seat labels and questions included — and does a filter come back in the page\'s language?' },
    { name: 'site-pages-language', writes: true, asks: 'Do the about, events, news, gallery, careers, achievements, search and CMS pages read in Dhivehi and Arabic, and is an event registration confirmed — and a second one refused — in Dhivehi?' },
    { name: 'daily-certificate-language', writes: true, asks: 'Do the daily reminders\' archive, a day\'s page and the subscription read in Dhivehi and Arabic, is a subscription saved and paused in Dhivehi, and does the certificate check answer in the scanning browser\'s language?' },
    { name: 'library-public-language', writes: true, asks: 'Do the Library\'s shelf, an item, an author, the offers, the reader, My Library, the wallet and gift cards read in Dhivehi and Arabic, and are a bad gift card code, a bad discount code and a gift card with nowhere to go refused in Dhivehi?' },
    { name: 'wallet-refusal', writes: true, asks: 'Is a wallet payment the balance cannot cover refused beside the button, with the wallet untouched and no purchase left behind in My Library?' },
    { name: 'bare-addresses', writes: true, asks: 'Do a bare /vendor, the Vendor Agreement, the portal\'s Home link and its product search reach the app, and is /robots.txt the app\'s?' },
    { name: 'abandoned-code', writes: true, asks: 'When a card payment cannot start, does My Library say so, and is the once-per-reader code still good for the next try?' },
    { name: 'library-categories', writes: true, asks: 'Can the Library office name a category in Dhivehi and Arabic and rename it, and does the shelf say the name in each language?' },
    { name: 'documents-language', writes: true, asks: 'Does a report card the office makes in Dhivehi or Arabic, and a transcript a family asks for, read in that language — no placeholder heading left?' },
    { name: 'pay-buttons', writes: true, asks: 'Do Pay on a family\'s fees and Enroll on a paid course take the browser to the bank, asking it for the right amount?' },
    { name: 'portal-language', writes: true, asks: 'Do the family\'s and the staff\'s own portal pages read in Dhivehi and Arabic, codes named, and are a sent message, a refused reply, a refused Pay and a too-obvious PIN said in Dhivehi?' },
    { name: 'office-language', writes: true, asks: 'Do the office\'s registers and attendance screens read in Dhivehi and Arabic, codes named, and are generating registers and a reason the school already has said in Dhivehi?' },
    // WA2 (STATUS §5tu): the walks written beside this runner and never added to it:
    // the Bookstore, the admin panel, the language sweeps. Read-only ones first, then
    // the Bookstore in the order it was built, so each finds what the one before left.
    { name: 'admin-pages', writes: false, asks: 'Does every admin page draw, desktop and phone?' },
    { name: 'admin-mobile', writes: false, asks: 'Does every admin landing fit a 390 px phone?' },
    { name: 'admin-layout', writes: false, asks: 'Do the admin panel\'s shells work for the office, on a phone and a desktop?' },
    { name: 'long-pages', writes: false, asks: 'Are the four long admin pages short on a phone?' },
    { name: 'phone-targets', writes: false, asks: 'Is every tap target in the admin panel big enough for a thumb?' },
    { name: 'once-props', writes: false, asks: 'Do the phrase books travel once, not on every visit?' },
    { name: 'fonts', writes: false, asks: 'Are the fonts self-hosted, with no third party asked for a face?' },
    { name: 'staff-phone', writes: false, asks: 'Does every page a teacher or the headmaster opens work on a phone, in Dhivehi and Arabic?' },
    { name: 'household', writes: false, asks: 'Does every page a family opens work on a phone, in Dhivehi and Arabic?' },
    { name: 'admin-hub', writes: false, asks: 'Do the School office and the Institute open on today\'s numbers, then the workspace in parts?' },
    { name: 'admin', writes: true, asks: 'Can the two people who run the admin panel reach what they run, and nothing else?' },
    { name: 'workspaces', writes: true, asks: 'Does a person with three workspaces switch between them, one job at a time?' },
    { name: 'logout', writes: true, asks: 'Does logging out of the shell leave the shell?' },
    { name: 'vendor', writes: true, reseed: true, asks: 'Can the office invite a vendor, and the vendor open its shop and list a product?' },
    { name: 'insights', writes: true, reseed: true, asks: 'Does a shop see how visitors move through it, and does search find things?' },
    { name: 'shop', writes: true, reseed: true, asks: 'Can anyone find Fitrah\'s products in the Akuru Bookstore?' },
    { name: 'checkout', writes: true, asks: 'Can a customer buy from two shops in one basket, and pay by bank transfer?' },
    { name: 'fulfilment', writes: true, reseed: true, asks: 'Can a shop send an order, and a customer track, return, cancel and talk to the shop?' },
    { name: 'storefront', writes: true, asks: 'Can a vendor make its page look like its brand, inside the Akuru frame?' },
    { name: 'storefront-colours', writes: true, reseed: true, asks: 'Do a shop\'s own colours reach its live page?' },
    { name: 'sections', writes: true, asks: 'Can a vendor arrange its page from sections, and the office moderate it?' },
    { name: 'vendor-money', writes: true, asks: 'Does a vendor see what it is owed, and is it paid?' },
    { name: 'polish', writes: true, reseed: true, asks: 'Can a customer find, save, trust and be nudged in the shop?' },
    { name: 'operations', writes: true, reseed: true, asks: 'Can a shop run its stock and products in bulk, and the office set how notices travel?' },
    { name: 'apply', writes: true, asks: 'Can someone apply to open a shop, and the office open it for them?' },
    { name: 'cod', writes: true, reseed: true, asks: 'Can a customer pay a shop in cash on delivery, and does the money come out right?' },
    { name: 'newsletter', writes: true, reseed: true, asks: 'Can a shop collect newsletter sign-ups with consent, and is a forgotten cart remembered?' },
    { name: 'quotes', writes: true, reseed: true, asks: 'Can a school buying in bulk get a shop\'s price, and does the checkout charge it?' },
    { name: 'hosts', writes: true, asks: 'Can a shop use its own domain?' },
    { name: 'team', writes: true, asks: 'Can the office have Bookstore admins who run the Bookstore and nothing else?' },
    { name: 'css', writes: true, asks: 'Can a shop add its own CSS, safely, with the office\'s say-so?' },
    { name: 'gallery', writes: true, reseed: true, asks: 'Can a shop pick a whole look from a gallery, and offer its own?' },
    { name: 'followups', writes: true, reseed: true, asks: 'Can each of the Bookstore audit\'s seven follow-ups be done in a browser?' },
    { name: 'vendor-mobile', writes: true, asks: 'Does the vendor portal work on a phone?' },
    { name: 'customer-sign-in', writes: true, asks: 'Can a customer sign in on their phone number?' },
    { name: 'akuru', writes: true, reseed: true, asks: 'Can Akuru pack and deliver for a shop, and charge for it?' },
    { name: 'campaigns', writes: true, reseed: true, asks: 'Can the office send an SMS offer to those who asked, and can a customer stop it?' },
    { name: 'customers', writes: true, reseed: true, asks: 'Can the office see a customer, tag them, keep a note and tick off a follow-up?' },
    { name: 'credit', writes: true, reseed: true, asks: 'Can a school pay on a credit account, and the office record what it paid?' },
    { name: 'preorder', writes: true, reseed: true, asks: 'Can a customer pre-order a book that is not out yet, and hear when it is?' },
    { name: 'used-books', writes: true, asks: 'Can a shop sell old and used books from the short form?' },
    { name: 'lending', writes: true, asks: 'Can people lend books to each other, checked by the office?' },
    { name: 'gift', writes: true, asks: 'Can a signed-in reader find the Library and buy someone a gift card?' },
    { name: 'notice-language', writes: true, asks: 'Does a notice read in the family\'s language, whoever sent it?' },
    { name: 'copyright', writes: true, asks: 'Can a reader report a Library work that copies theirs, and the office decide it?' },
    { name: 'account-language', writes: true, asks: 'Do linked accounts and a teacher\'s schedule read in Dhivehi and Arabic?' },
    { name: 'bookstore-language', writes: true, reseed: true, asks: 'Does the Bookstore read in Dhivehi and Arabic, to its last words?' },
    { name: 'commerce-language', writes: true, asks: 'Does the Commerce office read in Dhivehi and Arabic?' },
    { name: 'exams-language', writes: true, asks: 'Do the office\'s exams and grades screens read in Dhivehi and Arabic?' },
    { name: 'finance-language', writes: true, asks: 'Do the office\'s finance screens read in Dhivehi and Arabic?' },
    { name: 'hr-language', writes: true, asks: 'Do the office\'s HR screens read in Dhivehi and Arabic?' },
    { name: 'library-desk-language', writes: true, asks: 'Does the library desk read in Dhivehi and Arabic?' },
    { name: 'people-language', writes: true, asks: 'Do the office\'s people screens read in Dhivehi and Arabic?' },
    { name: 'promotions-language', writes: true, asks: 'Do the Library\'s promotion campaigns read in Dhivehi and Arabic?' },
    { name: 'register-language', writes: true, asks: 'Does the course registration read in Dhivehi and Arabic?' },
    { name: 'system-language', writes: true, asks: 'Do the system screens read in Dhivehi and Arabic?' },
];

const args = process.argv.slice(2);
const named = args.filter((arg) => !arg.startsWith('--'));
const onlyRead = args.includes('--read');
const onlyWrite = args.includes('--write');

let selected = WALKS;
if (named.length > 0) {
    selected = WALKS.filter((walk) => named.includes(walk.name));
    const unknown = named.filter((name) => !WALKS.some((walk) => walk.name === name));
    if (unknown.length > 0) {
        console.error(`Unknown walk(s): ${unknown.join(', ')}`);
        console.error(`Known: ${WALKS.map((walk) => walk.name).join(', ')}`);
        process.exit(2);
    }
} else if (onlyRead) {
    selected = WALKS.filter((walk) => !walk.writes);
} else if (onlyWrite) {
    selected = WALKS.filter((walk) => walk.writes);
}

// A hostname nobody could mistake for a school's real address. Deliberately
// generous — the point is to catch `akuru.edu.mv`, not to be clever.
const looksSynthetic = /localhost|127\.0\.0\.1|\btest\.|\bstaging\.|\bdev\./i.test(BASE);

if (selected.some((walk) => walk.writes) && !looksSynthetic && process.env.SMOKE_I_KNOW_THIS_WRITES !== 'yes') {
    console.error(`\nRefusing to run writing walks against ${BASE}.`);
    console.error('These walks submit absence notes, request that children be collected,');
    console.error('book meetings, enrol people, hand in recordings, publish exam results, issue invoices and approve leave. On a host with');
    console.error('real families on it that means real messages to real people.\n');
    console.error('If this host is synthetic, re-run with SMOKE_I_KNOW_THIS_WRITES=yes,');
    console.error('or use --read for the seven walks that only look at screens.\n');
    process.exit(2);
}

/**
 * One line per walk, from walks that do not agree on what a line looks like.
 *
 * The ten scripts were written at different times and end **five different
 * ways**: six say "N/M steps passed", `sweep` says "N/M screens showed what was
 * planted for them", `create-sweep` says "N/M screens created a record through
 * their own form", and `page-errors` and `own-data` print a sentence with no
 * score in it at all ("No runtime or server errors for any role.").
 *
 * The first version of this runner matched only the first phrasing and printed
 * a bare `—` for four walks that had answered perfectly well. Rather than
 * rewrite ten working scripts to satisfy the summary, the summary reads what
 * they actually say: a count where there is one, and otherwise their own last
 * sentence, which is the line each was written to end on.
 */
function summarise(out) {
    const count = out.match(/(\d+)\/(\d+) (?:steps passed|screens )/);
    if (count) {
        return `${count[1]}/${count[2]}`;
    }

    const lines = out.split('\n').map((line) => line.trim()).filter(Boolean);
    const last = lines[lines.length - 1] ?? '';

    return last.length > 46 ? `${last.slice(0, 45)}…` : (last || '—');
}

function run(walk) {
    return new Promise((resolve) => {
        const started = Date.now();
        const child = spawn(process.execPath, [join(HERE, `${walk.name}.mjs`)], {
            env: process.env,
            stdio: ['ignore', 'pipe', 'pipe'],
        });

        let out = '';
        child.stdout.on('data', (chunk) => { out += chunk; });
        child.stderr.on('data', (chunk) => { out += chunk; });

        child.on('close', (code) => {
            resolve({
                walk,
                code,
                out,
                score: summarise(out),
                seconds: Math.round((Date.now() - started) / 1000),
            });
        });
    });
}

console.log(`\nWalking ${BASE} — ${selected.length} of ${WALKS.length} walks.\n`);

const results = [];

// One at a time. They share a database and several of them sign in as the same
// people; running them in parallel would have them stepping on each other's
// fixtures, which is how a walk reports a failure that belongs to its neighbour.
//
// A walk marked `reseed` needs the Bookstore as the seeder leaves it: an empty
// basket, a topped-up wallet, the stock and the shop page as planted. The shop
// walks before it spend, stock and publish, so on a local server the seeder
// runs again first (it runs twice, and in under a second: STATUS §5tu). On any
// other host run `php artisan db:seed --class=SmokeMarkerSeeder` there before
// each of them, or run them by name one at a time.
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
for (const walk of selected) {
    process.stdout.write(`  ${walk.name.padEnd(13)} … `);
    if (walk.reseed && LOCAL) {
        execFileSync('php', ['artisan', 'db:seed', '--class=SmokeMarkerSeeder', '--force', '-q'], { cwd: join(HERE, '..', '..'), stdio: 'ignore' });
    }
    const result = await run(walk);
    results.push(result);
    console.log(`${result.code === 0 ? "ok  " : "FAIL"}  ${result.score.padEnd(46)}  ${result.seconds}s`);
}

const failed = results.filter((result) => result.code !== 0);

// Full output for the failures only. A passing run should be readable at a
// glance; a failing one should not need a second command to explain itself.
for (const result of failed) {
    console.log(`\n${'─'.repeat(72)}\n${result.walk.name} — ${result.walk.asks}\n`);
    console.log(result.out.trimEnd());
}

console.log(`\n${'═'.repeat(72)}`);
console.log(`${results.length - failed.length}/${results.length} walks passed against ${BASE}.`);

if (failed.length > 0) {
    console.log(`Failed: ${failed.map((result) => result.walk.name).join(', ')}`);
}
console.log('');

process.exit(failed.length === 0 ? 0 : 1);
