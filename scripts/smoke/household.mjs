/**
 * Does every page a family opens work on a phone, in Dhivehi and Arabic?
 * (The household audit, HP1, STATUS §5tv.)
 *
 * As the seeded parent and the seeded pupil, at 390 × 844: sign in, gather
 * every link the shell offers them (the bar, the More menu, the home's
 * tiles), then open each under /dv and /ar. Each page must answer, read
 * right to left, scroll nothing sideways, raise no server or script error,
 * stack its tables, and give no control under 24 px to a thumb.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/household.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_PARENT, SMOKE_STUDENT, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const PEOPLE = [process.env.SMOKE_PARENT ?? 'parent@akuru.edu.mv', process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv'];
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const phone = { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true };
const results = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);

try {
    for (const who of PEOPLE) {
        const context = await browser.newContext(phone);
        const page = await context.newPage();
        let errors = [];
        page.on('pageerror', (e) => errors.push(`script: ${String(e).slice(0, 120)}`));
        page.on('response', (r) => { if (r.status() >= 400 && r.url().startsWith(BASE)) errors.push(`HTTP ${r.status()} ${r.url().replace(BASE, '')}`); });

        await page.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
        await page.fill('input[name="identifier"]', who);
        await page.fill('input[name="password"]', PASSWORD);
        await page.locator('form[action$="/login"] button[type="submit"]').first().click();
        await page.waitForURL((u) => !String(u).includes('/login'), { timeout: 20000 });
        await page.waitForLoadState('networkidle');
        const home = page.url().replace(BASE, '');
        const more = page.locator('button[aria-controls="app-shell-more"]');
        if (await more.count()) {
            await more.first().click().catch(() => {});
            await page.waitForTimeout(400);
        }
        const paths = [...new Set([home, ...await page.evaluate((base) => [...document.querySelectorAll('a[href]')]
            .map((a) => a.getAttribute('href'))
            .filter((h) => h && (h.startsWith('/') || h.startsWith(base)) && !h.includes('logout'))
            .map((h) => h.replace(base, '').split('#')[0])
            .filter((h) => h && !/\.(pdf|csv)$|\/export/.test(h)), BASE)]
            .map((h) => h.replace(/^\/(en|dv|ar)(?=\/|$)/, '') || '/'))].sort();
        check(`${who}: the shell offers their pages`, paths.length >= 20, `${paths.length} pages`);

        const faults = [];
        for (const path of paths) {
            for (const locale of ['dv', 'ar']) {
                errors = [];
                const response = await page.goto(`${BASE}/${locale}${path}`, { waitUntil: 'networkidle' }).catch(() => null);
                const seen = await page.evaluate(() => {
                    const doc = document.documentElement;
                    const main = document.querySelector('main') ?? document.body;
                    return {
                        dir: doc.getAttribute('dir'),
                        sideways: doc.scrollWidth - doc.clientWidth,
                        unstacked: [...main.querySelectorAll('table')].filter((t) => !t.classList.contains('table-stack') && !t.closest('[data-wide-ok]')).length,
                        tiny: [...main.querySelectorAll('a, button, select, textarea, summary, input:not([type=checkbox]):not([type=radio]):not([type=hidden])')].filter((el) => {
                            const r = el.getBoundingClientRect();
                            return r.width > 0 && r.height > 0 && (r.height < 24 || r.width < 24) && getComputedStyle(el).visibility !== 'hidden';
                        }).map((el) => (el.innerText || el.getAttribute('aria-label') || el.tagName).trim().slice(0, 24)),
                    };
                }).catch((e) => ({ failed: String(e).slice(0, 80) }));
                const where = `/${locale}${path}`;
                if ((response?.status() ?? 0) !== 200) faults.push(`${where} answered ${response?.status() ?? 'nothing'}`);
                if (seen.failed) faults.push(`${where} ${seen.failed}`);
                if (seen.dir !== 'rtl') faults.push(`${where} reads ${seen.dir ?? 'no direction'}`);
                if (seen.sideways > 1) faults.push(`${where} scrolls ${seen.sideways} px sideways`);
                if (seen.unstacked) faults.push(`${where} has ${seen.unstacked} table(s) that do not stack`);
                if (seen.tiny?.length) faults.push(`${where} small targets: ${seen.tiny.slice(0, 4).join(', ')}`);
                for (const error of new Set(errors)) faults.push(`${where} ${error}`);
            }
        }
        if (paths.includes('/portal/learning')) {
            // HP1: a child's relationship and an enrolment's state in words, not their codes.
            await page.goto(`${BASE}/dv/portal/learning`, { waitUntil: 'networkidle' });
            const said = await page.locator('main').innerText();
            check(`${who}: their children's learning names the relationship and the state in Dhivehi`, !/\b(father|mother|guardian|active|pending|completed)\b/i.test(said), said.replace(/\s+/g, ' ').slice(0, 120));
        }
        check(`${who}: every page, in Dhivehi and Arabic, works on a phone`, faults.length === 0, faults.slice(0, 12).join(' | '));
        await context.close();
    }
} finally {
    await browser.close();
}

let passed = 0;
for (const [step, ok, detail] of results) {
    if (ok) passed++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${step}${detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length ? 0 : 1);
