import AppShell from '../../../Layouts/AppShell';

// A code the server sends, named from the `quran` book (slice CT5a).
const named = (q, family, code) => (code ? q[`${family}${code}`] || code.replaceAll('_', ' ') : '—');

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
    total_submissions, by_status, mistake_types, wrong_letters, wrong_harakas, teacher_activity, students, t = {}, q = {},
}) {
    return (
        <AppShell title={t.qover_title || 'Qur’an oversight'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/catalog/quran/oversight?format=csv">{t.catalog_export || 'Export CSV'}</a>
            </div>

            <div className="mb-6 grid gap-3 md:grid-cols-4">
                <div className="rounded-lg border bg-white p-4">
                    <div className="text-2xl font-bold">{total_submissions}</div>
                    <div className="text-sm text-gray-600">{t.qover_total || 'Total submissions'}</div>
                </div>
                {Object.entries(by_status).map(([status, count]) => (
                    <div key={status} className="rounded-lg border bg-white p-4">
                        <div className="text-2xl font-bold">{count}</div>
                        <div className="text-sm text-gray-600">{named(q, 'status_', status)}</div>
                    </div>
                ))}
            </div>

            <div className="grid gap-x-6 lg:grid-cols-2">
                <CountTable
                    title={t.qover_mistakes || 'Common mistakes'}
                    headers={[t.qt_col_type || 'Type', t.qover_count || 'Count']}
                    rows={mistake_types}
                    emptyText={t.qover_no_mistakes || 'No mistakes recorded yet.'}
                    renderRow={(row) => (
                        <tr key={row.type} className="border-t">
                            <td className="px-3 py-2">{named(q, 'mistake_', row.type)}</td>
                            <td className="px-3 py-2">{row.count}</td>
                        </tr>
                    )}
                />
                <CountTable
                    title={t.qover_teachers || 'Teacher activity'}
                    headers={[t.qover_teacher || 'Teacher', t.qover_marked || 'Submissions marked', t.qover_marks || 'Marks']}
                    rows={teacher_activity}
                    emptyText={t.qover_no_teachers || 'No teacher reviews yet.'}
                    renderRow={(row) => (
                        <tr key={row.teacher_id} className="border-t">
                            <td className="px-3 py-2">{row.name}</td>
                            <td className="px-3 py-2">{row.submissions}</td>
                            <td className="px-3 py-2">{row.marks}</td>
                        </tr>
                    )}
                />
                <CountTable
                    title={t.qover_letters || 'Most common wrong letters'}
                    headers={[t.qover_letter || 'Letter', t.qover_name || 'Name', t.qover_count || 'Count']}
                    rows={wrong_letters}
                    emptyText={t.qover_no_letters || 'No letter mistakes yet.'}
                    renderRow={(row) => (
                        <tr key={row.letter_id} className="border-t">
                            <td className="px-3 py-2 text-xl">{row.arabic_character}</td>
                            <td className="px-3 py-2">{row.display_name}</td>
                            <td className="px-3 py-2">{row.count}</td>
                        </tr>
                    )}
                />
                <CountTable
                    title={t.qover_harakas || 'Most common wrong harakas'}
                    headers={[t.qover_haraka || 'Haraka', t.qover_name || 'Name', t.qover_count || 'Count']}
                    rows={wrong_harakas}
                    emptyText={t.qover_no_harakas || 'No haraka mistakes yet.'}
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
                title={t.qover_students || 'Student progress'}
                headers={[t.qt_col_student || 'Student', t.qover_ranges || 'Ranges', t.qover_passed || 'Passed', t.qt_col_mistakes || 'Mistakes', t.qover_strength || 'Avg strength']}
                rows={students}
                emptyText={t.qover_no_students || 'No memorization progress yet.'}
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
