import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

function ApplyForm({ t }) {
    const form = useForm({
        display_name: '',
        bio: '',
        qualifications: '',
        expertise: '',
        motivation: '',
        agreement_accepted: false,
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post('/write/apply', { preserveScroll: true });
            }}
            className="grid max-w-2xl gap-3 rounded-lg border bg-white p-4"
        >
            <h2 className="text-lg font-semibold">{t.library_apply_title || 'Apply to publish with Akuru'}</h2>
            <input className="form-input" placeholder="Display name (as shown to readers)" value={form.data.display_name} onChange={(e) => form.setData('display_name', e.target.value)} />
            {form.errors.display_name && <p className="text-sm text-red-600">{form.errors.display_name}</p>}
            <textarea className="form-input" rows="3" placeholder="Bio" value={form.data.bio} onChange={(e) => form.setData('bio', e.target.value)} />
            <textarea className="form-input" rows="2" placeholder="Qualifications" value={form.data.qualifications} onChange={(e) => form.setData('qualifications', e.target.value)} />
            <input className="form-input" placeholder="Expertise (e.g. Tafsir, Arabic grammar)" value={form.data.expertise} onChange={(e) => form.setData('expertise', e.target.value)} />
            <textarea className="form-input" rows="3" placeholder="Why do you want to publish with us?" value={form.data.motivation} onChange={(e) => form.setData('motivation', e.target.value)} />
            <label className="flex items-start gap-2 text-sm">
                <input type="checkbox" checked={form.data.agreement_accepted} onChange={(e) => form.setData('agreement_accepted', e.target.checked)} />
                <span>
                    I own or have permission for everything I upload, accept the{' '}
                    <a className="text-[#7C2D37] underline" href="/page/publishing-terms" target="_blank" rel="noopener">Publishing Terms</a>,{' '}
                    the <a className="text-[#7C2D37] underline" href="/page/writer-agreement" target="_blank" rel="noopener">Writer Agreement</a>{' '}
                    and the <a className="text-[#7C2D37] underline" href="/refunds" target="_blank" rel="noopener">refund rules</a>, and understand Akuru may remove content on a valid complaint.
                </span>
            </label>
            {form.errors.agreement_accepted && <p className="text-sm text-red-600">{form.errors.agreement_accepted}</p>}
            {form.errors.application && <p className="text-sm text-red-600">{form.errors.application}</p>}
            <button type="submit" className="btn-primary justify-self-start" disabled={form.processing}>{t.library_apply_submit || 'Submit application'}</button>
        </form>
    );
}

function ItemEditor({ item, options, onDone, t }) {
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
        tags_text: (item?.tags || []).join(', '),
        co_authors_text: (item?.co_authors || []).join(', '),
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
            const { tags_text, co_authors_text, ...rest } = data;

            return {
                ...rest,
                declarations,
                preview_enabled: data.preview_enabled ? 1 : 0,
                tags: split(tags_text),
                co_authors: split(co_authors_text),
                ...(item ? { _method: 'put' } : {}),
            };
        });
        if (item) {
            form.post(`/write/items/${item.id}`, opts);
        } else {
            form.post('/write/items', opts);
        }
    };

    return (
        <form onSubmit={submit} className="mb-4 grid gap-2 rounded-lg border bg-white p-4 md:grid-cols-4" data-testid="draft-editor">
            <input className="form-input md:col-span-2" placeholder="Title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
            <input className="form-input md:col-span-2" placeholder="Subtitle" value={form.data.subtitle} onChange={(e) => form.setData('subtitle', e.target.value)} />
            <select className="form-input" value={form.data.content_type} onChange={(e) => form.setData('content_type', e.target.value)}>
                {options.content_types.map((type) => <option key={type} value={type}>{type.replaceAll('_', ' ')}</option>)}
            </select>
            <select className="form-input" value={form.data.access_type} onChange={(e) => form.setData('access_type', e.target.value)}>
                <option value="free_public">free public</option>
                <option value="free_login">free (login)</option>
                <option value="paid">paid</option>
            </select>
            <input className="form-input" placeholder="Suggested price (MVR)" value={form.data.price} onChange={(e) => form.setData('price', e.target.value)} />
            <select className="form-input" value={form.data.language} onChange={(e) => form.setData('language', e.target.value)} aria-label="Language">
                {Object.entries(options.languages || { en: 'English' }).map(([code, label]) => <option key={code} value={code}>{label}</option>)}
            </select>
            <select className="form-input" value={form.data.library_category_id} onChange={(e) => form.setData('library_category_id', e.target.value)} aria-label="Category">
                <option value="">Category…</option>
                {(options.categories || []).map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
            </select>
            <input className="form-input" placeholder="Keywords (comma-separated)" value={form.data.tags_text} onChange={(e) => form.setData('tags_text', e.target.value)} />
            <input className="form-input md:col-span-2" placeholder="Co-authors (comma-separated)" value={form.data.co_authors_text} onChange={(e) => form.setData('co_authors_text', e.target.value)} />
            <textarea className="form-input md:col-span-2" rows="2" placeholder="Description" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
            <textarea className="form-input md:col-span-2" rows="2" placeholder="Abstract" value={form.data.abstract} onChange={(e) => form.setData('abstract', e.target.value)} />
            <textarea className="form-input md:col-span-4" rows="6" placeholder="Body (HTML — use <!-- pagebreak --> between pages)" value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} />
            {isBook && (
                <textarea className="form-input md:col-span-4" rows="4" placeholder="Table of contents (one entry per line)" value={form.data.toc} onChange={(e) => form.setData('toc', e.target.value)} />
            )}
            {isResearch && (
                <>
                    <textarea className="form-input md:col-span-4" rows="3" placeholder="Citations (one per line)" value={form.data.citations} onChange={(e) => form.setData('citations', e.target.value)} />
                    <input className="form-input md:col-span-2" placeholder="Affiliation" value={form.data.affiliation} onChange={(e) => form.setData('affiliation', e.target.value)} />
                    <input className="form-input" placeholder="Field" value={form.data.research_field} onChange={(e) => form.setData('research_field', e.target.value)} />
                    <input className="form-input" placeholder="Suggested reviewer (optional)" value={form.data.suggested_reviewer} onChange={(e) => form.setData('suggested_reviewer', e.target.value)} />
                </>
            )}
            <label className="flex items-center gap-2 text-sm md:col-span-2">
                <input type="checkbox" checked={form.data.preview_enabled} onChange={(e) => form.setData('preview_enabled', e.target.checked)} />
                Suggest a free preview of
                <input className="form-input w-20" type="number" min="1" value={form.data.preview_pages} onChange={(e) => form.setData('preview_pages', e.target.value)} aria-label="Preview pages" />
                pages
            </label>
            <label className="text-sm md:col-span-4">
                Cover image (JPEG, PNG or WebP — shown on the shelf)
                <input className="form-input" type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => form.setData('cover', e.target.files[0] ?? null)} />
                {item?.cover_url && <img src={item.cover_url} alt="" className="mt-2 h-24 rounded object-cover" data-testid="draft-cover" />}
            </label>
            <label className="text-sm md:col-span-4">
                Original PDF (stored privately)
                <input className="form-input" type="file" accept="application/pdf" onChange={(e) => form.setData('pdf', e.target.files[0] ?? null)} />
                <span className="mt-1 block text-xs text-gray-500">
                    Readers get the PDF page by page, with their name on each page — never the file. A scanned PDF has no text to show; paste the text into the body instead. When both exist, the body is what readers see.
                </span>
            </label>
            <fieldset className="md:col-span-4 rounded border p-3" data-testid="declarations">
                <legend className="px-1 text-sm font-medium">Declarations (required before submitting)</legend>
                <label className="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="declarations[copyright]" checked={form.data.declarations.copyright} onChange={(e) => declare('copyright', e.target.checked)} />
                    <span>I hold the copyright to this work, or the right to publish it, and it does not infringe anyone else&rsquo;s.</span>
                </label>
                <label className="mt-1 flex items-start gap-2 text-sm">
                    <input type="checkbox" name="declarations[ai_use]" checked={form.data.declarations.ai_use} onChange={(e) => declare('ai_use', e.target.checked)} />
                    <span>AI tools were used in preparing this work (optional; shown to readers).</span>
                </label>
                {isResearch && (
                    <>
                        <label className="mt-1 flex items-start gap-2 text-sm">
                            <input type="checkbox" name="declarations[originality]" checked={form.data.declarations.originality} onChange={(e) => declare('originality', e.target.checked)} />
                            <span>This research is original and not under review or published elsewhere.</span>
                        </label>
                        <label className="mt-1 flex items-start gap-2 text-sm">
                            <input type="checkbox" name="declarations[conflict_of_interest]" checked={form.data.declarations.conflict_of_interest} onChange={(e) => declare('conflict_of_interest', e.target.checked)} />
                            <span>I have declared any conflict of interest, or have none.</span>
                        </label>
                        <label className="mt-1 flex items-start gap-2 text-sm">
                            <input type="checkbox" name="declarations[ethics]" checked={form.data.declarations.ethics} onChange={(e) => declare('ethics', e.target.checked)} />
                            <span>Where the research involved people, the required ethics approval was obtained (if applicable).</span>
                        </label>
                    </>
                )}
            </fieldset>
            <div className="flex gap-2 self-end">
                <button type="submit" className="btn-primary" disabled={form.processing}>{item ? t.library_update_draft || 'Update draft' : t.library_save_draft || 'Save draft'}</button>
                {onDone && <button type="button" className="btn-secondary" onClick={onDone}>{t.library_close || 'Close'}</button>}
            </div>
            {Object.values(form.errors).map((error) => <p key={error} className="text-sm text-red-600 md:col-span-4">{error}</p>)}
        </form>
    );
}

/**
 * L8: what readers see on the author's public page. The address is fixed
 * at approval and kept across renames, so it is shown, not edited.
 */
function AuthorPageForm({ profile, onDone, t }) {
    const form = useForm({
        display_name: profile.display_name || '',
        bio: profile.bio || '',
        qualifications: profile.qualifications || '',
        expertise: profile.expertise || '',
        photo: null,
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post('/write/profile', { preserveScroll: true, forceFormData: true, onSuccess: onDone });
            }}
            className="mb-4 grid gap-2 rounded-lg border bg-white p-4 md:grid-cols-2"
            data-testid="author-page-form"
        >
            <h3 className="text-base font-semibold md:col-span-2">{t.library_author_page_title || 'Your author page'}</h3>
            <input className="form-input" placeholder="Display name (as shown to readers)" value={form.data.display_name} onChange={(e) => form.setData('display_name', e.target.value)} />
            <input className="form-input" placeholder="Expertise (e.g. Tafsir, Arabic grammar)" value={form.data.expertise} onChange={(e) => form.setData('expertise', e.target.value)} />
            <textarea className="form-input md:col-span-2" rows="3" placeholder="Bio" value={form.data.bio} onChange={(e) => form.setData('bio', e.target.value)} />
            <textarea className="form-input md:col-span-2" rows="2" placeholder="Qualifications" value={form.data.qualifications} onChange={(e) => form.setData('qualifications', e.target.value)} />
            <label className="text-sm md:col-span-2">
                {t.library_author_photo || 'Portrait (JPEG, PNG or WebP, up to 4 MB) — shown publicly on your author page'}
                <input className="form-input" type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => form.setData('photo', e.target.files[0] ?? null)} />
            </label>
            <div className="flex flex-wrap items-center gap-2 md:col-span-2">
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.library_author_save || 'Save author page'}</button>
                {onDone && <button type="button" className="btn-secondary" onClick={onDone}>{t.library_close || 'Close'}</button>}
                {profile.slug && <a className="text-sm underline" href={`/library/authors/${profile.slug}`} target="_blank" rel="noreferrer">{t.library_author_view || 'View my author page'}</a>}
            </div>
            {Object.values(form.errors).map((error) => <p key={error} className="text-sm text-red-600 md:col-span-2">{error}</p>)}
        </form>
    );
}

function EarningsCard({ earnings, itemSales = [], t = {} }) {
    const bank = useForm({ bank_name: '', account_name: '', account_number: '' });
    if (!earnings) return null;

    return (
        <div className="mb-6 rounded-lg border bg-white p-4">
            <div className="mb-3 flex flex-wrap items-center gap-6 text-sm">
                <span><strong>MVR {earnings.pending}</strong> pending (in refund window)</span>
                <span><strong>MVR {earnings.available}</strong> available</span>
                <span><strong>MVR {earnings.paid}</strong> paid out</span>
                {earnings.refunded > 0 && <span className="text-red-600">MVR {earnings.refunded} refunded</span>}
                {earnings.payouts_enabled ? (
                    <button
                        type="button"
                        className="btn-primary"
                        disabled={!earnings.can_request || !earnings.has_bank_details}
                        onClick={() => router.post('/write/payout-request', {}, { preserveScroll: true })}
                    >
                        Request payout (min MVR {earnings.min_payout})
                    </button>
                ) : (
                    <span className="text-xs text-gray-500">Payouts open soon — earnings keep accruing and stay yours.</span>
                )}
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    bank.post('/write/bank-details', { preserveScroll: true });
                }}
                className="flex flex-wrap items-center gap-2"
            >
                <input className="form-input w-40" placeholder="Bank name" value={bank.data.bank_name} onChange={(e) => bank.setData('bank_name', e.target.value)} />
                <input className="form-input w-40" placeholder="Account name" value={bank.data.account_name} onChange={(e) => bank.setData('account_name', e.target.value)} />
                <input className="form-input w-40" placeholder="Account number" value={bank.data.account_number} onChange={(e) => bank.setData('account_number', e.target.value)} />
                <button type="submit" className="btn-secondary" disabled={bank.processing}>
                    {earnings.has_bank_details ? 'Update bank details' : 'Save bank details'}
                </button>
            </form>
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

export default function Write({ dashboard, options, earnings = null, item_sales = [] }) {
    const { flash = {}, i18n } = usePage().props;
    const t = i18n?.common || {};
    const [editing, setEditing] = useState(null);
    const [editingProfile, setEditingProfile] = useState(false);
    const { profile, application, items, sales } = dashboard;

    return (
        <AppShell title={t.library_write_title || 'Writer portal'}>
            {/* Submit-for-review posts without a form, so a refusal — an item
                not in a submittable state — had nowhere to appear. The editor
                forms below carry their own field-level errors. */}
            <FormErrors errors={usePage().props.errors} className="mb-4" />
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700">{flash.success}</p>}

            {!profile && (
                <div className="mb-6">
                    {application?.status === 'pending' && (
                        <p className="rounded bg-amber-50 p-3 text-amber-800">Your writer application is pending review (applied {application.created_at}).</p>
                    )}
                    {application?.status === 'rejected' && (
                        <p className="mb-4 rounded bg-red-50 p-3 text-red-700">Your last application was not approved{application.decision_note ? ` — ${application.decision_note}` : ''}. You may apply again.</p>
                    )}
                    {application?.status !== 'pending' && <ApplyForm t={t} />}
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
                                <p className="text-sm text-gray-500">
                                    Approved writer since {profile.approved_at} · {sales.total_sales || 0} sales · MVR {sales.total_revenue || 0}
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

                    {editingProfile && <AuthorPageForm profile={profile} onDone={() => setEditingProfile(false)} t={t} />}

                    <EarningsCard earnings={earnings} itemSales={item_sales} t={t} />

                    {editing === 'new' && <ItemEditor options={options} onDone={() => setEditing(null)} t={t} />}
                    {editing && editing !== 'new' && <ItemEditor item={editing} options={options} onDone={() => setEditing(null)} t={t} />}

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
                                                    <p className="text-xs text-gray-500">{item.content_type} · {item.access_type}{item.price ? ` · MVR ${item.price}` : ''}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-3 py-2">{item.status?.replaceAll('_', ' ')}</td>
                                        <td className="px-3 py-2 text-xs text-gray-600">{item.latest_comment || '—'}</td>
                                        <td className="px-3 py-2">{item.sales} ({item.revenue ? `MVR ${item.revenue}` : '—'})</td>
                                        <td className="px-3 py-2">
                                            {['draft', 'changes_requested'].includes(item.status) && (
                                                <span className="flex gap-2">
                                                    <button type="button" className="text-[#7C2D37] hover:underline" onClick={() => setEditing(item)}>{t.library_edit || 'Edit'}</button>
                                                    <button type="button" className="btn-secondary" onClick={() => router.post(`/write/items/${item.id}/submit`, {}, { preserveScroll: true })}>{t.library_submit_review || 'Submit for review'}</button>
                                                </span>
                                            )}
                                            {item.status === 'published' && <a className="text-[#7C2D37] hover:underline" href={`/library/${item.slug}`}>{t.library_view || 'View'}</a>}
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
