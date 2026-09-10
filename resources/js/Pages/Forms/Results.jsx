import AppShell from '../../Layouts/AppShell';

export default function Results({ form, rows = [] }) {
    return (
        <AppShell title={form.title}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-gray-600">
                    {form.responses} response{form.responses === 1 ? '' : 's'}
                    {form.requires_parent_confirmation ? ` · ${form.confirmed} confirmed by a parent` : ''}
                    {form.is_anonymous ? ' · anonymous' : ''}
                    {form.is_open ? '' : ' · closed'}
                </p>
                <a className="btn-secondary" href={`/forms/${form.id}/export`}>Export CSV</a>
            </div>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="w-full min-w-[40rem] text-sm">
                    <thead className="bg-[#F9F4EE] text-left">
                        <tr>
                            {/* No respondent column at all on an anonymous form —
                                a column of dashes invites someone to go looking. */}
                            {!form.is_anonymous && <th className="p-2">Who</th>}
                            <th className="p-2">Submitted</th>
                            {form.requires_parent_confirmation && <th className="p-2">Confirmed</th>}
                            {form.fields.map((f) => <th key={f.key} className="p-2">{f.label}</th>)}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t align-top">
                                {!form.is_anonymous && <td className="p-2">{row.respondent}</td>}
                                <td className="p-2 text-xs text-gray-500">{row.submitted_at}</td>
                                {form.requires_parent_confirmation && (
                                    <td className={`p-2 text-xs ${row.confirmed_at ? 'text-green-700' : 'font-semibold text-[#7C2D37]'}`}>
                                        {row.confirmed_at || 'Not confirmed'}
                                    </td>
                                )}
                                {form.fields.map((f) => {
                                    const v = row.answers[f.key];
                                    return <td key={f.key} className="p-2">{Array.isArray(v) ? v.join(', ') : (v ?? '')}</td>;
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
                {rows.length === 0 && <p className="p-4 text-sm text-gray-600">No responses yet.</p>}
            </div>
        </AppShell>
    );
}
