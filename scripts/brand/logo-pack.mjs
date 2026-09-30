/**
 * The logo files, from the owner's artwork (docs/BRAND.md, STATUS §5km, §5lv).
 *
 * Give it the SVG the design tool exported and it writes every file the site
 * uses, so a new version of the logo is one command rather than hand edits:
 *
 *   public/images/logos/akuru-logo.svg          as drawn: gold emblem, wine AKURU, wine bar, white INSTITUTE
 *   public/images/logos/akuru-logo-on-dark.svg  white AKURU, gold bar, deep-wine INSTITUTE — for the wine backgrounds
 *   public/images/logos/akuru-logo-white.svg    one colour, INSTITUTE knocked out of the bar with a mask
 *   public/images/logos/akuru-mark.svg          the emblem alone, gold
 *   public/images/logos/akuru-logo-800.png      800 px wide, transparent — email, structured data
 *
 * With --pack <dir> it also writes the pack for the owner: each variant as SVG
 * and as transparent PNG at 600, 1200 and 2400 wide, and the emblem in gold,
 * white and wine at 512 and 1024.
 *
 * The export's white background rectangles are dropped and the canvas is
 * cropped to the artwork, so every file sits on any colour. Elements are told
 * apart by their fill: wine #6E1E25 glyph groups are AKURU, the wine path is
 * the bar, white glyph groups are INSTITUTE, gold #C9A227 is the emblem.
 * The app icons and favicon are the emblem only and are not touched here.
 *
 *   node scripts/brand/logo-pack.mjs "public/images/logos/<uploaded>.svg" [--pack <dir>]
 *
 * Environment: SMOKE_CHROMIUM (a Chromium to render with), as for the walks.
 */
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';

const args = process.argv.slice(2);
const source = args[0];
const packDir = args.includes('--pack') ? args[args.indexOf('--pack') + 1] : null;
if (!source || !fs.existsSync(source)) {
    console.error('usage: node scripts/brand/logo-pack.mjs <uploaded.svg> [--pack <dir>]');
    process.exit(1);
}
const OUT = 'public/images/logos';
const WINE = '#6e1e25';
const GOLD = '#c9a227';
const DEEP_WINE = '#3d1219';

const browser = await chromium.launch(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {});
const page = await browser.newPage({ viewport: { width: 1600, height: 1000 } });
await page.setContent('<!doctype html><body style="margin:0"></body>');

const variants = await page.evaluate(async ({ svgText, WINE, GOLD, DEEP_WINE }) => {
    const parse = (text) => new DOMParser().parseFromString(text, 'image/svg+xml').documentElement;
    const fillOf = (el) => (el.getAttribute('fill') || '').toLowerCase();

    // One clean copy: no background rectangles, no empty groups in <defs>.
    const base = parse(svgText);
    base.querySelectorAll('rect').forEach((r) => {
        if (fillOf(r) === '#ffffff' && Number(r.getAttribute('x')) < 0) r.remove();
    });
    base.querySelectorAll('defs > g').forEach((g) => { if (!g.children.length) g.remove(); });

    // Measure what is actually drawn (clip paths included): draw it at eight
    // pixels per unit on a canvas and find the edges of the ink.
    const [vx, vy, vw, vh] = base.getAttribute('viewBox').split(/[ ,]+/).map(Number);
    const SCALE = 8;
    const bboxOf = async (root) => {
        const svg = root.cloneNode(true);
        svg.setAttribute('width', vw * SCALE);
        svg.setAttribute('height', vh * SCALE);
        const img = new Image();
        img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(new XMLSerializer().serializeToString(svg))));
        await img.decode();
        const canvas = document.createElement('canvas');
        canvas.width = Math.ceil(vw * SCALE);
        canvas.height = Math.ceil(vh * SCALE);
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0);
        const { data, width, height } = ctx.getImageData(0, 0, canvas.width, canvas.height);
        let x1 = width, y1 = height, x2 = -1, y2 = -1;
        for (let y = 0; y < height; y++) {
            for (let x = 0; x < width; x++) {
                if (data[(y * width + x) * 4 + 3] > 8) {
                    if (x < x1) x1 = x; if (x > x2) x2 = x; if (y < y1) y1 = y; if (y > y2) y2 = y;
                }
            }
        }
        const pad = 1.5;
        const q = (n) => Math.round(n * 8) / 8;

        return [q(x1 / SCALE + vx - pad), q(y1 / SCALE + vy - pad), q((x2 - x1 + 1) / SCALE + 2 * pad), q((y2 - y1 + 1) / SCALE + 2 * pad)];
    };
    const finish = (svg, box) => {
        svg.setAttribute('viewBox', box.join(' '));
        svg.setAttribute('width', String(box[2] * 2));
        svg.setAttribute('height', String(box[3] * 2));
        svg.setAttribute('version', '1.1');
        svg.setAttribute('role', 'img');
        svg.setAttribute('aria-label', 'Akuru Institute');
        svg.removeAttribute('zoomAndPan');
        svg.querySelector(':scope > title')?.remove();
        const title = svg.ownerDocument.createElementNS('http://www.w3.org/2000/svg', 'title');
        title.textContent = 'Akuru Institute';
        svg.insertBefore(title, svg.firstChild);

        return new XMLSerializer().serializeToString(svg);
    };
    const parts = (svg) => ({
        akuru: [...svg.querySelectorAll('g')].filter((g) => fillOf(g) === WINE),
        bar: [...svg.querySelectorAll('path, rect')].filter((p) => fillOf(p) === WINE && !p.closest('defs')),
        institute: [...svg.querySelectorAll('g')].filter((g) => fillOf(g) === '#ffffff'),
        gold: [...svg.querySelectorAll('[fill]')].filter((e) => fillOf(e) === GOLD),
    });

    const whole = await bboxOf(base);
    const out = {};

    // As drawn.
    out['akuru-logo.svg'] = finish(base.cloneNode(true), whole);

    // On the wine backgrounds.
    {
        const svg = base.cloneNode(true);
        const p = parts(svg);
        p.akuru.forEach((g) => g.setAttribute('fill', '#ffffff'));
        p.bar.forEach((b) => b.setAttribute('fill', GOLD));
        p.institute.forEach((g) => g.setAttribute('fill', DEEP_WINE));
        out['akuru-logo-on-dark.svg'] = finish(svg, whole);
    }

    // One colour, INSTITUTE cut out of the bar so whatever is behind shows through.
    {
        const svg = base.cloneNode(true);
        document.body.appendChild(svg);
        const p = parts(svg);
        const ns = 'http://www.w3.org/2000/svg';
        const defs = svg.querySelector('defs') || svg.insertBefore(document.createElementNS(ns, 'defs'), svg.firstChild);
        const mask = document.createElementNS(ns, 'mask');
        mask.setAttribute('id', 'akuru-knockout');
        mask.setAttribute('maskUnits', 'userSpaceOnUse');
        for (const [k, v] of Object.entries({ x: -50, y: -50, width: 400, height: 200 })) mask.setAttribute(k, v);
        const white = document.createElementNS(ns, 'rect');
        for (const [k, v] of Object.entries({ x: -50, y: -50, width: 400, height: 200, fill: '#ffffff' })) white.setAttribute(k, v);
        mask.appendChild(white);
        const bar = p.bar[0];
        // The letters, carried into the bar's own coordinates.
        const barSpace = bar.parentNode.getCTM().inverse();
        p.institute.forEach((g) => {
            const m = barSpace.multiply(g.parentNode.getCTM());
            const wrap = document.createElementNS(ns, 'g');
            wrap.setAttribute('transform', `matrix(${m.a}, ${m.b}, ${m.c}, ${m.d}, ${m.e}, ${m.f})`);
            const clone = g.cloneNode(true);
            clone.setAttribute('fill', '#000000');
            wrap.appendChild(clone);
            mask.appendChild(wrap);
            g.remove();
        });
        defs.appendChild(mask);
        bar.setAttribute('mask', 'url(#akuru-knockout)');
        [...p.akuru, ...p.bar, ...p.gold].forEach((e) => e.setAttribute('fill', '#ffffff'));
        svg.remove();
        out['akuru-logo-white.svg'] = finish(svg, whole);
    }

    // The emblem alone.
    {
        const svg = base.cloneNode(true);
        const p = parts(svg);
        [...p.akuru, ...p.bar, ...p.institute].forEach((e) => e.remove());
        svg.querySelectorAll('g').forEach((g) => { if (!g.querySelector('path')) g.remove(); });
        out['akuru-mark.svg'] = finish(svg, await bboxOf(svg));
    }

    return out;
}, { svgText: fs.readFileSync(source, 'utf8'), WINE, GOLD, DEEP_WINE });

for (const [name, text] of Object.entries(variants)) {
    fs.writeFileSync(path.join(OUT, name), text);
    console.log(`wrote ${OUT}/${name} (${text.length} bytes)`);
}

async function png(svgText, width, file, background = null) {
    const [, , vw, vh] = svgText.match(/viewBox="([^"]+)"/)[1].split(' ').map(Number);
    const height = Math.round(width * vh / vw);
    await page.setViewportSize({ width, height });
    await page.setContent(`<!doctype html><body style="margin:0;${background ? `background:${background}` : ''}"><img style="display:block;width:${width}px;height:${height}px" src="data:image/svg+xml;base64,${Buffer.from(svgText).toString('base64')}"></body>`);
    await page.waitForFunction(() => document.querySelector('img').complete);
    await page.screenshot({ path: file, omitBackground: !background, clip: { x: 0, y: 0, width, height } });
    console.log(`wrote ${file}`);
}

await png(variants['akuru-logo.svg'], 800, path.join(OUT, 'akuru-logo-800.png'));

if (packDir) {
    fs.mkdirSync(packDir, { recursive: true });
    for (const [name, text] of Object.entries(variants)) {
        fs.writeFileSync(path.join(packDir, name), text);
        if (name === 'akuru-mark.svg') continue;
        for (const w of [600, 1200, 2400]) await png(text, w, path.join(packDir, name.replace('.svg', `-${w}.png`)));
    }
    const mark = variants['akuru-mark.svg'];
    const recolour = (hex) => mark.replaceAll(`fill="${GOLD}"`, `fill="${hex}"`);
    for (const [label, text] of [['gold', mark], ['white', recolour('#ffffff')], ['wine', recolour(WINE)]]) {
        fs.writeFileSync(path.join(packDir, `akuru-mark-${label}.svg`), text);
        for (const w of [512, 1024]) await png(text, w, path.join(packDir, `akuru-mark-${label}-${w}.png`));
    }
}

await browser.close();
