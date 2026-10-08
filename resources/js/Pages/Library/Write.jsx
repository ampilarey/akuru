import { router, useForm, usePage } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import AppShell from '../../Layouts/AppShell';
import BodyEditor from '../../Components/BodyEditor';
import FormErrors, { useRowRefusals } from '../../Components/FormErrors';
import { DeliveryChoice, TeacherAuthors, defaultDelivery } from '../../Components/LibraryAuthoring';
import ReviewStateChip from '../../Components/ReviewStateChip';
import { IdentityCardFields, IdentityCardUpload } from '../../Components/IdentityCard';

// Every word on the page is a `common` phrase (EN/DV/AR, slice LT2).
const fill = (text, values) => Object.entries(values).reduce((out, [key, value]) => out.split(`:${key}`).join(String(value ?? '')), String(text));

// A phrase with an element in it — a link, a number box — where the phrase
// puts it, so a language that orders the sentence differently still can.
const withNodes = (text, nodes) => String(text).split(/(:[a-z_]+)/).map((part, index) => {
    const name = part.startsWith(':') ? part.slice(1) : null;

    return name !== null && nodes[name] !== undefined ? <Fragment key={index}>{nodes[name]}</Fragment> : part;
});

const money = (t, amount) => fill(t.library_money || 'MVR :amount', { amount });

// The toolbar's labels, from the common tranche (EN/DV/AR).
const editorLabels = (t) => ({
    toolbar: t.library_editor_toolbar,
    bold: t.library_editor_bold,
    italic: t.library_editor_italic,
    heading: t.library_editor_heading,
    subheading: t.library_editor_subheading,
    bullets: t.library_editor_bullets,
    numbers: t.library_editor_numbers,
    quote: t.library_editor_quote,
    link: t.library_editor_link,
    link_prompt: t.library_editor_link_prompt,
    page_break: t.library_editor_page_break,
    source: t.library_editor_source,
});

// The ApplyForm says these beside their fields; the rest it lists.
const APPLY_INLINE = ['display_name', 'photo', 'agreement_accepted', 'application', 'id_front', 'id_back'];

function ApplyForm({ t, idL, actOn }) {
    const form = useForm({
        display_name: '',
        bio: '',
        qualifications: '',
        expertise: '',
        motivation: '',
        // B9 (§11.1): the optional extras — what they have published, a
        // portrait for the author page, an identity document for the office.
        previous_publications: '',
        photo: null,
        id_front: null,
        id_back: null,
        agreement_accepted: false,
    });
    const link = (href, label) => <a className="text-[#7C2D37] underline" href={href} target="_blank" rel="noopener">{label}</a>;

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                actOn('apply', () => form.post('/write/apply', { preserveScroll: true, forceFormData: true }));
            }}
            className="grid max-w-2xl gap-3 rounded-lg border bg-white p-4"
        >
            <h2 className="text-lg font-semibold">{t.library_apply_title || 'Apply to publish with Akuru'}</h2>
            <input className="form-input" placeholder={t.library_f_display_name || 'Display name (as shown to readers)'} aria-label={t.library_f_display_name || 'Display name (as shown to readers)'} value={form.data.display_name} onChange={(e) => form.setData('display_name', e.target.value)} />
            {form.errors.display_name && <p className="text-sm text-red-600">{form.errors.display_name}</p>}
            <textarea className="form-input" rows="3" placeholder={t.library_f_bio || 'Bio'} aria-label={t.library_f_bio || 'Bio'} value={form.data.bio} onChange={(e) => form.setData('bio', e.target.value)} />
            <textarea className="form-input" rows="2" placeholder={t.library_f_qualifications || 'Qualifications'} aria-label={t.library_f_qualifications || 'Qualifications'} value={form.data.qualifications} onChange={(e) => form.setData('qualifications', e.target.value)} />
            <input className="form-input" placeholder={t.library_f_expertise || 'Expertise (e.g. Tafsir, Arabic grammar)'} aria-label={t.library_f_expertise || 'Expertise (e.g. Tafsir, Arabic grammar)'} value={form.data.expertise} onChange={(e) => form.setData('expertise', e.target.value)} />
            <textarea className="form-input" rows="3" placeholder={t.library_f_motivation || 'Why do you want to publish with us?'} aria-label={t.library_f_motivation || 'Why do you want to publish with us?'} value={form.data.motivation} onChange={(e) => form.setData('motivation', e.target.value)} />
            <textarea className="form-input" rows="3" placeholder={t.library_apply_publications || 'Previous publications (titles, where, when)'} aria-label={t.library_apply_publications || 'Previous publications (titles, where, when)'} value={form.data.previous_publications} onChange={(e) => form.setData('previous_publications', e.target.value)} data-testid="apply-publications" />
            <label className="grid gap-1 text-sm">
                <span>{t.library_apply_photo || 'Portrait (optional; shown on your author page)'}</span>
                <input className="form-input" type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => form.setData('photo', e.target.files[0] ?? null)} data-testid="apply-photo" />
                {form.errors.photo && <p className="text-red-600">{form.errors.photo}</p>}
            </label>
            {/* COMMERCE_PARITY_PLAN P2: both sides of the ID card, checked by the office. */}
            <IdentityCardFields form={form} l={idL} />
            <label className="flex items-start gap-2 text-sm">
                <input type="checkbox" checked={form.data.agreement_accepted} onChange={(e) => form.setData('agreement_accepted', e.target.checked)} />
                <span data-testid="apply-agreement">
                    {withNodes(t.library_agree_text || 'I own or have permission for everything I upload, accept the :terms, the :agreement and the :refunds, and understand Akuru may remove content on a valid complaint.', {
                        terms: link('/page/publishing-terms', t.library_agree_terms || 'Publishing Terms'),
                        agreement: link('/page/writer-agreement', t.library_agree_agreement || 'Writer Agreement'),
                        refunds: link('/refunds', t.library_agree_refunds || 'refund rules'),
                    })}
                </span>
            </label>
            {form.errors.agreement_accepted && <p className="text-sm text-red-600">{form.errors.agreement_accepted}</p>}
            {form.errors.application && <p className="text-sm text-red-600">{form.errors.application}</p>}
            {/* The fields without a message of their own — a bio past its length. */}
            <FormErrors errors={form.errors} except={APPLY_INLINE} />
            <button type="submit" className="btn-primary justify-self-start" disabled={form.processing}>{t.library_apply_submit || 'Submit application'}</button>
        </form>
    );
}

function ItemEditor({ item, options, onDone, t, actOn }) {
    const form = useForm({
        title: item?.title || '',
        subtitle: item?.subtitle || '',
        content_type: item?.content_type || options.content_types[0] || 'article',
        access_type: item?.access_type || 'free_login',
        price: item?.price ?? '',
        language: item?.language || 'en',
        library_category_id: item?.library_category_id || '',
        description: item?.description || '',
        abstract: item?.abstract || '',
        body: item?.body || '',
        toc: item?.toc || '',
        citations: item?.citations || '',
        affiliation: item?.affiliation || '',
        research_field: item?.research_field || '',
        suggested_reviewer: item?.suggested_reviewer || '',
        // B5 (§8.2–§8.3): how hard, and how long.
        difficulty: item?.difficulty || '',
        reading_time: item?.reading_time ?? '',
        tags_text: (item?.tags || []).join(', '),
        co_authors_text: (item?.co_authors || []).join(', '),
        // R1: teachers named as co-authors, and how readers get it (D1).
        co_author_teachers: item?.co_author_teachers || [],
        delivery: item?.delivery || '',
        preview_enabled: Boolean(item?.preview_enabled),
        preview_pages: item?.preview_pages ?? '',
        declarations: {
            copyright: Boolean(item?.declarations?.copyright),
            ai_use: Boolean(item?.declarations?.ai_use),
            originality: Boolean(item?.declarations?.originality),
            conflict_of_interest: Boolean(item?.declarations?.conflict_of_interest),
            ethics: Boolean(item?.declarations?.ethics),
        },
        pdf: null,
        cover: null,
    });

    const isResearch = form.data.content_type === 'research';
    const isBook = form.data.content_type === 'book';
    const split = (text) => (text ? text.split(',').map((part) => part.trim()).filter(Boolean) : []);
    const declare = (name, value) => form.setData('declarations', { ...form.data.declarations, [name]: value });

    const submit = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, forceFormData: true, onSuccess: onDone };
        form.transform((data) => {
            // Booleans travel as 1/0 in multipart form data; the server
            // validates them as booleans.
            const declarations = Object.fromEntries(Object.entries(data.declarations).map(([key, on]) => [key, on ? 1 : 0]));
            const { tags_text, co_authors_text, delivery, ...rest } = data;

            return {
                ...rest,
                delivery: delivery || defaultDelivery(data.content_type, data.access_type),
                declarations,
                preview_enabled: data.preview_enabled ? 1 : 0,
                tags: split(tags_text),
                // An empty list is sent as '' — multipart drops an empty
                // array, and then clearing every co-author would change nothing.
                co_authors: split(co_authors_text).length ? split(co_authors_text) : '',
                co_author_teachers: data.co_author_teachers.length ? data.co_author_teachers : '',
                ...(item ? { _method: 'put' } : {}),
            };
        });
        actOn('editor', () => (item ? form.post(`/write/items/${item.id}`, opts) : form.post('/write/items', opts)));
    };

    return (
        <form onSubmit={submit} className="mb-4 grid gap-2 rounded-lg border bg-white p-4 md:grid-cols-4" data-testid="draft-editor">
            <input className="form-input md:col-span-2" placeholder={t.library_f_title || 'Title'} aria-label={t.library_f_title || 'Title'} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
            <input className="form-input md:col-span-2" placeholder={t.library_f_subtitle || 'Subtitle'} aria-label={t.library_f_subtitle || 'Subtitle'} value={form.data.subtitle} onChange={(e) => form.setData('subtitle', e.target.value)} />
            <select className="form-input" value={form.data.content_type} onChange={(e) => form.setData('content_type', e.target.value)} aria-label={t.library_f_type || 'Type'}>
                {options.content_types.map((type) => <option key={type} value={type}>{t[`library_type_${type}`] || type.replaceAll('_', ' ')}</option>)}
            </select>
            <select className="form-input" value={form.data.access_type} onChange={(e) => form.setData('access_type', e.target.value)} aria-label={t.library_f_access || 'Access'}>
                <option value="free_public">{t.library_access_free_public || 'free public'}</option>
                <option value="free_login">{t.library_access_free_login || 'free (login)'}</option>
                <option value="paid">{t.library_access_paid || 'paid'}</option>
            </select>
            <input className="form-input" placeholder={t.library_f_price || 'Suggested price (MVR)'} aria-label={t.library_f_price || 'Suggested price (MVR)'} value={form.data.price} onChange={(e) => form.setData('price', e.target.value)} />
            <select className="form-input" value={form.data.language} onChange={(e) => form.setData('language', e.target.value)} aria-label={t.library_f_language || 'Language'}>
                {Object.entries(options.languages || { en: 'English' }).map(([code, label]) => <option key={code} value={code}>{label}</option>)}
            </select>
            <select className="form-input" value={form.data.library_category_id} onChange={(e) => form.setData('library_category_id', e.target.value)} aria-label={t.library_f_category || 'Category'}>
                <option value="">{t.library_f_category_pick || 'Category…'}</option>
                {(options.categories || []).map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
            </select>
            <select className="form-input" value={form.data.difficulty} onChange={(e) => form.setData('difficulty', e.target.value)} aria-label={t.library_f_difficulty || 'Difficulty'}>
                <option value="">{t.library_difficulty_none || 'Difficulty: not set'}</option>
                <option value="beginner">{t.library_difficulty_beginner || 'Beginner'}</option>
                <option value="intermediate">{t.library_difficulty_intermediate || 'Intermediate'}</option>
                <option value="advanced">{t.library_difficulty_advanced || 'Advanced'}</option>
            </select>
            <input className="form-input" type="number" min="1" placeholder={t.library_f_reading_time || 'Reading time (min)'} aria-label={t.library_f_reading_time || 'Reading time (min)'} value={form.data.reading_time} onChange={(e) => form.setData('reading_time', e.target.value)} />
            <input className="form-input" placeholder={t.library_f_keywords || 'Keywords (comma-separated)'} aria-label={t.library_f_keywords || 'Keywords (comma-separated)'} value={form.data.tags_text} onChange={(e) => form.setData('tags_text', e.target.value)} />
            <input className="form-input md:col-span-2" placeholder={t.library_f_co_authors || 'Co-authors (comma-separated)'} aria-label={t.library_f_co_authors || 'Co-authors (comma-separated)'} value={form.data.co_authors_text} onChange={(e) => form.setData('co_authors_text', e.target.value)} />
            <TeacherAuthors className="md:col-span-4" teachers={options.teachers || []} value={form.data.co_author_teachers} onChange={(ids) => form.setData('co_author_teachers', ids)} t={t} />
            <DeliveryChoice
                className="md:col-span-4"
                contentType={form.data.content_type}
                value={form.data.delivery || defaultDelivery(form.data.content_type, form.data.access_type)}
                onChange={(value) => form.setData('delivery', value)}
                hasPdf={Boolean(form.data.pdf || item?.has_pdf)}
                t={t}
            />
            <textarea className="form-input md:col-span-2" rows="2" placeholder={t.library_f_description || 'Description'} aria-label={t.library_f_description || 'Description'} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
            <textarea className="form-input md:col-span-2" rows="2" placeholder={t.library_f_abstract || 'Abstract'} aria-label={t.library_f_abstract || 'Abstract'} value={form.data.abstract} onChange={(e) => form.setData('abstract', e.target.value)} />
            {/* B3: the body is written, not pasted — page breaks from the toolbar. */}
            <BodyEditor className="md:col-span-4" value={form.data.body} onChange={(html) => form.setData('body', html)} placeholder={t.library_editor_body || 'Body — the text readers will read; insert a page break between pages'} labels={editorLabels(t)} />
            {isBook && (
                <textarea className="form-input md:col-span-4" rows="4" placeholder={t.library_f_toc || 'Table of contents (one entry per line)'} aria-label={t.library_f_toc || 'Table of contents (one entry per line)'} value={form.data.toc} onChange={(e) => form.setData('toc', e.target.value)} />
            )}
            {isResearch && (
                <>
                    <textarea className="form-input md:col-span-4" rows="3" placeholder={t.library_f_citations || 'Citations (one per line)'} aria-label={t.library_f_citations || 'Citations (one per line)'} value={form.data.citations} onChange={(e) => form.setData('citations', e.target.value)} />
                    <input className="form-input md:col-span-2" placeholder={t.library_f_affiliation || 'Affiliation'} aria-label={t.library_f_affiliation || 'Affiliation'} value={form.data.affiliation} onChange={(e) => form.setData('affiliation', e.target.value)} />
                    <input className="form-input" placeholder={t.library_f_field || 'Field'} aria-label={t.library_f_field || 'Field'} value={form.data.research_field} onChange={(e) => form.setData('research_field', e.target.value)} />
                    <input className="form-input" placeholder={t.library_f_suggested_reviewer || 'Suggested reviewer (optional)'} aria-label={t.library_f_suggested_reviewer || 'Suggested reviewer (optional)'} value={form.data.suggested_reviewer} onChange={(e) => form.setData('suggested_reviewer', e.target.value)} />
                </>
            )}
            <label className="flex flex-wrap items-center gap-2 text-sm md:col-span-2">
                <input type="checkbox" checked={form.data.preview_enabled} onChange={(e) => form.setData('preview_enabled', e.target.checked)} />
                {withNodes(t.library_f_preview || 'Suggest a free preview of :pages pages', {
                    pages: <input className="form-input w-20" type="number" min="1" value={form.data.preview_pages} onChange={(e) => form.setData('preview_pages', e.target.value)} aria-label={t.library_f_preview_pages || 'Preview pages'} />,
                })}
            </label>
            <label className="text-sm md:col-span-4">
                {t.library_f_cover || 'Cover image (JPEG, PNG or WebP — shown on the shelf)'}
                <input className="form-input" type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => form.setData('cover', e.target.files[0] ?? null)} />
                {item?.cover_url && <img src={item.cover_url} alt="" className="mt-2 h-24 rounded object-cover" data-testid="draft-cover" />}
            </label>
            <label className="text-sm md:col-span-4">
                {t.library_f_pdf || 'Original PDF (stored privately)'}
                <input className="form-input" type="file" accept="application/pdf" onChange={(e) => form.setData('pdf', e.target.files[0] ?? null)} />
                <span className="mt-1 block text-xs text-gray-500">
                    {t.library_f_pdf_help || 'Readers get the PDF page by page, with their name on each page — never the file. A scanned PDF has no text to show; paste the text into the body instead. When both exist, the body is what readers see.'}
                </span>
            </label>
            <fieldset className="md:col-span-4 rounded border p-3" data-testid="declarations">
                <legend className="px-1 text-sm font-medium">{t.library_decl_legend || 'Declarations (required before submitting)'}</legend>
                <label className="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="declarations[copyright]" checked={form.data.declarations.copyright} onChange={(e) => declare('copyright', e.target.checked)} />
                    <span>{t.library_decl_copyright || 'I hold the copyright to this work, or the right to publish it, and it does not infringe anyone else’s.'}</span>
                </label>
                <label className="mt-1 flex items-start gap-2 text-sm">
                    <input type="checkbox" name="declarations[ai_use]" checked={form.data.declarations.ai_use} onChange={(e) => declare('ai_use', e.target.checked)} />
                    <span>{t.library_decl_ai_use || 'AI tools were used in preparing this work (optional; shown to readers).'}</span>
                </label>
                {isResearch && (
                    <>
                        <label className="mt-1 flex items-start gap-2 text-sm">
                            <input type="checkbox" name="declarations[originality]" checked={form.data.declarations.originality} onChange={(e) => declare('originality', e.target.checked)} />
                            <span>{t.library_decl_originality || 'This research is original and not under review or published elsewhere.'}</span>
                        </label>
                        <label className="mt-1 flex items-start gap-2 text-sm">
                            <input type="checkbox" name="declarations[conflict_of_interest]" checked={form.data.declarations.conflict_of_interest} onChange={(e) => declare('conflict_of_interest', e.target.checked)} />
                            <span>{t.library_decl_conflict || 'I have declared any conflict of interest, or have none.'}</span>
                        </label>
                        <label className="mt-1 flex items-start gap-2 text-sm">
                            <input type="checkbox" name="declarations[ethics]" checked={form.data.declarations.ethics} onChange={(e) => declare('ethics', e.target.checked)} />
                            <span>{t.library_decl_ethics || 'Where the research involved people, the required ethics approval was obtained (if applicable).'}</span>
                        </label>
                    </>
                )}
            </fieldset>
            <div className="flex gap-2 self-end">
                <button type="submit" className="btn-primary" disabled={form.processing}>{item ? t.library_update_draft || 'Update draft' : t.library_save_draft || 'Save draft'}</button>
                {onDone && <button type="button" className="btn-secondary" onClick={onDone}>{t.library_close || 'Close'}</button>}
            </div>
            <FormErrors errors={form.errors} className="md:col-span-4" />
        </form>
    );
}

/**
 * L8: what readers see on the author's public page. The address is fixed
 * at approval and kept across renames, so it is shown, not edited. The
 * networks are names; only the website is a word to translate.
 */
const LINK_KEYS = [
    ['website', null], ['facebook', 'Facebook'], ['instagram', 'Instagram'], ['x', 'X'],
    ['youtube', 'YouTube'], ['linkedin', 'LinkedIn'], ['telegram', 'Telegram'],
];

function AuthorPageForm({ profile, items = [], onDone, t, actOn }) {
    const links = profile.social_links || {};
    const form = useForm({
        display_name: profile.display_name || '',
        bio: profile.bio || '',
        qualifications: profile.qualifications || '',
        expertise: profile.expertise || '',
        photo: null,
        featured_item_ids: profile.featured_item_ids || [],
        social_links: Object.fromEntries(LINK_KEYS.map(([key]) => [key, links[key] || ''])),
    });
    // B6: only what readers can already open may be pinned.
    const published = items.filter((item) => item.status === 'published');
    const toggleFeatured = (id) => {
        const current = form.data.featured_item_ids;
        if (current.includes(id)) form.setData('featured_item_ids', current.filter((x) => x !== id));
        else if (current.length < 3) form.setData('featured_item_ids', [...current, id]);
    };

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                actOn('author', () => form.post('/write/profile', { preserveScroll: true, forceFormData: true, onSuccess: onDone }));
            }}
            className="mb-4 grid gap-2 rounded-lg border bg-white p-4 md:grid-cols-2"
            data-testid="author-page-form"
        >
            <h3 className="text-base font-semibold md:col-span-2">{t.library_author_page_title || 'Your author page'}</h3>
            <input className="form-input" placeholder={t.library_f_display_name || 'Display name (as shown to readers)'} aria-label={t.library_f_display_name || 'Display name (as shown to readers)'} value={form.data.display_name} onChange={(e) => form.setData('display_name', e.target.value)} />
            <input className="form-input" placeholder={t.library_f_expertise || 'Expertise (e.g. Tafsir, Arabic grammar)'} aria-label={t.library_f_expertise || 'Expertise (e.g. Tafsir, Arabic grammar)'} value={form.data.expertise} onChange={(e) => form.setData('expertise', e.target.value)} />
            <textarea className="form-input md:col-span-2" rows="3" placeholder={t.library_f_bio || 'Bio'} aria-label={t.library_f_bio || 'Bio'} value={form.data.bio} onChange={(e) => form.setData('bio', e.target.value)} />
            <textarea className="form-input md:col-span-2" rows="2" placeholder={t.library_f_qualifications || 'Qualifications'} aria-label={t.library_f_qualifications || 'Qualifications'} value={form.data.qualifications} onChange={(e) => form.setData('qualifications', e.target.value)} />
            <label className="text-sm md:col-span-2">
                {t.library_author_photo || 'Portrait (JPEG, PNG or WebP, up to 4 MB) — shown publicly on your author page'}
                <input className="form-input" type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => form.setData('photo', e.target.files[0] ?? null)} />
            </label>
            {published.length > 0 && (
                <fieldset className="md:col-span-2" data-testid="featured-works">
                    <legend className="text-sm font-medium">{t.library_author_featured || 'Featured works (up to three, shown first on your page)'}</legend>
                    <div className="mt-1 flex flex-wrap gap-3">
                        {published.map((item) => (
                            <label key={item.id} className="flex items-center gap-1 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.data.featured_item_ids.includes(item.id)}
                                    disabled={!form.data.featured_item_ids.includes(item.id) && form.data.featured_item_ids.length >= 3}
                                    onChange={() => toggleFeatured(item.id)}
                                />
                                {item.title}
                            </label>
                        ))}
                    </div>
                </fieldset>
            )}
            <fieldset className="grid gap-2 md:col-span-2 md:grid-cols-2" data-testid="author-links">
                <legend className="text-sm font-medium">{t.library_author_links || 'Links (full addresses, shown on your page)'}</legend>
                {LINK_KEYS.map(([key, name]) => {
                    const label = name ?? (t.library_link_website || 'Website');

                    return (
                        <input
                            key={key}
                            className="form-input"
                            type="url"
                            placeholder={`${label} — https://…`} aria-label={`${label} — https://…`}
                            value={form.data.social_links[key]}
                            onChange={(e) => form.setData('social_links', { ...form.data.social_links, [key]: e.target.value })}
                        />
                    );
                })}
            </fieldset>
            <div className="flex flex-wrap items-center gap-2 md:col-span-2">
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.library_author_save || 'Save author page'}</button>
                {onDone && <button type="button" className="btn-secondary" onClick={onDone}>{t.library_close || 'Close'}</button>}
                {profile.slug && <a className="text-sm underline" href={`/library/authors/${profile.slug}`} target="_blank" rel="noreferrer">{t.library_author_view || 'View my author page'}</a>}
            </div>
            <FormErrors errors={form.errors} className="md:col-span-2" />
        </form>
    );
}

function EarningsCard({ earnings, itemSales = [], t = {}, refusals }) {
    const bank = useForm({ bank_name: '', account_name: '', account_number: '' });
    if (!earnings) return null;

    return (
        <div className="mb-6 rounded-lg border bg-white p-4" data-testid="earnings-card">
            <div className="mb-3 flex flex-wrap items-center gap-6 text-sm">
                <span><strong>{money(t, earnings.pending)}</strong> {t.library_earn_pending || 'pending (in refund window)'}</span>
                <span><strong>{money(t, earnings.available)}</strong> {t.library_earn_available || 'available'}</span>
                <span><strong>{money(t, earnings.paid)}</strong> {t.library_earn_paid || 'paid out'}</span>
                {earnings.refunded > 0 && <span className="text-red-600">{money(t, earnings.refunded)} {t.library_earn_refunded || 'refunded'}</span>}
                {earnings.payouts_enabled ? (
                    <button
                        type="button"
                        className="btn-primary"
                        disabled={!earnings.can_request || !earnings.has_bank_details}
                        onClick={() => refusals.actOn('payout', () => router.post('/write/payout-request', {}, { preserveScroll: true }))}
                    >
                        {fill(t.library_earn_request || 'Request payout (min MVR :min)', { min: earnings.min_payout })}
                    </button>
                ) : (
                    <span className="text-xs text-gray-500">{t.library_earn_soon || 'Payouts open soon — earnings keep accruing and stay yours.'}</span>
                )}
            </div>
            <FormErrors errors={refusals.errorsFor('payout')} className="mb-2" />
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    refusals.actOn('bank', () => bank.post('/write/bank-details', { preserveScroll: true }));
                }}
                className="flex flex-wrap items-center gap-2"
            >
                <input className="form-input w-40" placeholder={t.library_bank_name || 'Bank name'} aria-label={t.library_bank_name || 'Bank name'} value={bank.data.bank_name} onChange={(e) => bank.setData('bank_name', e.target.value)} />
                <input className="form-input w-40" placeholder={t.library_account_name || 'Account name'} aria-label={t.library_account_name || 'Account name'} value={bank.data.account_name} onChange={(e) => bank.setData('account_name', e.target.value)} />
                <input className="form-input w-40" placeholder={t.library_account_number || 'Account number'} aria-label={t.library_account_number || 'Account number'} value={bank.data.account_number} onChange={(e) => bank.setData('account_number', e.target.value)} />
                <button type="submit" className="btn-secondary" disabled={bank.processing}>
                    {earnings.has_bank_details ? t.library_bank_update || 'Update bank details' : t.library_bank_save || 'Save bank details'}
                </button>
            </form>
            {/* The bank form's refusals were said only at the top of the page. */}
            <FormErrors errors={bank.errors} className="mt-2" />
            {itemSales.length > 0 && (
                <div className="mt-4 overflow-x-auto">
                    <p className="mb-1 text-sm font-semibold">{t.library_sales_by_book || 'Sales by book'}</p>
                    <table className="min-w-full text-sm">
                        <thead className="bg-[#F3EBE0] text-start">
                            <tr>
                                <th className="px-3 py-2">{t.library_th_book || 'Book'}</th>
                                <th className="px-3 py-2">{t.library_th_sold || 'Copies sold'}</th>
                                <th className="px-3 py-2">{t.library_th_gross || 'Gross (MVR)'}</th>
                                <th className="px-3 py-2">{t.library_th_your_share || 'Your share (MVR)'}</th>
                                <th className="px-3 py-2">{t.library_th_refunded || 'Refunded'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {itemSales.map((row) => (
                                <tr key={row.item_id} className="border-t">
                                    <td className="px-3 py-2 font-medium">{row.title}</td>
                                    <td className="px-3 py-2">{row.sold}</td>
                                    <td className="px-3 py-2">{row.gross}</td>
                                    <td className="px-3 py-2">{row.earned}</td>
                                    <td className="px-3 py-2">{row.refunded > 0 ? <span className="text-red-600">{row.refunded}</span> : '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

export default function Write({ dashboard, options, earnings = null, item_sales = [], identity = null, id_l = {} }) {
    const { i18n } = usePage().props;
    const t = i18n?.common || {};
    // Submit for review and Request payout post without a form, so a refusal
    // — an item not in a submittable state, a declaration not ticked — is
    // said on the row whose button was pressed; each form says its own.
    const refusals = useRowRefusals();
    // R3b: on a revision, the writer tells the reviewers what changed.
    const [notes, setNotes] = useState({});
    const [editing, setEditing] = useState(null);
    const [editingProfile, setEditingProfile] = useState(false);
    const { profile, application, items, sales } = dashboard;
    const salesCount = Number(sales?.total_sales || 0);

    return (
        <AppShell title={t.library_write_title || 'Writer portal'}>
            <FormErrors errors={refusals.unplaced} className="mb-4" />
            {profile && <IdentityCardUpload identity={identity} href="/write/identity" l={id_l} blurb={id_l.id_writer_blurb} actOn={refusals.actOn} />}
            <FormErrors errors={refusals.errorsFor('identity')} except={['id_front', 'id_back']} className="mb-4" />
            {!profile && (
                <div className="mb-6">
                    {application?.status === 'pending' && (
                        <p className="rounded bg-amber-50 p-3 text-amber-800">{fill(t.library_app_pending || 'Your writer application is pending review (applied :date).', { date: application.created_at })}</p>
                    )}
                    {application?.status === 'rejected' && (
                        <p className="mb-4 rounded bg-red-50 p-3 text-red-700">
                            {application.decision_note
                                ? fill(t.library_app_rejected_note || 'Your last application was not approved — :note. You may apply again.', { note: application.decision_note })
                                : t.library_app_rejected || 'Your last application was not approved. You may apply again.'}
                        </p>
                    )}
                    {application?.status !== 'pending' && <ApplyForm t={t} idL={id_l} actOn={refusals.actOn} />}
                </div>
            )}

            {profile && (
                <>
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <div className="flex items-center gap-3">
                            {profile.photo_url ? (
                                <img src={profile.photo_url} alt="" className="h-12 w-12 rounded-full object-cover" data-testid="author-portrait" />
                            ) : (
                                <div className="flex h-12 w-12 items-center justify-center rounded-full bg-[#F3EBE0] font-semibold text-[#7C2D37]" aria-hidden="true">{(profile.display_name || '?').slice(0, 1).toUpperCase()}</div>
                            )}
                            <div>
                                <h2 className="text-lg font-semibold">{profile.display_name}</h2>
                                <p className="text-sm text-gray-500" data-testid="writer-standing">
                                    {fill(t.library_since || 'Approved writer since :date', { date: profile.approved_at })}
                                    {' · '}
                                    {salesCount === 1 ? t.library_sales_one || '1 sale' : fill(t.library_sales_count || ':count sales', { count: salesCount })}
                                    {' · '}
                                    {money(t, sales?.total_revenue || 0)}
                                    {profile.slug && <> · <a className="underline" href={`/library/authors/${profile.slug}`}>{t.library_author_view || 'View my author page'}</a></>}
                                </p>
                            </div>
                        </div>
                        <div className="flex gap-2">
                            <button type="button" className="btn-secondary" onClick={() => setEditingProfile(!editingProfile)}>
                                {editingProfile ? t.library_close || 'Close' : t.library_author_edit || 'Edit author page'}
                            </button>
                            <button type="button" className="btn-primary" onClick={() => setEditing(editing === 'new' ? null : 'new')}>
                                {editing === 'new' ? t.library_close_editor || 'Close editor' : t.library_new_draft || 'New draft'}
                            </button>
                        </div>
                    </div>

                    {editingProfile && <AuthorPageForm profile={profile} items={dashboard.items || []} onDone={() => setEditingProfile(false)} t={t} actOn={refusals.actOn} />}

                    <EarningsCard earnings={earnings} itemSales={item_sales} t={t} refusals={refusals} />

                    {editing === 'new' && <ItemEditor options={options} onDone={() => setEditing(null)} t={t} actOn={refusals.actOn} />}
                    {editing && editing !== 'new' && <ItemEditor item={editing} options={options} onDone={() => setEditing(null)} t={t} actOn={refusals.actOn} />}

                    <div className="overflow-x-auto rounded-lg border bg-white">
                        <table className="min-w-full text-sm">
                            <thead className="bg-[#F3EBE0] text-start">
                                <tr>
                                    <th className="px-3 py-2">{t.library_th_title || 'Title'}</th>
                                    <th className="px-3 py-2">{t.library_th_status || 'Status'}</th>
                                    <th className="px-3 py-2">{t.library_th_feedback || 'Editor feedback'}</th>
                                    <th className="px-3 py-2">{t.library_th_sales || 'Sales'}</th>
                                    <th className="px-3 py-2">{t.library_th_actions || 'Actions'}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.length === 0 && (
                                    <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>{t.library_no_drafts || 'No drafts yet — start one.'}</td></tr>
                                )}
                                {items.map((item) => (
                                    <tr key={item.id} className="border-t">
                                        <td className="px-3 py-2">
                                            <div className="flex items-start gap-3">
                                                {item.cover_url && <img src={item.cover_url} alt="" className="h-12 w-9 shrink-0 rounded object-cover" data-testid="item-cover" />}
                                                <div>
                                                    <p className="font-medium">{item.title}</p>
                                                    <p className="text-xs text-gray-500">
                                                        {t[`library_type_${item.content_type}`] || item.content_type}
                                                        {' · '}
                                                        {t[`library_access_${item.access_type}`] || item.access_type}
                                                        {item.price ? ` · ${money(t, item.price)}` : ''}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-3 py-2">{t[`library_status_${item.status}`] || item.status?.replaceAll('_', ' ')} {item.review_state && <ReviewStateChip state={item.review_state} t={t} />}</td>
                                        <td className="px-3 py-2 text-xs text-gray-600">{item.latest_comment || '—'}</td>
                                        <td className="px-3 py-2">{item.sales} ({item.revenue ? money(t, item.revenue) : '—'})</td>
                                        <td className="px-3 py-2">
                                            {['draft', 'changes_requested'].includes(item.status) && (
                                                <span className="flex flex-wrap gap-2">
                                                    <button type="button" className="text-[#7C2D37] hover:underline" onClick={() => setEditing(item)}>{t.library_edit || 'Edit'}</button>
                                                    {item.status === 'changes_requested' && item.content_type === 'research' && (
                                                        <input
                                                            className="form-input w-56 text-xs"
                                                            placeholder={t.library_revision_note || 'What changed (for the reviewers)'} aria-label={t.library_revision_note || 'What changed (for the reviewers)'}
                                                            value={notes[item.id] || ''}
                                                            onChange={(e) => setNotes({ ...notes, [item.id]: e.target.value })}
                                                            data-testid={`revision-note-${item.id}`}
                                                        />
                                                    )}
                                                    <button type="button" className="btn-secondary" onClick={() => refusals.actOn(`item:${item.id}`, () => router.post(`/write/items/${item.id}/submit`, notes[item.id] ? { note: notes[item.id] } : {}, { preserveScroll: true }))}>{t.library_submit_review || 'Submit for review'}</button>
                                                </span>
                                            )}
                                            {item.status === 'published' && <a className="text-[#7C2D37] hover:underline" href={`/library/${item.slug}`}>{t.library_view || 'View'}</a>}
                                            <FormErrors errors={refusals.errorsFor(`item:${item.id}`)} className="mt-1" />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}
        </AppShell>
    );
}
