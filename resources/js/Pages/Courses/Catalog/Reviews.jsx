import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

function ReviewRow({ row }) {
    const form = useForm({
        kind: row.kind,
        attempt_id: row.id,
        score: row.score || 0,
        max_score: row.max_score || 1,
        feedback: row.feedback || '',
    });

    return (
        <article className="rounded-lg border bg-white p-4">
            <h2 className="mb-1 font-medium">{row.title} <span className="text-xs uppercase text-gray-500">{row.kind}</span></h2>
            <p className="mb-3 text-sm text-gray-600">{row.prompt || 'Teacher-marked submission'}</p>
            <pre className="mb-3 overflow-x-auto rounded bg-[#F9F4EE] p-3 text-xs">{JSON.stringify(row.answers, null, 2)}</pre>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/catalog/reviews', { preserveScroll: true });
                }}
                className="grid gap-3 md:grid-cols-4"
            >
                <input className="form-input" type="number" min="0" value={form.data.score} onChange={(e) => form.setData('score', e.target.value)} />
                <input className="form-input" type="number" min="1" value={form.data.max_score} onChange={(e) => form.setData('max_score', e.target.value)} />
                <input className="form-input md:col-span-2" placeholder="Feedback" value={form.data.feedback} onChange={(e) => form.setData('feedback', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>Score and release</button>
            </form>
        </article>
    );
}

export default function Reviews({ rows }) {
    return (
        <AppShell title="Teacher review">
            {rows.length === 0 && <p className="text-sm text-gray-500">No submitted work waiting for review.</p>}
            <div className="space-y-3">
                {rows.map((row) => <ReviewRow key={`${row.kind}-${row.id}`} row={row} />)}
            </div>
        </AppShell>
    );
}
