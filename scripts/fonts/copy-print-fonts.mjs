/**
 * Copies the print fonts named in config/print.php out of their @fontsource
 * packages into public/fonts/print, each beside its licence (NAME_TAGS_PLAN
 * §5.4, slice NT0). The copies are committed, like Faruma under public/fonts,
 * so the designer, the renderer and production serve the same files without
 * a build step. Run after changing the font table or updating the packages:
 *
 *   node scripts/fonts/copy-print-fonts.mjs
 */
import { copyFileSync, existsSync, mkdirSync, readdirSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');
const out = join(root, 'public', 'fonts', 'print');
mkdirSync(out, { recursive: true });

// package → the woff2 files to take (matched by suffix) and the licence name to write.
const packages = {
    'noto-sans-thaana': ['thaana-400-normal.woff2', 'thaana-700-normal.woff2'],
    amiri: ['arabic-400-normal.woff2', 'arabic-700-normal.woff2'],
    cairo: ['arabic-400-normal.woff2', 'arabic-700-normal.woff2'],
    figtree: ['latin-400-normal.woff2', 'latin-700-normal.woff2'],
    poppins: ['latin-400-normal.woff2', 'latin-700-normal.woff2'],
    nunito: ['latin-400-normal.woff2', 'latin-700-normal.woff2'],
    fredoka: ['latin-400-normal.woff2', 'latin-700-normal.woff2'],
    'baloo-2': ['latin-400-normal.woff2', 'latin-700-normal.woff2'],
    lora: ['latin-400-normal.woff2', 'latin-700-normal.woff2'],
    merriweather: ['latin-400-normal.woff2', 'latin-700-normal.woff2'],
    pacifico: ['latin-400-normal.woff2'],
};

let copied = 0;
for (const [pkg, suffixes] of Object.entries(packages)) {
    const dir = join(root, 'node_modules', '@fontsource', pkg);
    if (!existsSync(dir)) throw new Error(`@fontsource/${pkg} is not installed`);
    const files = readdirSync(join(dir, 'files'));
    for (const suffix of suffixes) {
        const file = files.find((f) => f === `${pkg}-${suffix}`);
        if (!file) throw new Error(`@fontsource/${pkg} has no ${pkg}-${suffix}`);
        copyFileSync(join(dir, 'files', file), join(out, file));
        copied++;
    }
    copyFileSync(join(dir, 'LICENSE'), join(out, `${pkg}-LICENSE.txt`));
}
console.log(`${copied} font files and ${Object.keys(packages).length} licences copied to public/fonts/print`);
