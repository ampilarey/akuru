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
function YearCard({ year }) {
    const termForm = useForm({
        name: 'Term 1',
        start_date: '',
        end_date: '',
    });

    return (
        <section className="mb-4 rounded-lg border bg-white p-4">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 className="font-semibold">{year.name} · {year.status}</h2>
                <div className="flex gap-2">
                    <button type="button" className="btn-secondary" onClick={() => router.post(`/academics/years/${year.id}/activate`)}>Activate</button>
                    <button type="button" className="btn-secondary" onClick={() => router.post(`/academics/years/${year.id}/close`)}>Close</button>
                </div>
            </div>
            <ul className="mb-3 text-sm">
                {year.terms.length === 0 && (
                    <li className="border-t py-1 text-gray-500">No terms yet.</li>
                )}
                {year.terms.map((term) => (
                    <li key={term.id} className="flex justify-between border-t py-1">
                        <span>{term.name} ({term.status})</span>
                        <button type="button" className="text-[#7C2D37] hover:underline" onClick={() => router.post(`/academics/years/${year.id}/terms/${term.id}/close`)}>
                            Close term
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
                <Field label={`Term name (${year.name})`} error={termForm.errors.name}>
                    <input className="form-input" value={termForm.data.name} onChange={(e) => termForm.setData('name', e.target.value)} />
                </Field>
                <Field label="Starts" error={termForm.errors.start_date}>
                    <input className="form-input" type="date" value={termForm.data.start_date} onChange={(e) => termForm.setData('start_date', e.target.value)} />
                </Field>
                <Field label="Ends" error={termForm.errors.end_date}>
                    <input className="form-input" type="date" value={termForm.data.end_date} onChange={(e) => termForm.setData('end_date', e.target.value)} />
                </Field>
                <button type="submit" className="btn-primary" disabled={termForm.processing}>Add term</button>
            </form>
        </section>
    );
}

export default function Index({ years }) {
    const yearForm = useForm({
        name: '',
        start_date: '',
        end_date: '',
        description: '',
    });

    return (
        <AppShell title="Academic years">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    yearForm.post('/academics/years', {
                        onSuccess: () => yearForm.reset(),
                    });
                }}
                className="mb-6 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <Field label="Year name" error={yearForm.errors.name}>
                    <input className="form-input w-full" placeholder="2026-2027" value={yearForm.data.name} onChange={(e) => yearForm.setData('name', e.target.value)} />
                </Field>
                <Field label="Starts" error={yearForm.errors.start_date}>
                    <input className="form-input w-full" type="date" value={yearForm.data.start_date} onChange={(e) => yearForm.setData('start_date', e.target.value)} />
                </Field>
                <Field label="Ends" error={yearForm.errors.end_date}>
                    <input className="form-input w-full" type="date" value={yearForm.data.end_date} onChange={(e) => yearForm.setData('end_date', e.target.value)} />
                </Field>
                {/* The controller has validated `description` since the screen
                    shipped; the form carried the key with nowhere to type it. */}
                <Field label="Description (optional)" error={yearForm.errors.description}>
                    <input className="form-input w-full" value={yearForm.data.description} onChange={(e) => yearForm.setData('description', e.target.value)} />
                </Field>
                <div className="md:col-span-4">
                    <button type="submit" className="btn-primary" disabled={yearForm.processing}>Create year</button>
                </div>
            </form>

            {years.map((year) => <YearCard key={year.id} year={year} />)}
        </AppShell>
    );
}
