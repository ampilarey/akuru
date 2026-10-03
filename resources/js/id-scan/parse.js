/**
 * Read the details off an ID card or passport — from the text a photo of it
 * yields (C17 slice R2, STATUS §5od). The owner, 2026-10-03: "when id card is
 * uploaded it reads the data and auto fills".
 *
 * Pure: text in, fields out. The recognition itself happens in the browser
 * (`scan.js`); this only decides what the text says, and is tested on its
 * own (`tests/js/id-scan.test.mjs`).
 *
 * Two sources, best first:
 *
 * 1. **The machine-readable zone** — the `P<MDV…` lines on a passport, the
 *    three `I<…` lines on an ID card that carries them. Its fields have
 *    check digits, so a field is taken only when its check digit agrees.
 * 2. **The printed text** — "ID No. A123456", "Date of Birth", "Sex", the
 *    English name line. OCR misreads letters for digits, so a Maldivian ID
 *    number is "A" followed by six characters that read as digits once
 *    O/I/l/S/B are mapped back.
 *
 * Whatever it returns is a suggestion: the form fills empty fields and asks
 * the person to check them. Nothing is submitted for them.
 *
 * @typedef {{first_name?: string, middle_name?: string, last_name?: string, dob?: string, gender?: 'male'|'female', id_type?: 'national_id'|'passport', national_id?: string, passport?: string, source?: 'mrz'|'text'}} IdFields
 */

const MONTHS = { JAN: 1, FEB: 2, MAR: 3, APR: 4, MAY: 5, JUN: 6, JUL: 7, AUG: 8, SEP: 9, SEPT: 9, OCT: 10, NOV: 11, DEC: 12 };

// Words that appear on a card but are never part of a person's name.
const NOT_A_NAME = new Set([
    'REPUBLIC', 'MALDIVES', 'MALDIVIAN', 'NATIONAL', 'IDENTITY', 'IDENTIFICATION', 'CARD', 'DATE', 'BIRTH', 'OF', 'SEX',
    'MALE', 'FEMALE', 'ISSUE', 'ISSUED', 'EXPIRY', 'EXPIRES', 'VALID', 'UNTIL', 'ADDRESS', 'SIGNATURE', 'PERMANENT',
    'PASSPORT', 'NO', 'NUMBER', 'NATIONALITY', 'PLACE', 'HOLDER', 'GOVERNMENT', 'DEPARTMENT', 'REGISTRATION', 'ID',
    'TYPE', 'CODE', 'AUTHORITY', 'ISLAND', 'ATOLL', 'NAME', 'SURNAME', 'GIVEN', 'NAMES', 'DOB', 'GENDER', 'COUNTRY',
]);

/** Map the letters OCR mistakes for digits back to digits. */
const toDigits = (s) => s.replace(/[Oo]/g, '0').replace(/[IlL|]/g, '1').replace(/[Ss]/g, '5').replace(/B/g, '8').replace(/Z/g, '2');

const pad = (n) => String(n).padStart(2, '0');

/** A real calendar date in the past, at most 120 years ago — or null. */
function isoDate(year, month, day, today = new Date()) {
    const y = Number(year);
    const m = Number(month);
    const d = Number(day);
    if (!y || !m || !d || m > 12 || d > 31) {
        return null;
    }
    const date = new Date(Date.UTC(y, m - 1, d));
    if (date.getUTCFullYear() !== y || date.getUTCMonth() !== m - 1 || date.getUTCDate() !== d) {
        return null;
    }
    if (date > today || today.getUTCFullYear() - y > 120) {
        return null;
    }
    return `${y}-${pad(m)}-${pad(d)}`;
}

const titleCase = (s) => s.toLowerCase().replace(/(^|[\s'-])([a-z])/g, (_, p, c) => p + c.toUpperCase());

/**
 * Split a full name the Maldivian way, as the form asks for it (C17 slice
 * R4): the first word, the last word, and whatever stands between as the
 * middle name. A middle name is left out when there is none.
 */
function splitName(full) {
    const words = full.trim().split(/\s+/).filter(Boolean).map(titleCase);
    const out = { first_name: words[0] ?? '', last_name: words.length > 1 ? words[words.length - 1] : '' };
    if (words.length > 2) {
        out.middle_name = words.slice(1, -1).join(' ');
    }
    return out;
}

// ---------------------------------------------------------------- the MRZ

/** ICAO 9303 check digit: weights 7-3-1, A=10 … Z=35, '<'=0. */
export function checkDigit(field) {
    const weights = [7, 3, 1];
    let total = 0;
    for (let i = 0; i < field.length; i += 1) {
        const c = field[i];
        let v = 0;
        if (c >= '0' && c <= '9') {
            v = c.charCodeAt(0) - 48;
        } else if (c >= 'A' && c <= 'Z') {
            v = c.charCodeAt(0) - 55;
        }
        total += v * weights[i % 3];
    }
    return String(total % 10);
}

const checks = (field, digit) => checkDigit(field) === toDigits(digit ?? '');

/** YYMMDD → ISO, choosing the century that keeps a birth date in the past. */
function mrzBirthDate(yymmdd, today = new Date()) {
    const s = toDigits(yymmdd);
    if (!/^\d{6}$/.test(s)) {
        return null;
    }
    const yy = Number(s.slice(0, 2));
    const century = 2000 + yy > today.getUTCFullYear() ? 1900 : 2000;
    return isoDate(century + yy, s.slice(2, 4), s.slice(4, 6), today);
}

function mrzNames(field) {
    // The filler after the names is a run of '<', which recognition often
    // reads as L, K or I; the names stop at the first empty '<<' gap.
    const [surname = '', ...rest] = field.replace(/^<+/, '').replace(/[<LKI]{5,}$/, '').split('<<');
    const given = [];
    for (const part of rest) {
        if (part === '') {
            break;
        }
        given.push(part);
    }
    const clean = (s) => titleCase(s.replace(/</g, ' ').replace(/\s+/g, ' ').trim());
    const givenNames = clean(given.join(' '));
    const last = clean(surname);
    if (!givenNames) {
        return splitName(last);
    }
    // Given names: the first is the first name, any others are middle names.
    const [first, ...middle] = givenNames.split(' ');
    return { first_name: first, ...(middle.length ? { middle_name: middle.join(' ') } : {}), last_name: last };
}

const sexOf = (c) => (c === 'M' ? 'male' : c === 'F' ? 'female' : undefined);

/** @returns {IdFields|null} */
export function parseMrz(text, today = new Date()) {
    const lines = String(text || '')
        .toUpperCase()
        .replace(/[«‹]/g, '<')
        .split('\n')
        .map((l) => l.replace(/\s+/g, '').replace(/[^A-Z0-9<]/g, ''))
        .filter((l) => l.length >= 28 && (l.match(/</g) || []).length >= 2);

    // Passport, TD3: two lines of 44.
    for (let i = 0; i + 1 < lines.length; i += 1) {
        const l1 = lines[i];
        const l2 = lines[i + 1];
        // Every field used here sits in the first 21 characters of line two;
        // recognition often drops some of the '<' filler after them.
        if (!/^P[A-Z<]/.test(l1) || l2.length < 21 || !/^[A-Z0-9<]{9}\d/.test(l2)) {
            continue;
        }
        const out = { source: 'mrz', id_type: 'passport', ...mrzNames(l1.slice(5)) };
        const doc = l2.slice(0, 9);
        if (checks(doc, l2[9])) {
            out.passport = doc.replace(/</g, '');
        }
        const dob = l2.slice(13, 19);
        if (checks(toDigits(dob), l2[19])) {
            const iso = mrzBirthDate(dob, today);
            if (iso) {
                out.dob = iso;
            }
        }
        const sex = sexOf(l2[20]);
        if (sex) {
            out.gender = sex;
        }
        return out;
    }

    // ID card, TD1: three lines of 30.
    for (let i = 0; i + 2 < lines.length; i += 1) {
        const [l1, l2, l3] = [lines[i], lines[i + 1], lines[i + 2]];
        if (!/^[IAC][A-Z<]/.test(l1)) {
            continue;
        }
        const out = { source: 'mrz', ...mrzNames(l3) };
        const doc = l1.slice(5, 14);
        const docOk = checks(doc, l1[14]);
        const nid = [doc.replace(/</g, ''), l1.slice(15, 30).replace(/</g, '')].find((v) => /^A\d{6}$/.test(v));
        if (nid && (docOk || nid !== doc.replace(/</g, ''))) {
            out.id_type = 'national_id';
            out.national_id = nid;
        } else if (docOk) {
            out.id_type = 'passport';
            out.passport = doc.replace(/</g, '');
        }
        const dob = l2.slice(0, 6);
        if (checks(toDigits(dob), l2[6])) {
            const iso = mrzBirthDate(dob, today);
            if (iso) {
                out.dob = iso;
            }
        }
        const sex = sexOf(l2[7]);
        if (sex) {
            out.gender = sex;
        }
        return out;
    }

    return null;
}

// ----------------------------------------------------- the printed text

function findNationalId(text) {
    const tokens = text.split(/[^A-Za-z0-9|]+/).filter(Boolean);
    for (let i = 0; i < tokens.length; i += 1) {
        const t = tokens[i];
        if (/^[Aa][0-9OoIlSB|]{6}$/.test(t)) {
            const digits = toDigits(t.slice(1));
            if (/^\d{6}$/.test(digits)) {
                return `A${digits}`;
            }
        }
        if (/^[Aa]$/.test(t) && /^[0-9OoIlSB|]{6}$/.test(tokens[i + 1] ?? '')) {
            const digits = toDigits(tokens[i + 1]);
            if (/^\d{6}$/.test(digits)) {
                return `A${digits}`;
            }
        }
    }
    return null;
}

function findPassportNumber(text) {
    const m = text.match(/passport\s*(?:no\.?|number|#)?\s*[:.]?\s*([A-Z]{1,2}\s?\d{6,8})/i);
    return m ? m[1].replace(/\s/g, '').toUpperCase() : null;
}

function findDates(text, today) {
    const found = [];
    const add = (index, y, m, d) => {
        const iso = isoDate(y, m, d, today);
        if (iso) {
            found.push({ index, iso });
        }
    };
    for (const m of text.matchAll(/(\d{1,2})\s?[./-]\s?(\d{1,2})\s?[./-]\s?(\d{4})/g)) {
        // Day first, as dates are written in the Maldives; a "month" over 12 means the other way round.
        if (Number(m[2]) > 12 && Number(m[1]) <= 12) {
            add(m.index, m[3], m[1], m[2]);
        } else {
            add(m.index, m[3], m[2], m[1]);
        }
    }
    for (const m of text.matchAll(/(\d{4})[./-](\d{1,2})[./-](\d{1,2})/g)) {
        add(m.index, m[1], m[2], m[3]);
    }
    for (const m of text.matchAll(/(\d{1,2})[\s./-]*(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEPT?|OCT|NOV|DEC)[A-Z]*[\s./-]*(\d{4})/gi)) {
        add(m.index, m[3], MONTHS[m[2].toUpperCase()], m[1]);
    }
    return found;
}

// A date just after one of these is the card's own date, never a birth date.
const CARD_DATE_LABEL = /expir|issue|valid|until/gi;

function findBirthDate(text, today) {
    const cardDates = [...text.matchAll(CARD_DATE_LABEL)].map((m) => m.index);
    const dates = findDates(text, today)
        .filter((d) => !cardDates.some((at) => d.index >= at && d.index - at < 40));
    if (dates.length === 0) {
        return null;
    }
    const label = text.search(/birth|d\.?\s?o\.?\s?b/i);
    if (label >= 0) {
        const after = dates.filter((d) => d.index >= label && d.index - label < 80).sort((a, b) => a.index - b.index);
        if (after.length) {
            return after[0].iso;
        }
    }
    // No label: of what is left, a birth is the earliest date.
    return dates.map((d) => d.iso).sort()[0];
}

const sexWord = (w) => (/^f(emale)?$/i.test(w) ? 'female' : /^m(ale)?$/i.test(w) ? 'male' : null);

function findGender(text) {
    const labelled = text.match(/\b(?:sex|gender)\b\s*[:/\-.]?\s*(male|female|m|f)\b/i);
    if (labelled) {
        return sexWord(labelled[1]);
    }
    const lines = text.split('\n').map((l) => l.trim());
    // The label on one line, the letter at the start of the next — the
    // Maldivian card prints "Sex" over "M", beside "Date of Birth" over the date.
    for (let i = 0; i + 1 < lines.length; i += 1) {
        if (/\bsex\b/i.test(lines[i])) {
            const first = sexWord(lines[i + 1].split(/\s+/)[0] ?? '');
            if (first) {
                return first;
            }
        }
    }
    // Recognition often drops that label line and keeps the values: a lone
    // M or F opening the line that holds the birth date.
    for (const line of lines) {
        const m = line.match(/^([MF])\b[^A-Za-z0-9]*(?:\S+\s+){0,2}\d{1,2}\s?[./-]\s?\d{1,2}\s?[./-]\s?\d{4}/);
        if (m) {
            return sexWord(m[1]);
        }
    }
    if (/\bfemale\b/i.test(text)) {
        return 'female';
    }
    if (/\bmale\b/i.test(text)) {
        return 'male';
    }
    return null;
}

/**
 * The name-like part of a line, or null. Recognition leaves marks at the
 * ends ("Ibrahim™") and junk after the name, which on the Maldivian card is
 * printed in Title Case: a mixed-case line keeps its leading capitalised
 * words; an all-capitals line keeps them all.
 */
function nameIn(s) {
    const words = String(s || '')
        .split(/\s+/)
        .map((w) => w.replace(/^[^A-Za-z]+|[^A-Za-z.'-]+$/g, ''))
        .filter(Boolean);
    if (words.length === 0) {
        return null;
    }
    let kept = words;
    const capital = (w) => /^[A-Z]/.test(w);
    if (words.some((w) => /^[a-z]/.test(w))) {
        const end = words.findIndex((w) => !capital(w));
        kept = end === -1 ? words : words.slice(0, end);
    }
    if (kept.length === 0 || kept.length > 6) {
        return null;
    }
    const ok = kept.every((w) => /^[A-Za-z][A-Za-z.'-]*$/.test(w)
        && w.replace(/[.'-]/g, '').length >= 2
        && /[aeiouy]/i.test(w)
        && !NOT_A_NAME.has(w.toUpperCase().replace(/[.'-]/g, '')));
    return ok ? kept.join(' ') : null;
}

function findName(text) {
    const lines = text.split('\n').map((l) => l.trim()).filter(Boolean);
    for (let i = 0; i < lines.length; i += 1) {
        if (/\bname\b/i.test(lines[i]) && !/\b(?:father|mother|parent|common|other)\b/i.test(lines[i])) {
            const rest = nameIn(lines[i].replace(/.*\bname\b\s*[:\-.]?\s*/i, ''));
            if (rest && rest.split(' ').length >= 2) {
                return rest;
            }
            // The Maldivian card puts the Dhivehi name beside the label and the
            // English one a line or few below; recognition adds noise between.
            for (let j = i + 1; j < Math.min(lines.length, i + 5); j += 1) {
                if (/\b(?:sex|date|birth|address)\b/i.test(lines[j])) {
                    break;
                }
                const found = nameIn(lines[j]);
                if (found && found.split(' ').length >= 2) {
                    return found;
                }
            }
        }
    }
    // No label read: the first line of two or more name-like words, above the
    // address — but only from a reading that is plainly of a card. Noise from
    // a sideways or blurred photo makes word-shaped lines too.
    if (!/republic|maldives|identity|passport|national/i.test(text) && !findNationalId(text) && !findDates(text, new Date()).length) {
        return null;
    }
    for (const line of lines) {
        if (/\baddre/i.test(line)) {
            break;
        }
        const found = nameIn(line);
        if (found && found.split(' ').length >= 2) {
            return found;
        }
    }
    return null;
}

/**
 * Everything the text says, MRZ first, the printed text filling the gaps.
 *
 * @param {string} text  what recognition returned for one photo
 * @param {Date} [today]
 * @returns {IdFields}
 */
export function parseIdText(text, today = new Date()) {
    const raw = String(text || '').replace(/\r/g, '');
    const out = { ...(parseMrz(raw, today) ?? {}) };

    if (!out.national_id && !out.passport) {
        const nid = findNationalId(raw);
        const passport = nid ? null : findPassportNumber(raw);
        if (nid) {
            out.id_type = 'national_id';
            out.national_id = nid;
        } else if (passport) {
            out.id_type = 'passport';
            out.passport = passport;
        }
    }
    if (!out.dob) {
        const dob = findBirthDate(raw, today);
        if (dob) {
            out.dob = dob;
        }
    }
    if (!out.gender) {
        const gender = findGender(raw);
        if (gender) {
            out.gender = gender;
        }
    }
    if (!out.first_name) {
        const name = findName(raw);
        if (name) {
            Object.assign(out, splitName(name));
        }
    }
    if (!out.source && Object.keys(out).length) {
        out.source = 'text';
    }
    return out;
}

/** Merge what two photos (front and back) said: the first answer for a field wins. */
export function mergeIdFields(...results) {
    const out = {};
    for (const r of results) {
        for (const [k, v] of Object.entries(r || {})) {
            if (v && !out[k]) {
                out[k] = v;
            }
        }
    }
    return out;
}
