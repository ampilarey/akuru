import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

function Field({ label, error, children }) {
    return (
        <label className="block text-sm">
            <span className="mb-1 block text-gray-600">{label}</span>
            {children}
            {error && <span className="mt-1 block text-xs text-red-600">{error}</span>}
        </label>
    );
}

/**
 * Each year card owns its term form.
 *
 * A single shared `useForm` meant every card rendered the same state: typing a
 * term name on one year filled the boxes on all of them, and it was never
 * obvious which year an "Add term" click would hit.
 */
function YearCard({ year, t }) {
    const termForm = useForm({
        name: t.years_term_default || 'Term 1',
        start_date: '',
        end_date: '',
    });
    const termName = (t.years_term_name || 'Term name (:year)').replace(':year', year.name);

    return (
        <section className="mb-4 rounded-lg border bg-white p-4">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 className="font-semibold">{year.name} · {t[`year_status_${year.status}`] || year.status}</h2>
                <div className="flex gap-2">
                    <button type="button" className="btn-secondary" onClick={() => router.post(`/academics/years/${year.id}/activate`)}>{t.years_activate || 'Activate'}</button>
                    <button type="button" className="btn-secondary" onClick={() => router.post(`/academics/years/${year.id}/close`)}>{t.years_close || 'Close'}</button>
                </div>
            </div>
            <ul className="mb-3 text-sm">
                {year.terms.length === 0 && (
                    <li className="border-t py-1 text-gray-500">{t.years_no_terms || 'No terms yet.'}</li>
                )}
                {year.terms.map((term) => (
                    <li key={term.id} className="flex justify-between border-t py-1">
                        <span>{term.name} ({t[`term_status_${term.status}`] || term.status})</span>
                        <button type="button" className="text-[#7C2D37] hover:underline" onClick={() => router.post(`/academics/years/${year.id}/terms/${term.id}/close`)}>
                            {t.years_close_term || 'Close term'}
                        </button>
                    </li>
                ))}
            </ul>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    termForm.post(`/academics/years/${year.id}/terms`, {
                        preserveScroll: true,
                        onSuccess: () => termForm.reset(),
                    });
                }}
                className="flex flex-wrap items-end gap-2"
            >
                <Field label={termName} error={termForm.errors.name}>
                    <input className="form-input" aria-label={termName} value={termForm.data.name} onChange={(e) => termForm.setData('name', e.target.value)} />
                </Field>
                <Field label={t.starts || 'Starts'} error={termForm.errors.start_date}>
                    <input className="form-input" type="date" aria-label={t.starts || 'Starts'} value={termForm.data.start_date} onChange={(e) => termForm.setData('start_date', e.target.value)} />
                </Field>
                <Field label={t.ends || 'Ends'} error={termForm.errors.end_date}>
                    <input className="form-input" type="date" aria-label={t.ends || 'Ends'} value={termForm.data.end_date} onChange={(e) => termForm.setData('end_date', e.target.value)} />
                </Field>
                <button type="submit" className="btn-primary" disabled={termForm.processing}>{t.years_add_term || 'Add term'}</button>
            </form>
        </section>
    );
}

export default function Index({ years, t = {} }) {
    const yearForm = useForm({
        name: '',
        start_date: '',
        end_date: '',
        description: '',
    });

    // In the page's language (BACKLOG C21, slice OA2); a year's and a term's
    // state are codes, named here. Activating or closing a year the server
    // refuses is said in the red notice, in the page's language.
    return (
        <AppShell title={t.years_title || 'Academic years'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/academics/years/export">{t.export_csv || 'Export CSV'}</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    yearForm.post('/academics/years', {
                        onSuccess: () => yearForm.reset(),
                    });
                }}
                className="mb-6 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <Field label={t.years_year_name || 'Year name'} error={yearForm.errors.name}>
                    <input className="form-input w-full" aria-label={t.years_year_name || 'Year name'} placeholder="2026-2027" value={yearForm.data.name} onChange={(e) => yearForm.setData('name', e.target.value)} />
                </Field>
                <Field label={t.starts || 'Starts'} error={yearForm.errors.start_date}>
                    <input className="form-input w-full" type="date" aria-label={t.starts || 'Starts'} value={yearForm.data.start_date} onChange={(e) => yearForm.setData('start_date', e.target.value)} />
                </Field>
                <Field label={t.ends || 'Ends'} error={yearForm.errors.end_date}>
                    <input className="form-input w-full" type="date" aria-label={t.ends || 'Ends'} value={yearForm.data.end_date} onChange={(e) => yearForm.setData('end_date', e.target.value)} />
                </Field>
                {/* The controller has validated `description` since the screen
                    shipped; the form carried the key with nowhere to type it. */}
                <Field label={t.years_description || 'Description (optional)'} error={yearForm.errors.description}>
                    <input className="form-input w-full" aria-label={t.years_description || 'Description (optional)'} value={yearForm.data.description} onChange={(e) => yearForm.setData('description', e.target.value)} />
                </Field>
                <div className="md:col-span-4">
                    <button type="submit" className="btn-primary" disabled={yearForm.processing}>{t.years_create || 'Create year'}</button>
                </div>
            </form>

            {years.map((year) => <YearCard key={year.id} year={year} t={t} />)}
        </AppShell>
    );
}
