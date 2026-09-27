import { Link, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * New or edit research post (W25; C9 slice 8, STATUS §5jj). One form for
 * both: the edit carries the current PDF and posts with a spoofed PUT,
 * because a browser cannot send a file on a real one. Validation comes back
 * from SaveResearchPostAction as field errors, shown at the top and under
 * the field.
 */
/**
 * New and edit render the same page component, and Inertia keeps a mounted
 * component when only its props change — so a save from *New* that lands on
 * *Edit* would otherwise keep the new form's state (no `_method`, the file
 * still selected). Keying the form on the post remounts it with the edit's
 * initial values.
 */
export default function ResearchForm(props) {
    return <ResearchFormBody key={props.item?.id ?? 'new'} {...props} />;
}

function ResearchFormBody({ item = null, instructors = [], t = {} }) {
    const { flash = {} } = usePage().props;
    const editing = item !== null;
    const form = useForm({
        title: item?.title || '',
        slug: item?.slug || '',
        abstract: item?.abstract || '',
        body: item?.body || '',
        citation_note: item?.citation_note || '',
        instructor_ids: (item?.instructor_ids || []).map(String),
        external_names: item?.external_names_text || '',
        pdf: null,
        published_at: item?.published_at_local || '',
        is_published: editing ? !!item.is_published_flag : false,
        ...(editing ? { _method: 'put' } : {}),
    });
    const submit = (e) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, is_published: data.is_published ? '1' : '0' }));
        form.post(editing ? `/admin/public-site/research/${item.id}` : '/admin/public-site/research', { forceFormData: true, preserveScroll: true });
    };
    const firstError = Object.values(form.errors)[0];
    const text = (name, label, props = {}) => (
        <div>
            <label className="mb-1 block text-xs text-gray-600" htmlFor={`research-${name}`}>{label}</label>
            <input id={`research-${name}`} name={name} className="form-input w-full" value={form.data[name]} onChange={(e) => form.setData(name, e.target.value)} {...props} />
            {form.errors[name] && <p className="mt-1 text-xs text-red-700">{form.errors[name]}</p>}
        </div>
    );
    const area = (name, label, rows) => (
        <div>
            <label className="mb-1 block text-xs text-gray-600" htmlFor={`research-${name}`}>{label}</label>
            <textarea id={`research-${name}`} name={name} rows={rows} className="form-input w-full" value={form.data[name]} onChange={(e) => form.setData(name, e.target.value)} />
            {form.errors[name] && <p className="mt-1 text-xs text-red-700">{form.errors[name]}</p>}
        </div>
    );

    return (
        <AppShell title={editing ? (t.research_edit_title || 'Edit research post') : (t.research_new_title || 'New research post')}>
            <p className="mb-4 text-sm"><Link href="/admin/public-site/research" className="text-gray-500 underline" data-testid="research-back">{t.research_back || '← Research posts'}</Link></p>
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700" data-testid="research-flash">✓ {flash.success}</p>}
            {firstError && <p className="mb-4 rounded bg-red-50 p-3 text-sm text-red-700" data-testid="research-error">✗ {firstError}</p>}

            <form onSubmit={submit} className="max-w-3xl space-y-4 rounded-lg border bg-white p-6" data-testid="research-form" encType="multipart/form-data">
                {text('title', t.research_col_title || 'Title', { required: true, type: 'text' })}
                {text('slug', t.research_slug || 'Slug', { type: 'text', placeholder: t.research_slug_optional || 'optional' })}
                {area('abstract', t.research_abstract || 'Abstract', 3)}
                {area('body', t.research_body || 'Body', 8)}
                {area('citation_note', t.research_citation || 'Citation note', 2)}
                <div>
                    <label className="mb-1 block text-xs text-gray-600" htmlFor="research-instructors">{t.research_instructor_authors || 'Instructor authors'}</label>
                    <select id="research-instructors" name="instructor_ids[]" multiple size="6" className="form-input w-full" value={form.data.instructor_ids} onChange={(e) => form.setData('instructor_ids', Array.from(e.target.selectedOptions).map((o) => o.value))}>
                        {instructors.map((i) => <option key={i.id} value={String(i.id)}>{i.name}</option>)}
                    </select>
                </div>
                {area('external_names', t.research_external_authors || 'External authors (one per line)', 3)}
                <div>
                    <label className="mb-1 block text-xs text-gray-600" htmlFor="research-pdf">{t.research_pdf || 'PDF (optional)'}</label>
                    <input id="research-pdf" type="file" name="pdf" accept="application/pdf" className="block text-sm text-gray-600" onChange={(e) => form.setData('pdf', e.target.files[0] || null)} data-testid="research-pdf-input" />
                    {editing && item.pdf?.url && <p className="mt-1 text-xs text-gray-500"><a href={item.pdf.url} className="underline" target="_blank" rel="noopener noreferrer" data-testid="research-current-pdf">{t.research_current_pdf || 'Current PDF'}</a></p>}
                    {form.errors.pdf && <p className="mt-1 text-xs text-red-700">{form.errors.pdf}</p>}
                </div>
                <div className="grid gap-4 md:grid-cols-2">
                    {text('published_at', t.research_published_at || 'Published at', { type: 'datetime-local' })}
                    <label className="flex items-end gap-2 pb-2 text-sm text-gray-700">
                        <input type="checkbox" name="is_published" checked={form.data.is_published} onChange={(e) => form.setData('is_published', e.target.checked)} data-testid="research-published" />
                        {t.research_published || 'Published'}
                    </label>
                </div>
                <div className="flex items-center gap-3">
                    <button type="submit" className="btn-primary" disabled={form.processing} data-testid="research-save">{t.research_save || 'Save'}</button>
                    <Link href="/admin/public-site/research" className="text-sm text-gray-500 underline">{t.research_back_short || 'Back'}</Link>
                </div>
            </form>
        </AppShell>
    );
}
