import AppShell from '../../../Layouts/AppShell';

function CountTable({ title, headers, rows, renderRow, emptyText }) {
    return (
        <div>
            <h2 className="mb-2 text-lg font-semibold">{title}</h2>
            <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>{headers.map((header) => <th key={header} className="px-3 py-2">{header}</th>)}</tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={headers.length}>{emptyText}</td></tr>
                        )}
                        {rows.map(renderRow)}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

export default function QuranOversight({
    total_submissions, by_status, mistake_types, wrong_letters, wrong_harakas, teacher_activity, students,
}) {
    return (
        <AppShell title="Qur'an oversight">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/catalog/quran/oversight?format=csv">Export CSV</a>
            </div>

            <div className="mb-6 grid gap-3 md:grid-cols-4">
                <div className="rounded-lg border bg-white p-4">
                    <div className="text-2xl font-bold">{total_submissions}</div>
                    <div className="text-sm text-gray-600">Total submissions</div>
                </div>
                {Object.entries(by_status).map(([status, count]) => (
                    <div key={status} className="rounded-lg border bg-white p-4">
                        <div className="text-2xl font-bold">{count}</div>
                        <div className="text-sm text-gray-600">{status.replaceAll('_', ' ')}</div>
                    </div>
                ))}
            </div>

            <div className="grid gap-x-6 lg:grid-cols-2">
                <CountTable
                    title="Common mistakes"
                    headers={['Type', 'Count']}
                    rows={mistake_types}
                    emptyText="No mistakes recorded yet."
                    renderRow={(row) => (
                        <tr key={row.type} className="border-t">
                            <td className="px-3 py-2">{row.type.replaceAll('_', ' ')}</td>
                            <td className="px-3 py-2">{row.count}</td>
                        </tr>
                    )}
                />
                <CountTable
                    title="Teacher activity"
                    headers={['Teacher', 'Submissions marked', 'Marks']}
                    rows={teacher_activity}
                    emptyText="No teacher reviews yet."
                    renderRow={(row) => (
                        <tr key={row.teacher_id} className="border-t">
                            <td className="px-3 py-2">{row.name}</td>
                            <td className="px-3 py-2">{row.submissions}</td>
                            <td className="px-3 py-2">{row.marks}</td>
                        </tr>
                    )}
                />
                <CountTable
                    title="Most common wrong letters"
                    headers={['Letter', 'Name', 'Count']}
                    rows={wrong_letters}
                    emptyText="No letter mistakes yet."
                    renderRow={(row) => (
                        <tr key={row.letter_id} className="border-t">
                            <td className="px-3 py-2 text-xl">{row.arabic_character}</td>
                            <td className="px-3 py-2">{row.display_name}</td>
                            <td className="px-3 py-2">{row.count}</td>
                        </tr>
                    )}
                />
                <CountTable
                    title="Most common wrong harakas"
                    headers={['Haraka', 'Name', 'Count']}
                    rows={wrong_harakas}
                    emptyText="No haraka mistakes yet."
                    renderRow={(row) => (
                        <tr key={row.haraka_id} className="border-t">
                            <td className="px-3 py-2 text-xl">{row.symbol}</td>
                            <td className="px-3 py-2">{row.display_name}</td>
                            <td className="px-3 py-2">{row.count}</td>
                        </tr>
                    )}
                />
            </div>

            <CountTable
                title="Student progress"
                headers={['Student', 'Ranges', 'Passed', 'Mistakes', 'Avg strength']}
                rows={students}
                emptyText="No memorization progress yet."
                renderRow={(row) => (
                    <tr key={row.student_id} className="border-t">
                        <td className="px-3 py-2">{row.name}</td>
                        <td className="px-3 py-2">{row.ranges}</td>
                        <td className="px-3 py-2">{row.passed}</td>
                        <td className="px-3 py-2">{row.mistakes}</td>
                        <td className="px-3 py-2">{row.avg_strength ?? '—'}</td>
                    </tr>
                )}
            />
        </AppShell>
    );
}
