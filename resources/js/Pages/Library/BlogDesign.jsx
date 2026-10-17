import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * BLOG_PLAN BL3a (§5): the blog page designer. A theme from the gallery, an
 * accent, a heading font, the header's words, the languages and the sections
 * in order — saved as a draft, sent to the office, live once approved. The
 * preview shows the page as readers will, left to right or right to left.
 * The colours are checked here for the reader's sake and again on the server.
 */
const luminance = (hex) => {
    const value = /^#([0-9a-f]{6})$/i.exec(hex || '');
    if (!value) return 0;
    const channel = (i) => {
        const c = parseInt(value[1].slice(i, i + 2), 16) / 255;
        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    };
    return 0.2126 * channel(0) + 0.7152 * channel(2) + 0.0722 * channel(4);
};
const ratio = (a, b) => {
    const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);
    return Math.round(((hi + 0.05) / (lo + 0.05)) * 100) / 100;
};
const fill = (text, values) => Object.entries(values).reduce((out, [key, value]) => out.split(`:${key}`).join(String(value ?? '')), String(text));

export default function BlogDesign({ blog, themes = [], fonts = [], limits = {}, live, draft = null, state = 'live', decision_note: decisionNote = null, posts = [], t = {} }) {
    const start = draft || live;
    const form = useForm({ ...start, accent: start.accent || '', font: start.font || '', tagline: start.tagline || '', about: start.about || '' });
    const [dir, setDir] = useState('ltr');
    const waiting = state === 'waiting';
    const theme = themes.find((x) => x.key === form.data.theme) || themes[0] || { colors: {} };
    const accent = /^#[0-9a-f]{6}$/i.test(form.data.accent) ? form.data.accent.toUpperCase() : theme.colors.accent;
    const onAccent = form.data.accent ? (ratio('#FFFFFF', accent) >= ratio('#15151A', accent) ? '#FFFFFF' : '#15151A') : theme.colors.on_accent;
    const reads = ratio(accent, theme.colors.page_bg) >= 3 && ratio(accent, theme.colors.card_bg) >= 3 && ratio(onAccent, accent) >= 4.5;
    const move = (index, step) => {
        const sections = [...form.data.sections];
        const [row] = sections.splice(index, 1);
        sections.splice(index + step, 0, row);
        form.setData('sections', sections);
    };
    const toggleLanguage = (code) => form.setData('languages', form.data.languages.includes(code) ? form.data.languages.filter((x) => x !== code) : [...form.data.languages, code]);
    const preview = {
        '--blog-page': theme.colors.page_bg, '--blog-card': theme.colors.card_bg, '--blog-text': theme.colors.text,
        '--blog-accent': accent, '--blog-on-accent': onAccent,
        '--blog-radius': { square: '0', soft: '0.5rem', round: '1.25rem' }[theme.colors.radius] || '0.5rem',
        fontFamily: form.data.font ? `'${form.data.font}', system-ui, sans-serif` : undefined,
    };
    const stateText = { live: t.page_state_live || 'Readers see this design.', draft: t.page_state_draft || 'Your changes are saved as a draft. Send them for checking when ready.', waiting: t.page_state_waiting || 'Your changes are with the Library office. Readers see your page as it was until they are approved.' }[state];

    return (
        <AppShell title={t.design_title || 'Design my blog page'}>
            <p className="mb-4 max-w-3xl rounded bg-amber-50 p-3 text-sm text-amber-900" data-testid="design-state" data-state={state}>{stateText}</p>
            {decisionNote && state !== 'waiting' && <p className="mb-4 max-w-3xl rounded bg-red-50 p-3 text-sm text-red-800" data-testid="design-note">{fill(t.request_note || 'From the office: :note', { note: decisionNote })}</p>}

            <div className="grid gap-6 lg:grid-cols-2">
                <form
                    className="grid gap-4 rounded-lg border bg-white p-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post('/my-blog/design', { preserveScroll: true, onSuccess: () => form.setDefaults() });
                    }}
                    data-testid="design-form"
                >
                    <fieldset disabled={waiting} className="grid gap-4">
                        <div>
                            <p className="mb-2 text-sm font-medium">{t.field_theme || 'Theme'}</p>
                            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                {themes.map((x) => (
                                    <label key={x.key} className={`cursor-pointer rounded border p-2 text-sm ${form.data.theme === x.key ? 'ring-2 ring-[#7C2D37]' : ''}`} data-testid={`design-theme-${x.key}`}>
                                        <input type="radio" name="theme" className="sr-only" checked={form.data.theme === x.key} onChange={() => form.setData('theme', x.key)} />
                                        <span className="mb-1 flex gap-1" aria-hidden="true">
                                            {['page_bg', 'card_bg', 'accent', 'text'].map((slot) => <span key={slot} className="h-4 w-4 rounded border" style={{ background: x.colors[slot] }} />)}
                                        </span>
                                        {x.name}
                                    </label>
                                ))}
                            </div>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <label className="block text-sm">
                                <span className="mb-1 block font-medium">{t.field_accent || 'Accent colour'}</span>
                                <span className="flex items-center gap-2">
                                    <input type="color" value={accent || '#000000'} onChange={(e) => form.setData('accent', e.target.value)} aria-label={t.field_accent || 'Accent colour'} data-testid="design-accent-picker" />
                                    <input className="form-input w-28" value={form.data.accent} placeholder={theme.colors.accent} onChange={(e) => form.setData('accent', e.target.value)} maxLength={7} aria-label={t.field_accent || 'Accent colour'} data-testid="design-accent" />
                                    {form.data.accent && <button type="button" className="text-xs underline" onClick={() => form.setData('accent', '')}>{t.accent_reset || 'Use the theme’s'}</button>}
                                </span>
                                {!reads && <span className="mt-1 block text-xs text-red-700" data-testid="design-contrast">{t.contrast_warning || 'This colour does not stand out enough to read. Choose a darker or lighter one.'}</span>}
                            </label>
                            <label className="block text-sm">
                                <span className="mb-1 block font-medium">{t.field_font || 'Heading font'}</span>
                                <select className="form-input w-full" value={form.data.font} onChange={(e) => form.setData('font', e.target.value)} data-testid="design-font">
                                    <option value="">{t.font_default || 'The site’s own'}</option>
                                    {fonts.map((f) => <option key={f} value={f}>{f}</option>)}
                                </select>
                            </label>
                        </div>
                        <label className="block text-sm">
                            <span className="mb-1 block font-medium">{t.field_tagline || 'Tagline'}</span>
                            <input className="form-input w-full" value={form.data.tagline} onChange={(e) => form.setData('tagline', e.target.value)} maxLength={limits.tagline || 120} data-testid="design-tagline" />
                        </label>
                        <label className="block text-sm">
                            <span className="mb-1 block font-medium">{t.field_about_me || 'About me'}</span>
                            <textarea className="form-input w-full" rows={4} value={form.data.about} onChange={(e) => form.setData('about', e.target.value)} maxLength={limits.about || 1500} data-testid="design-about" />
                            <span className="mt-1 block text-xs text-gray-500">{t.about_help || 'Plain text. No contact details: no phone number, address or school email.'}</span>
                        </label>
                        <div className="text-sm">
                            <p className="mb-1 font-medium">{t.field_languages || 'I write in'}</p>
                            <div className="flex flex-wrap gap-3">
                                {['en', 'dv', 'ar'].map((code) => (
                                    <label key={code} className="flex items-center gap-1">
                                        <input type="checkbox" checked={form.data.languages.includes(code)} onChange={() => toggleLanguage(code)} data-testid={`design-language-${code}`} />
                                        {t[`lang_${code}`] || code}
                                    </label>
                                ))}
                            </div>
                        </div>
                        <div className="text-sm">
                            <p className="mb-1 font-medium">{t.field_sections || 'Sections, in order'}</p>
                            <ol className="grid gap-1">
                                {form.data.sections.map((row, index) => (
                                    <li key={row.key} className="flex items-center gap-2 rounded border px-2 py-1" data-testid={`design-section-${row.key}`}>
                                        <input
                                            type="checkbox"
                                            checked={row.on}
                                            onChange={(e) => form.setData('sections', form.data.sections.map((s) => (s.key === row.key ? { ...s, on: e.target.checked } : s)))}
                                            aria-label={t[`section_${row.key}`] || row.key}
                                            data-testid={`design-section-on-${row.key}`}
                                        />
                                        <span className="flex-1">{t[`section_${row.key}`] || row.key}</span>
                                        <button type="button" className="px-2" disabled={index === 0} onClick={() => move(index, -1)} aria-label={t.move_up || 'Move up'} data-testid={`design-section-up-${row.key}`}>↑</button>
                                        <button type="button" className="px-2" disabled={index === form.data.sections.length - 1} onClick={() => move(index, 1)} aria-label={t.move_down || 'Move down'}>↓</button>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </fieldset>
                    <FormErrors errors={form.errors} />
                    {!waiting && (
                        <div className="flex flex-wrap gap-2">
                            <button type="submit" className="btn-primary" disabled={form.processing} data-testid="design-save">{t.save_draft || 'Save draft'}</button>
                            {state === 'draft' && !form.isDirty && <button type="button" className="btn-secondary" onClick={() => router.post('/my-blog/design/submit', {}, { preserveScroll: true })} data-testid="design-submit">{t.submit_post || 'Send for checking'}</button>}
                        </div>
                    )}
                </form>

                <section aria-label={t.preview || 'Preview'}>
                    <div className="mb-2 flex items-center gap-2 text-sm">
                        <span className="font-medium">{t.preview || 'Preview'}</span>
                        <button type="button" className={`rounded px-2 py-0.5 ${dir === 'ltr' ? 'bg-gray-200' : ''}`} onClick={() => setDir('ltr')} data-testid="preview-ltr">{t.preview_ltr || 'Left to right'}</button>
                        <button type="button" className={`rounded px-2 py-0.5 ${dir === 'rtl' ? 'bg-gray-200' : ''}`} onClick={() => setDir('rtl')} data-testid="preview-rtl">{t.preview_rtl || 'Right to left'}</button>
                    </div>
                    <div dir={dir} className="rounded-lg border p-5" style={{ ...preview, background: 'var(--blog-page)', color: 'var(--blog-text)' }} data-testid="design-preview">
                        <h2 className="text-2xl font-bold" style={{ fontFamily: 'var(--blog-font-heading, inherit)', color: 'var(--blog-accent)' }}>{blog.blog_title || blog.pen_name}</h2>
                        {form.data.tagline && <p className="mb-2" data-testid="preview-tagline">{form.data.tagline}</p>}
                        <p className="mb-3 text-sm opacity-80">{fill(t.blog_by || 'by :name', { name: blog.pen_name })}</p>
                        {form.data.sections.filter((s) => s.on).map((s) => (
                            <div key={s.key} className="mb-3 p-3" style={{ background: 'var(--blog-card)', borderRadius: 'var(--blog-radius)' }} data-testid={`preview-section-${s.key}`}>
                                <p className="text-sm font-semibold" style={{ color: 'var(--blog-accent)' }}>{t[`section_${s.key}`] || s.key}</p>
                                {s.key === 'about' && form.data.about && <p className="whitespace-pre-line text-sm">{form.data.about}</p>}
                                {['latest', 'pinned'].includes(s.key) && posts.slice(0, s.key === 'pinned' ? 1 : 3).map((p) => <p key={p.title} className="text-sm">{p.title}</p>)}
                            </div>
                        ))}
                        <span className="inline-block px-3 py-1 text-sm" style={{ background: 'var(--blog-accent)', color: 'var(--blog-on-accent)', borderRadius: 'var(--blog-radius)' }}>{t.preview_button || 'Read'}</span>
                    </div>
                </section>
            </div>
        </AppShell>
    );
}
