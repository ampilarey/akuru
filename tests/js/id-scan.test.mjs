// C17 slice R2 (STATUS §5od): what the ID-card scan reads from recognised text.
// Run with `node --test tests/js/`; `IdScanParserTest.php` runs it under Pest so CI does too.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { checkDigit, mergeIdFields, parseIdText, parseMrz } from '../../resources/js/id-scan/parse.js';

const TODAY = new Date(Date.UTC(2026, 9, 3));

test('a Maldivian ID card front, as recognition returns it', () => {
    const text = `REPUBLIC OF MALDIVES
NATIONAL IDENTITY CARD
Name
AISHATH SHAZNA ALI
ID No. A123456
Sex: F
Date of Birth: 05-05-2000
Date of Expiry: 01-01-2030`;
    assert.deepEqual(parseIdText(text, TODAY), {
        id_type: 'national_id',
        national_id: 'A123456',
        dob: '2000-05-05',
        gender: 'female',
        first_name: 'Aishath',
        last_name: 'Shazna Ali',
        source: 'text',
    });
});

test('OCR noise: letters read for digits, a space after the A, a name on the label line', () => {
    const text = `Name: MOHAMED IBRAHIM\nID NO : A 1O2S4B\nSEX M\nDOB 7 MAR 1985`;
    const out = parseIdText(text, TODAY);
    assert.equal(out.national_id, 'A102548');
    assert.equal(out.first_name, 'Mohamed');
    assert.equal(out.last_name, 'Ibrahim');
    assert.equal(out.gender, 'male');
    assert.equal(out.dob, '1985-03-07');
});

test('no birth label: the earliest real date is the birth, not the issue or expiry date', () => {
    const out = parseIdText('A654321\n12/03/2021\n15/08/1999\n12/03/2031', TODAY);
    assert.equal(out.dob, '1999-08-15');
});

test('a future date or an impossible one is never a birth date', () => {
    assert.equal(parseIdText('Date of Birth 31/02/2001', TODAY).dob, undefined);
    assert.equal(parseIdText('Date of Birth 01/01/2031', TODAY).dob, undefined);
});

test('a passport machine-readable zone, every field behind its check digit', () => {
    const text = `PASSPORT
P<MDVHASSAN<<MOHAMED<AHMED<<<<<<<<<<<<<<<<<<
LA12345675MDV9005053M3001019<<<<<<<<<<<<<<02`;
    assert.deepEqual(parseIdText(text, TODAY), {
        source: 'mrz',
        id_type: 'passport',
        first_name: 'Mohamed Ahmed',
        last_name: 'Hassan',
        passport: 'LA1234567',
        dob: '1990-05-05',
        gender: 'male',
    });
});

test('the filler after the names, misread as letters, is not a name', () => {
    const out = parseMrz(`P<MDVHASSAN<<MOHAMED<AHMED<<<<<<<LLLLLKLLILLLL
LA12345675MDV9005053M3001019<<<<<<<<<<<<<<02`, TODAY);
    assert.equal(out.first_name, 'Mohamed Ahmed');
    assert.equal(out.last_name, 'Hassan');
    assert.equal(out.passport, 'LA1234567');
});

test('line two with some of its filler dropped, as a browser read it', () => {
    const out = parseIdText(`PASSPORT - REPUBLIC OF MALDIVES

Surname HASSAN Given names MOHAMED AHMED
P<MDVHASSAN<<MOHAMED<AHMED<<<<<<<KLL IIL
LA12345675MDV9005053M3001019<<<<<<<<<P2
`, TODAY);
    assert.equal(out.passport, 'LA1234567');
    assert.equal(out.dob, '1990-05-05');
    assert.equal(out.gender, 'male');
    assert.equal(out.first_name, 'Mohamed Ahmed');
});

test('a wrong check digit drops that field instead of guessing', () => {
    const mrz = parseMrz(`P<MDVHASSAN<<MOHAMED<<<<<<<<<<<<<<<<<<<<<<<<<
LA12345679MDV9005058M3001019<<<<<<<<<<<<<<02`, TODAY);
    assert.equal(mrz.passport, undefined);
    assert.equal(mrz.dob, undefined);
    assert.equal(mrz.gender, 'male');
});

test('an ID card machine-readable zone (three lines) carrying the national ID', () => {
    const out = parseIdText(`IDMDVA123456<<5<<<<<<<<<<<<<<<
0005050F3001019MDV<<<<<<<<<<<0
ALI<<AISHATH<SHAZNA<<<<<<<<<<<`, TODAY);
    assert.equal(out.national_id, 'A123456');
    assert.equal(out.id_type, 'national_id');
    assert.equal(out.dob, '2000-05-05');
    assert.equal(out.gender, 'female');
    assert.equal(out.first_name, 'Aishath Shazna');
    assert.equal(out.last_name, 'Ali');
});

test('a printed passport number when there is no machine zone', () => {
    const out = parseIdText('Passport No: LA 1234567\nSurname HASSAN', TODAY);
    assert.equal(out.id_type, 'passport');
    assert.equal(out.passport, 'LA1234567');
});

test('nothing readable comes back empty, not invented', () => {
    assert.deepEqual(parseIdText('', TODAY), {});
    assert.deepEqual(parseIdText('~~ ;; ##', TODAY), {});
});

test('front and back: the first answer for each field wins', () => {
    assert.deepEqual(mergeIdFields({ national_id: 'A111111', dob: '' }, { national_id: 'A222222', dob: '1999-01-01' }), { national_id: 'A111111', dob: '1999-01-01' });
});

test('check digits follow ICAO 9303', () => {
    assert.equal(checkDigit('LA1234567'), '5');
    assert.equal(checkDigit('900505'), '3');
    assert.equal(checkDigit('<<<<<<<<<<<<<<'), '0');
});
