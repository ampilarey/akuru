/**
 * NAME_TAGS_PLAN §5.1 (NT2): the design model in the browser — the same
 * shape `PrintDesign::validate` returns, so what the canvas draws is what
 * the renderer prints. Millimetres on the tag; a text's size in points.
 */
export const PT_TO_MM = 0.352778;

export const FALLBACK_SCHEME = { primary: '#7A1F2B', secondary: '#E9D8B4' };

export const TYPES = ['text', 'image', 'rect', 'ellipse', 'line'];

export function blankDesign(w = 95, h = 60) {
    return {
        v: 1,
        tag: { w, h },
        background: { color: '#FFFFFF', image: null },
        elements: [],
        palette: { scheme: 'maroon', schemes: { maroon: { ...FALLBACK_SCHEME } } },
        fields: [],
    };
}

/** A design as the server gives it, with anything missing filled in. */
export function normaliseDesign(raw) {
    const base = blankDesign();
    if (!raw || typeof raw !== 'object') return base;
    const schemes = raw.palette?.schemes && Object.keys(raw.palette.schemes).length ? raw.palette.schemes : base.palette.schemes;
    const scheme = raw.palette?.scheme && schemes[raw.palette.scheme] ? raw.palette.scheme : Object.keys(schemes)[0];
    return {
        v: 1,
        tag: { w: Number(raw.tag?.w) || base.tag.w, h: Number(raw.tag?.h) || base.tag.h },
        background: { color: raw.background?.color || '#FFFFFF', image: raw.background?.image ?? null },
        elements: Array.isArray(raw.elements) ? raw.elements.map((el, i) => ({ ...el, id: el.id || `e${i + 1}`, rotate: Number(el.rotate) || 0, locked: !!el.locked })) : [],
        palette: { scheme, schemes },
        fields: Array.isArray(raw.fields) ? raw.fields : [],
    };
}

/** A colour's hex: a palette token becomes the current scheme's colour; null is no colour. */
export function resolveColour(value, design) {
    if (value === null || value === undefined || value === '' || value === 'none') return null;
    if (value === '{primary}' || value === '{secondary}') {
        const scheme = design.palette.schemes[design.palette.scheme] || FALLBACK_SCHEME;
        return scheme[value.slice(1, -1)] || FALLBACK_SCHEME[value.slice(1, -1)];
    }
    return value;
}

/** The text a tag shows for an element: its fields filled from the row. */
export function fillText(el, row) {
    let text = el.text || '';
    if (el.field) {
        const value = row?.[el.field];
        text = text.split(`{${el.field}}`).join(value === undefined || value === null ? '' : String(value));
    }
    return text.replace(/\{([a-z0-9_]+)\}/g, (m, key) => (row && row[key] !== undefined ? String(row[key]) : ''));
}

let counter = 0;
export function nextId(type) {
    counter += 1;
    return `${type}-${Date.now().toString(36)}${counter}`;
}

/** A new element of a kind, placed in the middle of the tag. */
export function newElement(type, design, extra = {}) {
    const { w, h } = design.tag;
    const base = { id: nextId(type), type, rotate: 0, locked: false };
    switch (type) {
        case 'text':
            return { ...base, x: round(w * 0.1), y: round(h * 0.35), w: round(w * 0.8), h: round(Math.min(h * 0.3, 18)), text: 'Text', font: 'noto-sans-thaana', size: 18, weight: 700, color: '{primary}', align: 'center', dir: 'auto', field: null, ...extra };
        case 'image':
            return { ...base, x: round(w * 0.3), y: round(h * 0.2), w: round(Math.min(w * 0.4, h * 0.6)), h: round(Math.min(w * 0.4, h * 0.6)), media: null, fit: 'contain', ...extra };
        case 'rect':
            return { ...base, x: round(w * 0.1), y: round(h * 0.1), w: round(w * 0.8), h: round(h * 0.8), fill: '{secondary}', stroke: null, stroke_mm: 0.3, radius: 2, ...extra };
        case 'ellipse':
            return { ...base, x: round(w * 0.3), y: round(h * 0.2), w: round(h * 0.6), h: round(h * 0.6), fill: null, stroke: '{primary}', stroke_mm: 0.6, radius: 0, ...extra };
        case 'line':
        default:
            return { ...base, type: 'line', x: round(w * 0.1), y: round(h * 0.5), w: round(w * 0.8), h: 0.5, stroke: '{primary}', ...extra };
    }
}

export function round(n, places = 2) {
    const f = 10 ** places;
    return Math.round((Number(n) || 0) * f) / f;
}

export function clampElement(el, tag) {
    const w = Math.max(0.5, Math.min(round(el.w), tag.w));
    const h = Math.max(0.5, Math.min(round(el.h), tag.h));
    return { ...el, w, h, x: round(Math.max(0, Math.min(el.x, tag.w - 0.5))), y: round(Math.max(0, Math.min(el.y, tag.h - 0.5))) };
}

/** The field keys the design's texts name, in order, as the server lists them. */
export function fieldKeysOf(design) {
    const keys = [];
    for (const el of design.elements) {
        if (el.type === 'text' && el.field && !keys.includes(el.field)) keys.push(el.field);
    }
    return keys;
}

/** The design's `fields` list kept in step with its texts, keeping each key's required flag. */
export function withFields(design) {
    const required = Object.fromEntries((design.fields || []).map((f) => [f.key, !!f.required]));
    return { ...design, fields: fieldKeysOf(design).map((key) => ({ key, required: required[key] ?? key === 'name' })) };
}

/** Snap a position to the tag's edges and centre and to other elements' edges, within a tolerance in mm. */
export function snap(value, targets, tolerance = 1) {
    let best = value;
    let distance = tolerance;
    for (const target of targets) {
        const d = Math.abs(target - value);
        if (d < distance) { best = target; distance = d; }
    }
    return best;
}
