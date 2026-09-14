import { chromium } from 'playwright';
const BASE = 'http://127.0.0.1:8123';
const PATHS = [
  '/en/catalog/courses/1/assessments','/en/hr/postings','/en/catalog/quran','/en/catalog/quran/oversight',
  '/en/catalog/reviews','/en/academics/requests','/en/hr/leave-balances',
  '/en/academics/absence-notes','/en/academics/attendance/daily','/en/academics/promotion',
  '/en/admin/commerce','/en/catalog/arabic','/en/catalog/audiences','/en/catalog/levels',
  '/en/finance/receipts/manual','/en/admin/library','/en/people/staff',
  '/en/portal/appraisals','/en/portal/homework','/en/portal/meetings','/en/admin/pronunciation',
];
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
const p = await (await browser.newContext()).newPage();
const errs = [];
p.on('pageerror', e => errs.push(String(e).slice(0, 110)));
await p.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
await p.fill('input[name="identifier"]', 'admin@akuru.edu.mv');
await p.fill('input[name="password"]', 'password');
await p.click('button[type="submit"]');
await p.waitForLoadState('domcontentloaded');
let bad = 0, checked = 0;
for (const path of PATHS) {
  errs.length = 0;
  const res = await p.goto(BASE + path, { waitUntil: 'domcontentloaded' }).catch(() => null);
  await p.waitForTimeout(500);
  const status = res ? res.status() : 'nav';
  if (status !== 200) { console.log(`${String(status).padEnd(4)} ${path}`); continue; }
  checked++;
  if (errs.length) { bad++; console.log(`JS!  ${path}  ${errs[0]}`); }
}
console.log(`RESULT ${checked} pages loaded, ${bad} with JS errors`);
await browser.close();
