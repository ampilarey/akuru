import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ years }) {
    const yearForm = useForm({
        name: '',
        start_date: '',
        end_date: '',
        description: '',
    });
    const termForm = useForm({
        name: 'Term 1',
        start_date: '',
        end_date: '',
    });

    return (
        <AppShell title="Academic years">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    yearForm.post('/academics/years');
                }}
                className="mb-6 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <input className="form-input" placeholder="2026-2027" value={yearForm.data.name} onChange={(e) => yearForm.setData('name', e.target.value)} />
                <input className="form-input" type="date" value={yearForm.data.start_date} onChange={(e) => yearForm.setData('start_date', e.target.value)} />
                <input className="form-input" type="date" value={yearForm.data.end_date} onChange={(e) => yearForm.setData('end_date', e.target.value)} />
                <button type="submit" className="btn-primary">Create year</button>
                {yearForm.errors.name && <p className="md:col-span-4 text-sm text-red-600">{yearForm.errors.name}</p>}
            </form>

            {years.map((year) => (
                <section key={year.id} className="mb-4 rounded-lg border bg-white p-4">
                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h2 className="font-semibold">{year.name} · {year.status}</h2>
                        <div className="flex gap-2">
                            <button type="button" className="btn-secondary" onClick={() => router.post(`/academics/years/${year.id}/activate`)}>Activate</button>
                            <button type="button" className="btn-secondary" onClick={() => router.post(`/academics/years/${year.id}/close`)}>Close</button>
                        </div>
                    </div>
                    <ul className="mb-3 text-sm">
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
                            termForm.post(`/academics/years/${year.id}/terms`);
                        }}
                        className="flex flex-wrap gap-2"
                    >
                        <input className="form-input" value={termForm.data.name} onChange={(e) => termForm.setData('name', e.target.value)} />
                        <input className="form-input" type="date" value={termForm.data.start_date} onChange={(e) => termForm.setData('start_date', e.target.value)} />
                        <input className="form-input" type="date" value={termForm.data.end_date} onChange={(e) => termForm.setData('end_date', e.target.value)} />
                        <button type="submit" className="btn-primary">Add term</button>
                    </form>
                </section>
            ))}
        </AppShell>
    );
}
