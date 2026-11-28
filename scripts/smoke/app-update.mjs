/**
 * Is an installed app told when a new version is out? On a phone, in Dhivehi.
 * (PW1, STATUS §5uz.)
 *
 * At 390 × 844 under /dv: a page on the current build shows no bar. When
 * the server's build changes, a check shows the bar in Dhivehi with a
 * thumb-sized Refresh, nothing sideways; Later puts it away; Refresh
 * reloads the page. Under /en the same words in English. Then, with the
 * service worker running: it controls the page under the new cache name,
 * a build file the deploy retired is cleared on the page's message, and
 * one the build still names is kept.
 *
 *   node scripts/smoke/app-update.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_CHROMIUM, SMOKE_SHOTS.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const SHOTS = process.env.SMOKE_SHOTS ?? null;
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const phone = { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true };
const sideways = (page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
try {
    // The bar, with the service worker out of the way so the build's manifest can be swapped.
    const context = await browser.newContext({ ...phone, serviceWorkers: 'block' });
    const page = await context.newPage();
    page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
    await page.goto(`${BASE}/dv`, { waitUntil: 'networkidle' });
    const served = await page.locator('meta[name="app-build"]').getAttribute('content');
    check('/dv the page says which build it was served with', /^[0-9a-f]{64}$/.test(served ?? ''), served ?? 'none');
    check('/dv on the current build a check finds nothing and shows no bar', await page.evaluate(() => window.akuruCheckForUpdate()) === false && await page.locator('[data-testid="update-bar"]').count() === 0);

    // A deploy lands: the server's build is no longer the page's.
    let deployed = true;
    await context.route('**/build/manifest.json', async (route) => {
        if (!deployed) return route.continue();
        const response = await route.fetch();
        await route.fulfill({ response, body: `${await response.text()}\n` });
    });
    check('/dv after a deploy the check finds the new version', await page.evaluate(() => window.akuruCheckForUpdate()) === true);
    const bar = page.locator('[data-testid="update-bar"]');
    await bar.waitFor({ timeout: 5000 }).catch(() => {});
    const refresh = page.locator('[data-testid="update-refresh"]');
    const box = await refresh.boundingBox();
    check('/dv a bar says so in Dhivehi, at the top, with a thumb-sized Refresh, nothing sideways', await bar.count() === 1 && /[ހ-޿]/.test(await bar.innerText()) && (await bar.boundingBox()).y === 0 && box !== null && box.height >= 44 && await sideways(page) <= 1, await bar.innerText().catch(() => 'no bar'));
    if (SHOTS) await page.screenshot({ path: `${SHOTS}/pw1-update-bar-dv.png` });
    await page.evaluate(() => window.akuruCheckForUpdate());
    check('/dv a second check does not stack a second bar', await bar.count() === 1);

    await page.locator('[data-testid="update-later"]').click();
    await page.evaluate(() => window.akuruCheckForUpdate());
    check('/dv Later puts it away for this page', await bar.count() === 0);

    await page.reload({ waitUntil: 'networkidle' });
    await page.evaluate(() => window.akuruCheckForUpdate());
    await bar.waitFor({ timeout: 5000 }).catch(() => {});
    deployed = false;
    await Promise.all([page.waitForEvent('load'), refresh.click()]);
    await page.waitForLoadState('networkidle');
    check('/dv Refresh reloads the page, and on the new build the bar is gone', await page.evaluate(() => window.akuruCheckForUpdate()) === false && await bar.count() === 0);

    deployed = true;
    await page.goto(`${BASE}/en`, { waitUntil: 'networkidle' });
    await page.evaluate(() => window.akuruCheckForUpdate());
    await bar.waitFor({ timeout: 5000 }).catch(() => {});
    check('/en the same bar in English', (await bar.innerText().catch(() => '')).includes('A new version of Akuru is ready.') && (await refresh.innerText()) === 'Refresh');
    await context.close();

    // The service worker itself.
    const live = await browser.newContext(phone);
    const app = await live.newPage();
    await app.goto(`${BASE}/dv`, { waitUntil: 'networkidle' });
    await app.evaluate(() => navigator.serviceWorker.ready);
    await app.reload({ waitUntil: 'networkidle' });
    const state = await app.evaluate(async () => ({ controlled: !!navigator.serviceWorker.controller, caches: await caches.keys() }));
    check('the service worker controls the page under the new cache name alone', state.controlled && state.caches.join() === 'akuru-shell-v2', JSON.stringify(state));

    const outcome = await app.evaluate(async () => {
        const manifest = await (await fetch('/build/manifest.json', { cache: 'no-store' })).json();
        const kept = `/build/${Object.values(manifest)[0].file}`;
        const cache = await caches.open('akuru-shell-v2');
        await cache.put('/build/assets/retired-by-a-deploy.js', new Response('old'));
        await cache.put(kept, new Response('current'));
        (await navigator.serviceWorker.ready).active.postMessage({ type: 'prune' });
        for (let i = 0; i < 50; i++) {
            if (!(await cache.match('/build/assets/retired-by-a-deploy.js'))) break;
            await new Promise((r) => setTimeout(r, 200));
        }
        return { retired: !!(await cache.match('/build/assets/retired-by-a-deploy.js')), kept: !!(await cache.match(kept)), manifest: !!(await cache.match('/build/manifest.json')), offline: !!(await cache.match('/offline.html')) };
    });
    check('a build file the deploy retired is cleared; one the build names, and the offline page, are kept; the manifest is never cached', !outcome.retired && outcome.kept && outcome.offline && !outcome.manifest, JSON.stringify(outcome));

    await live.setOffline(true);
    const offline = await app.goto(`${BASE}/dv/about`, { waitUntil: 'load' }).then(() => app.content()).catch((e) => String(e));
    await live.setOffline(false);
    check('offline, a page falls back to the offline page', /offline/i.test(offline), offline.slice(0, 120));
    await live.close();
} catch (e) {
    problems.push(String(e).slice(0, 300));
} finally {
    await browser.close();
}
for (const problem of problems) check(problem, false);
let passed = 0;
for (const [step, ok, detail] of results) {
    if (ok) passed++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${step}${!ok && detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length ? 0 : 1);
