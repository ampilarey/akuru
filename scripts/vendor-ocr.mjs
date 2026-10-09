// C17 slice R2 (STATUS §5od): copy the in-browser ID-card reader into
// public/ocr/tesseract/<version>/ so the site serves it itself.
//
// The host pulls from git and never runs npm, so the copied files are
// committed, like public/build. Only what a browser can ask for is copied:
// the worker, the three LSTM engine builds it chooses between (relaxed SIMD,
// SIMD, plain), and the compact English model. Run after bumping tesseract.js,
// and bump `registration.ocr_version` to match.
//
//   npm run vendor:ocr
import { copyFileSync, mkdirSync, readFileSync, rmSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const version = JSON.parse(readFileSync(join(root, 'node_modules/tesseract.js/package.json'), 'utf8')).version;
const out = join(root, 'public/ocr/tesseract', version);

rmSync(out, { recursive: true, force: true });
mkdirSync(join(out, 'core'), { recursive: true });
mkdirSync(join(out, 'lang'), { recursive: true });

const copy = (from, to) => copyFileSync(join(root, from), join(out, to));
copy('node_modules/tesseract.js/dist/worker.min.js', 'worker.min.js');
for (const build of ['lstm', 'simd-lstm', 'relaxedsimd-lstm']) {
    copy(`node_modules/tesseract.js-core/tesseract-core-${build}.wasm.js`, `core/tesseract-core-${build}.wasm.js`);
}
copy('node_modules/@tesseract.js-data/eng/4.0.0_best_int/eng.traineddata.gz', 'lang/eng.traineddata.gz');

console.log(`Copied the ID-card reader to public/ocr/tesseract/${version}`);
