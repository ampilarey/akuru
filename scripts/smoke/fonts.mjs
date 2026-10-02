/**
 * The fonts are self-hosted (docs/ADMIN_PANEL.md §7 P6, STATUS §5np): no
 * page asks Google or bunny.net for a face, and the faces a page needs
 * come from the build, hashed and cacheable. Walked on the public home, the
 * sign-in page (the guest layout), the admin hub in English, Dhivehi and
 * Arabic, and the School office (the Blade shell).
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/fonts.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN (a super admin), SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
// Font stylesheets and font files from the two hosts — not the Google
// Translate widget's logo, which also lives on gstatic and is not a font.
const THIRD_PARTY = /fonts\.googleapis\.com\/css|fonts\.bunny\.net|fonts\.gstatic\.com\/s\/.*\.(woff2?|ttf)/;

const browser = await chromium.launch({
    args: ['--disable-background-networking', '--no-first-run', '--no-default-browser-check'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});

const results = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
let finished = false;
async function finish() {
    if (finished) return;
    finished = true;
    const width = Math.max(...results.map(([step]) => step.length));
    for (const [step, ok, detail] of results) console.log(`${ok ? 'ok  ' : 'FAIL'}  ${step.padEnd(width)}  ${detail}`);
    const failed = results.filter(([, ok]) => !ok).length;
    console.log(`\n${results.length - failed}/${results.length} steps passed.`);
    await browser.close();
    process.exit(failed === 0 ? 0 : 1);
}
for (const event of ['unhandledRejection', 'uncaughtException']) {
    process.on(event, async (error) => { check('the walk reached its end', false, String(error?.message ?? error).split('\n')[0].slice(0, 200)); await finish(); });
}

// Every request is allowed through so a third-party one can be seen, not just blocked.
const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
context.setDefaultNavigationTimeout(60000);
const page = await context.newPage();
const requests = [];
page.on('request', (request) => requests.push(request.url()));

const fontsLoaded = () => page.evaluate(async () => {
    await document.fonts.ready;
    return [...document.fonts].filter((f) => f.status === 'loaded').map((f) => f.family.replace(/"/g, ''));
});
const woff = () => requests.filter((u) => /\.woff2?(\?|$)/.test(u)).map((u) => u.replace(BASE, ''));
const third = () => requests.filter((u) => THIRD_PARTY.test(u));

async function visit(label, path, wants) {
    requests.length = 0;
    const response = await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle' });
    const families = await fontsLoaded();
    const files = woff();
    const outside = third();
    check(`${label} asks no third party for a font`, response?.status() === 200 && outside.length === 0, outside.length ? outside.slice(0, 2).join(', ') : `${files.length} font files from the build`);
    check(`${label} draws with ${wants.join(' and ')} from the build`, wants.every((w) => families.includes(w)) && files.every((f) => f.startsWith('/build/assets/') || f.startsWith('/fonts/')), `${[...new Set(families)].join(', ')} · ${files.map((f) => f.split('/').pop().split('-')[0]).join(', ')}`);
}

await visit('the public home', '/en', ['Figtree']);
await visit('the sign-in page', '/en/login', ['Figtree']);

await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
await page.fill('input[name="identifier"]', ADMIN);
await page.fill('input[name="password"]', PASSWORD);
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('button[type=submit]')]);

await visit('the admin hub in English', '/en/admin', ['Figtree']);
await visit('the admin hub in Dhivehi', '/dv/admin', ['Faruma']);
await visit('the admin hub in Arabic', '/ar/admin', ['Cairo']);
await visit('the School office (the Blade shell)', '/en/school', ['Figtree']);

// An English page must not download the Arabic files: each face is split by script.
requests.length = 0;
await page.goto(`${BASE}/en/admin/users`, { waitUntil: 'networkidle' });
const englishFiles = woff();
check('an English page downloads no Arabic font file', englishFiles.every((f) => !/arabic/.test(f)), englishFiles.map((f) => f.split('/').pop()).join(', ') || 'cached');

await finish();
