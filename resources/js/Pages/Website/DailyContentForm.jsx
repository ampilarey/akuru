import { Link, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AppShell from '../../Layouts/AppShell';

/**
 * New or edit daily content (W23; C9 slice 9, STATUS §5jk). The type picks
 * the fields; the live preview reads them as they are typed, and for an ayah
 * asks the ayah-preview route for the text and meanings. Saving keeps the
 * item a draft: a second reviewer approves from the queue. Keyed on the
 * item, because new and edit share the component and Inertia keeps it
 * mounted across the redirect (see STATUS §5jj).
 */
const TYPES = ['ayah', 'hadith', 'saying', 'reminder'];
const GRADES = ['sahih', 'hasan', 'daif'];
const humanize = (value) => (value || '').replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase());

export default function DailyContentForm(props) {
    return <DailyContentFormBody key={props.item?.id ?? 'new'} {...props} />;
}

function DailyContentFormBody({ item = null, type = 'ayah', t = {} }) {
        const editing = item !== null;
    const form = useForm({
        content_type: item?.content_type || type || 'ayah',
        publish_date: item?.publish_date || '',
        surah_number: item?.ayah?.surah_number ?? 1,
        ayah_number: item?.ayah?.ayah_number ?? 1,
        hadith_text_ar: item?.hadith_text_ar || '',
        hadith_text_en: item?.hadith_text_en || '',
        hadith_text_dv: item?.hadith_text_dv || '',
        hadith_collection: item?.hadith_collection || '',
        hadith_number: item?.hadith_number || '',
        hadith_grading: item?.hadith_grading || '',
        grading_source: item?.grading_source || '',
        text_en: item?.text_en || '',
        text_dv: item?.text_dv || '',
        text_ar: item?.text_ar || '',
        attribution: item?.attribution || '',
        theme_tag: item?.theme_tag || '',
        notes_internal: item?.notes_internal || '',
        archived: item?.status === 'archived',
    });
    const d = form.data;
    const [ayahPreview, setAyahPreview] = useState(null);
    const timer = useRef(null);
    const typeLabel = (value) => t[`subs_type_${value}`] || humanize(value);

    // The ayah preview: the route answers JSON for a surah and ayah number, debounced as the numbers change.
    useEffect(() => {
        if (d.content_type !== 'ayah') return undefined;
        clearTimeout(timer.current);
        timer.current = setTimeout(() => {
            fetch(`/admin/public-site/daily-content/ayah-preview?surah_number=${encodeURIComponent(d.surah_number)}&ayah_number=${encodeURIComponent(d.ayah_number)}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then((r) => r.json())
                .then((payload) => setAyahPreview(payload || { missing: true }))
                .catch(() => setAyahPreview({ failed: true }));
        }, 250);
        return () => clearTimeout(timer.current);
    }, [d.content_type, d.surah_number, d.ayah_number]);

    const preview = (() => {
        if (d.content_type === 'hadith') return { ar: d.hadith_text_ar, en: d.hadith_text_en, dv: d.hadith_text_dv, meta: [d.hadith_collection, d.hadith_number, d.hadith_grading, d.grading_source].filter(Boolean).join(' · ') };
        if (d.content_type === 'saying' || d.content_type === 'reminder') return { ar: d.text_ar, en: d.text_en, dv: d.text_dv, meta: d.attribution };
        if (!ayahPreview) return { ar: '', en: '', dv: '', meta: '' };
        if (ayahPreview.missing) return { ar: '', en: t.daily_ayah_missing || 'That ayah is not in the active mushaf.', dv: '', meta: '' };
        if (ayahPreview.failed) return { ar: '', en: t.daily_ayah_failed || 'Could not load ayah preview.', dv: '', meta: '' };
        return { ar: ayahPreview.text_uthmani || '', en: ayahPreview.meanings?.en || '', dv: ayahPreview.meanings?.dv || '', meta: ayahPreview.meaning_source || '' };
    })();

    const submit = (e) => {
        e.preventDefault();
        form.transform((data) => {
            const { archived, ...rest } = data;
            return editing ? { ...rest, status: archived ? 'archived' : 'draft' } : rest;
        });
        if (editing) form.put(`/admin/public-site/daily-content/${item.id}`, { preserveScroll: true });
        else form.post('/admin/public-site/daily-content', { preserveScroll: true });
    };
    const firstError = Object.values(form.errors)[0];
    const field = (name, label, props = {}) => (
        <div>
            <label className="mb-1 block text-xs text-gray-600" htmlFor={`daily-${name}`}>{label}</label>
            <input id={`daily-${name}`} name={name} className="form-input w-full" value={d[name]} onChange={(e) => form.setData(name, e.target.value)} {...props} />
            {form.errors[name] && <p className="mt-1 text-xs text-red-700">{form.errors[name]}</p>}
        </div>
    );
    const area = (name, label, props = {}) => (
        <div>
            <label className="mb-1 block text-xs text-gray-600" htmlFor={`daily-${name}`}>{label}</label>
            <textarea id={`daily-${name}`} name={name} rows="2" className="form-input w-full" value={d[name]} onChange={(e) => form.setData(name, e.target.value)} {...props} />
            {form.errors[name] && <p className="mt-1 text-xs text-red-700">{form.errors[name]}</p>}
        </div>
    );

    return (
        <AppShell title={editing ? (t.daily_edit_title || 'Edit daily content') : (t.daily_new_title || 'New daily content')}>
            <p className="mb-4 text-sm"><Link href="/admin/public-site/daily-content" className="text-gray-500 underline" data-testid="daily-back">{t.daily_back || '← Daily content'}</Link></p>
            {firstError && <p className="mb-4 rounded bg-red-50 p-3 text-sm text-red-700" data-testid="daily-error">✗ {firstError}</p>}

            <form onSubmit={submit} className="max-w-3xl space-y-4 rounded-lg border bg-white p-6" data-testid="daily-form">
                <div className="grid gap-4 md:grid-cols-2">
                    <div>
                        <label className="mb-1 block text-xs text-gray-600" htmlFor="daily-content_type">{t.daily_type || 'Type'}</label>
                        {/* The type is fixed once saved: the fields it chose are the row's. */}
                        <select id="daily-content_type" name="content_type" className="form-input w-full" value={d.content_type} disabled={editing} onChange={(e) => form.setData('content_type', e.target.value)}>
                            {TYPES.map((value) => <option key={value} value={value}>{typeLabel(value)}</option>)}
                        </select>
                        {form.errors.content_type && <p className="mt-1 text-xs text-red-700">{form.errors.content_type}</p>}
                    </div>
                    {field('publish_date', t.daily_publish_date || 'Publish date', { type: 'date', required: true })}
                </div>

                {d.content_type === 'ayah' && (
                    <div className="grid grid-cols-2 gap-4" data-testid="daily-fields-ayah">
                        {field('surah_number', t.daily_surah || 'Surah number', { type: 'number', min: 1, max: 114 })}
                        {field('ayah_number', t.daily_ayah || 'Ayah number', { type: 'number', min: 1 })}
                    </div>
                )}
                {d.content_type === 'hadith' && (
                    <div className="space-y-3" data-testid="daily-fields-hadith">
                        {area('hadith_text_ar', t.daily_arabic || 'Arabic', { dir: 'rtl' })}
                        {area('hadith_text_en', t.daily_english || 'English')}
                        {area('hadith_text_dv', t.daily_dhivehi || 'Dhivehi', { dir: 'rtl' })}
                        <div className="grid gap-4 md:grid-cols-2">
                            {field('hadith_collection', t.daily_collection || 'Collection (Bukhari, Muslim, …)', { type: 'text' })}
                            {field('hadith_number', t.daily_number || 'Number', { type: 'text' })}
                            <div>
                                <label className="mb-1 block text-xs text-gray-600" htmlFor="daily-hadith_grading">{t.daily_grading || 'Grading'}</label>
                                <select id="daily-hadith_grading" name="hadith_grading" className="form-input w-full" value={d.hadith_grading} onChange={(e) => form.setData('hadith_grading', e.target.value)}>
                                    <option value="">{t.daily_grading || 'Grading'}</option>
                                    {GRADES.map((g) => <option key={g} value={g}>{t[`daily_grade_${g}`] || g}</option>)}
                                </select>
                                {form.errors.hadith_grading && <p className="mt-1 text-xs text-red-700">{form.errors.hadith_grading}</p>}
                            </div>
                            {field('grading_source', t.daily_grading_source || 'Grading source', { type: 'text' })}
                        </div>
                    </div>
                )}
                {(d.content_type === 'saying' || d.content_type === 'reminder') && (
                    <div className="space-y-3" data-testid="daily-fields-text">
                        {area('text_en', t.daily_english || 'English')}
                        {area('text_dv', t.daily_dhivehi || 'Dhivehi', { dir: 'rtl' })}
                        {area('text_ar', t.daily_arabic_optional || 'Arabic (optional)', { dir: 'rtl' })}
                        {field('attribution', t.daily_attribution_source || 'Attribution / source', { type: 'text' })}
                    </div>
                )}

                {field('theme_tag', t.daily_theme_tag || 'Theme tag', { type: 'text' })}
                {area('notes_internal', t.daily_notes || 'Internal notes')}
                {editing && (
                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="archived" checked={d.archived} onChange={(e) => form.setData('archived', e.target.checked)} data-testid="daily-archive" />
                        {t.daily_archive || 'Archive'}
                    </label>
                )}

                <div className="space-y-2 rounded-lg bg-gray-50 p-4 text-sm" data-testid="daily-preview">
                    <p className="text-xs uppercase tracking-wide text-gray-500">{t.daily_live_preview || 'Live preview'}</p>
                    <p className="text-xl leading-loose" dir="rtl" data-testid="preview-ar">{preview.ar}</p>
                    <p dir="ltr" data-testid="preview-en">{preview.en}</p>
                    <p dir="rtl" data-testid="preview-dv">{preview.dv}</p>
                    <p className="text-xs text-gray-500" data-testid="preview-meta">{preview.meta}</p>
                </div>

                <div className="flex items-center gap-3">
                    <button className="btn-primary" type="submit" disabled={form.processing} data-testid="daily-save">{t.daily_save || 'Save draft'}</button>
                    <Link href="/admin/public-site/daily-content" className="text-sm text-gray-500 underline">{t.research_back_short || 'Back'}</Link>
                </div>
            </form>
        </AppShell>
    );
}
