/**
 * The phrase books travel once (docs/ADMIN_PANEL.md §7 P2, STATUS §5nm).
 *
 * A page's `t` is its whole language file (44 KB for `admin`) and Inertia
 * resends props on every visit. As a once-prop the client remembers it and
 * names what it holds on the next visit, and the server leaves it out. This
 * walk signs in, loads the hub (a full load, everything arrives), opens
 * Manage users through the shell (an Inertia visit) and checks that the
 * request named the keys, the response carried no `t` or `i18n`, and the
 * screen still read its heading from the remembered book — in Dhivehi, where
 * a missing book would show the English fallback.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/once-props.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN (a super admin), SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

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

const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
context.setDefaultNavigationTimeout(60000);
await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
const page = await context.newPage();

await page.goto(`${BASE}/dv/login`, { waitUntil: 'domcontentloaded' });
await page.fill('input[name="identifier"]', ADMIN);
await page.fill('input[name="password"]', PASSWORD);
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('button[type=submit]')]);

// A full load of the hub: the first response carries the books and names them.
const hub = await page.goto(`${BASE}/dv/admin`, { waitUntil: 'networkidle' });
const first = await page.evaluate(() => JSON.parse(document.querySelector('script[data-page="app"]')?.textContent || '{}'));
check('the hub loads in Dhivehi', hub?.status() === 200 && first.component === 'Portal/WorkspaceHome', `${hub?.status()} ${first.component}`);
check('the first load carries the admin book and the shell strings', typeof first.props?.t === 'object' && typeof first.props?.i18n === 'object', `t:${typeof first.props?.t} i18n:${typeof first.props?.i18n}`);
const keys = Object.keys(first.onceProps ?? {});
check('and names them as once-props keyed by file and locale', keys.includes('t:admin:dv') && keys.includes('i18n:dv'), keys.join(', '));

// An Inertia visit to Manage users: the request names what it holds, the response leaves it out.
const visit = page.waitForResponse((r) => r.url().includes('/admin/users') && r.request().headers()['x-inertia'] === 'true');
await page.click('[data-testid="open-manage_users"]');
const response = await visit;
const sent = response.request().headers()['x-inertia-except-once-props'] ?? '';
const body = await response.json();
check('the visit tells the server which books it already holds', sent.includes('t:admin:dv') && sent.includes('i18n:dv'), sent);
check('and the response leaves `t` and `i18n` out', !('t' in (body.props ?? {})) && !('i18n' in (body.props ?? {})), Object.keys(body.props ?? {}).join(', '));
check('while the page data still arrives', Array.isArray(body.props?.users), `users:${Array.isArray(body.props?.users)}`);

// The response is in; wait for React to swap the page in before reading the heading.
const wanted = first.props?.t?.users_title ?? '';
await page.waitForFunction((w) => [...document.querySelectorAll('h1')].some((h) => h.textContent.trim() === w), wanted, { timeout: 10000 }).catch(() => {});
const headings = (await page.locator('h1').allTextContents()).map((h) => h.trim());
check('Manage users still reads its heading from the remembered Dhivehi book', wanted !== '' && /[ހ-޿]/.test(wanted) && headings.includes(wanted), `${headings.join(' | ')} (wanted ${wanted})`);
const more = (await page.locator('[data-testid="shell-more"]').first().textContent().catch(() => ''))?.trim() ?? '';
check('and the shell its More button', more === '' || /[ހ-޿]/.test(more), more || '(no More on this width)');

// A second screen in the same book: still nothing resent.
const second = page.waitForResponse((r) => r.url().includes('/admin/settings') && r.request().headers()['x-inertia'] === 'true');
await page.goto(`${BASE}/dv/admin`, { waitUntil: 'networkidle' });
await page.click('[data-testid="open-system_settings"]');
const settings = await (await second).json();
check('a second screen of the same book is not resent either', !('t' in (settings.props ?? {})), Object.keys(settings.props ?? {}).join(', '));

await finish();
