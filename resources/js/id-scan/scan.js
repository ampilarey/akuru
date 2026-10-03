/**
 * "Fill in from your ID card" on the registration forms (C17 slice R2, STATUS
 * §5od). Loaded only on a page that has a `[data-id-scan]` block, and the
 * reader itself (tesseract.js, self-hosted under public/vendor/tesseract) only
 * when somebody picks a photo.
 *
 * The photo is read here, in the browser. It is not uploaded to be read: on
 * the checkout form it is never sent at all; on the details form it goes up
 * with the form, as the ID card the office checks, exactly as before.
 *
 * What it does with the answer:
 *
 * - fills the visible, enabled fields of the form that are **empty** —
 *   choosing the ID card or passport option first when the card says which;
 * - lists what it filled and asks the person to check it;
 * - when a field already holds something different, says so and offers one
 *   button to use the card's details instead. It never overwrites silently,
 *   and it never submits anything.
 *
 * If the reader cannot start or reads nothing, it says so and the form is
 * exactly as usable as before.
 */
import { mergeIdFields, parseIdText } from './parse.js';

const FIELDS = ['first_name', 'middle_name', 'last_name', 'dob', 'gender', 'national_id', 'passport'];

let workerPromise = null;

function reader(base) {
    if (!workerPromise) {
        workerPromise = import('tesseract.js')
            .then(({ createWorker }) => createWorker('eng', 1, {
                workerPath: `${base}/worker.min.js`,
                corePath: `${base}/core`,
                langPath: `${base}/lang`,
                gzip: true,
                workerBlobURL: false,
            }));
        workerPromise.catch(() => { workerPromise = null; });
    }
    return workerPromise;
}

async function bitmapOf(file) {
    try {
        return await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch {
        const url = URL.createObjectURL(file);
        try {
            const img = new Image();
            img.src = url;
            await img.decode();
            return img;
        } finally {
            URL.revokeObjectURL(url);
        }
    }
}

/**
 * Scale the photo to a size recognition likes, turn it a quarter if asked
 * (a card photographed sideways), and make it grey, stretched to full contrast.
 */
async function prepare(image, quarterTurns = 0) {
    const long = Math.max(image.width, image.height) || 1;
    const scale = Math.min(3, Math.max(0.5, 1800 / long));
    const w = Math.round(image.width * scale);
    const h = Math.round(image.height * scale);
    const sideways = quarterTurns % 2 === 1;
    const canvas = document.createElement('canvas');
    canvas.width = sideways ? h : w;
    canvas.height = sideways ? w : h;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    ctx.translate(canvas.width / 2, canvas.height / 2);
    ctx.rotate((quarterTurns * Math.PI) / 2);
    ctx.drawImage(image, -w / 2, -h / 2, w, h);
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    const pixels = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const d = pixels.data;
    let min = 255;
    let max = 0;
    for (let i = 0; i < d.length; i += 4) {
        const g = Math.round(0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2]);
        d[i] = g;
        if (g < min) min = g;
        if (g > max) max = g;
    }
    const range = Math.max(1, max - min);
    for (let i = 0; i < d.length; i += 4) {
        const g = Math.round(((d[i] - min) * 255) / range);
        d[i] = g;
        d[i + 1] = g;
        d[i + 2] = g;
    }
    ctx.putImageData(pixels, 0, 0);
    return canvas;
}

const visible = (el) => el && !el.disabled && el.type !== 'hidden' && el.offsetParent !== null;

function fieldIn(form, name) {
    return [...form.querySelectorAll(`[name="${name}"]`)].find(visible) ?? null;
}

function setValue(el, value) {
    el.value = value;
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
}

const same = (a, b) => String(a).trim().toLowerCase() === String(b).trim().toLowerCase();

const nextFrame = () => new Promise((resolve) => setTimeout(resolve, 30));

async function chooseIdType(form, idType) {
    if (!idType) {
        return;
    }
    const radio = [...form.querySelectorAll('input[type="radio"]')]
        .find((r) => r.value === idType && r.offsetParent !== null && !r.disabled);
    if (radio && !radio.checked) {
        radio.click();
        await nextFrame();
    }
}

/** @returns {Promise<{filled: string[], differs: Array<{name: string, el: HTMLElement, value: string}>}>} */
async function fill(form, fields) {
    await chooseIdType(form, fields.id_type);
    const filled = [];
    const differs = [];
    for (const name of FIELDS) {
        const value = fields[name];
        if (!value) {
            continue;
        }
        const el = fieldIn(form, name);
        if (!el) {
            continue;
        }
        if (el.value.trim() === '') {
            setValue(el, value);
            el.dataset.idScanFilled = '1';
            filled.push(name);
        } else if (!same(el.value, value)) {
            differs.push({ name, el, value });
        }
    }
    return { filled, differs };
}

function wire(root) {
    const form = root.closest('form') ?? document.querySelector(root.dataset.form || 'form');
    const status = root.querySelector('[data-id-scan-status]');
    const useButton = root.querySelector('[data-id-scan-use]');
    const base = root.dataset.ocrBase;
    const msg = JSON.parse(root.dataset.messages || '{}');
    const labels = JSON.parse(root.dataset.labels || '{}');
    const results = new Map();
    let pending = [];

    const say = (text, tone = 'info') => {
        if (!status) {
            return;
        }
        status.textContent = text;
        status.dataset.tone = tone;
        status.hidden = text === '';
    };
    const names = (list) => list.map((n) => labels[n] ?? n).join(', ');

    useButton?.addEventListener('click', () => {
        for (const { el, value } of pending) {
            setValue(el, value);
            el.dataset.idScanFilled = '1';
        }
        say((msg.filled ?? 'Filled in from the card: :fields. Please check them.').replace(':fields', names(pending.map((p) => p.name))), 'ok');
        pending = [];
        useButton.hidden = true;
    });

    const sources = [...root.querySelectorAll('input[type="file"][data-id-scan-source]'),
        ...(form ? form.querySelectorAll('input[type="file"][data-id-scan-source]') : [])]
        .filter((el, i, all) => all.indexOf(el) === i);

    for (const input of sources) {
        input.addEventListener('change', async () => {
            const file = input.files?.[0];
            if (!file) {
                return;
            }
            if (file.type === 'application/pdf') {
                say(msg.pdf ?? 'A PDF cannot be read here. Choose a photo, or type the details.', 'warn');
                return;
            }
            say(msg.reading ?? 'Reading the card…', 'busy');
            root.dataset.state = 'reading';
            try {
                const worker = await reader(base);
                const image = await bitmapOf(file);
                // Upright first; a photo that yields almost nothing is tried
                // a quarter turn each way, and the fullest reading kept.
                let best = {};
                for (const turns of [0, 1, 3]) {
                    const { data } = await worker.recognize(await prepare(image, turns));
                    const fields = parseIdText(data.text);
                    if (Object.keys(fields).length > Object.keys(best).length) {
                        best = fields;
                    }
                    if (Object.keys(best).length >= 4) {
                        break;
                    }
                }
                results.set(input, best);
            } catch (error) {
                root.dataset.state = 'failed';
                say(msg.failed ?? 'The card reader could not start on this device. Please type the details.', 'warn');
                return;
            }
            const fields = mergeIdFields(...sources.map((s) => results.get(s)).filter(Boolean));
            const { filled, differs } = await fill(form, fields);
            pending = differs;
            root.dataset.state = 'done';
            if (useButton) {
                useButton.hidden = differs.length === 0;
            }
            const parts = [];
            if (filled.length) {
                parts.push((msg.filled ?? 'Filled in from the card: :fields. Please check them.').replace(':fields', names(filled)));
            }
            if (differs.length) {
                parts.push((msg.differs ?? 'The card shows something different for: :fields.').replace(':fields', names(differs.map((d) => d.name))));
            }
            if (!filled.length && !differs.length) {
                parts.push(Object.keys(fields).length > 1 ? (msg.already ?? 'Everything the card shows is already filled in.') : (msg.nothing ?? 'We could not read the details from this photo. Try a sharper photo, straight on and in good light, or type them.'));
            }
            say(parts.join(' '), filled.length || differs.length ? 'ok' : 'warn');
        });
    }
}

export function start() {
    document.querySelectorAll('[data-id-scan]').forEach(wire);
}
