/**
 * Does a notice read in the family's language, whoever sent it? (HP3,
 * ADR-044, STATUS §5tx.)
 *
 * A cart reminder and a Library reading nudge (HP3b) are sent to the seeded
 * parent the way the nightly jobs send them, in English (tinker, so local
 * only). On a 390 px phone the parent opens
 * their notifications under /dv and /ar: the reminder reads in Dhivehi, then
 * in Arabic, not in the words it was sent in. The notice is removed after.
 *
 *   node scripts/smoke/notice-language.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_PARENT, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const PARENT = process.env.SMOKE_PARENT ?? 'parent@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
if (!LOCAL) {
    console.log('notice-language sends a notice with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key, locale) => tinker(`echo __('${key}', [], '${locale}');`);
const results = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);

const userId = tinker(`echo DB::table('users')->where('email', '${PARENT}')->value('id');`);
const id = tinker(`app()->setLocale('en'); app(\\App\\Domains\\Bookshop\\Actions\\NotifyBookshopUserAction::class)->execute(${userId}, \\App\\Support\\Said::of('shop.notice_cart_reminder_title'), \\App\\Support\\Said::of('shop.notice_cart_reminder_body', ['count' => 1, 'shops' => 'Fitrah']), '/shop/cart'); echo DB::table('user_notifications')->where('user_id', ${userId})->max('id');`);
const libraryId = tinker(`app()->setLocale('en'); app(\\App\\Domains\\Library\\Actions\\NotifyLibraryUserAction::class)->execute(${userId}, \\App\\Support\\Said::of('library_notices.continue_title'), \\App\\Support\\Said::of('library_notices.continue_body', ['page' => 4, 'title' => 'SMOKE-Primer']), '/library'); echo DB::table('user_notifications')->where('user_id', ${userId})->max('id');`);
const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
try {
    const english = say('shop.notice_cart_reminder_title', 'en');
    check('the reminder is kept in the words it was sent in, English', tinker(`echo DB::table('user_notifications')->where('id', ${id})->value('title');`) === english);
    const page = await (await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true })).newPage();
    await page.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
    await page.fill('input[name="identifier"]', PARENT);
    await page.fill('input[name="password"]', PASSWORD);
    await page.locator('form[action$="/login"] button[type="submit"]').first().click();
    await page.waitForURL((u) => !String(u).includes('/login'));
    for (const locale of ['dv', 'ar']) {
        await page.goto(`${BASE}/${locale}/portal/notifications`, { waitUntil: 'networkidle' });
        // Its own row: an older notice may still be in the words it was sent in.
        const text = await page.locator(`[data-notification="${id}"]`).innerText().catch(() => '');
        const wanted = say('shop.notice_cart_reminder_title', locale);
        check(`/${locale} the parent reads it in their language, not in English`, text.includes(wanted) && !text.includes(english), wanted);
        const nudge = await page.locator(`[data-notification="${libraryId}"]`).innerText().catch(() => '');
        const nudgeWanted = say('library_notices.continue_title', locale);
        check(`/${locale} and the Library's nudge too`, nudge.includes(nudgeWanted) && !nudge.includes(say('library_notices.continue_title', 'en')), nudgeWanted);
    }
} finally {
    await browser.close();
    tinker(`DB::table('user_notifications')->whereIn('id', [${Number(id) || 0}, ${Number(libraryId) || 0}])->delete(); echo 'ok';`);
}
let passed = 0;
for (const [step, ok, detail] of results) {
    if (ok) passed++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${step}${!ok && detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length ? 0 : 1);
