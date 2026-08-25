import { router, useForm } from '@inertiajs/react';
import { useRef } from 'react';
import AppShell from '../../../Layouts/AppShell';

export default function Show({ exam, rows, progress }) {
    const importForm = useForm({ rows: [] });

    return (
        <AppShell title={`Marks — ${exam.name}`}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm text-gray-600">
                    Status {exam.status} · max {exam.max_marks} · {progress.entered}/{progress.total} entered
                    {progress.blank > 0 ? ` · ${progress.blank} blank` : ''}
                    {progress.absent ? ` · ${progress.absent} absent` : ''}
                    {progress.exempt ? ` · ${progress.exempt} exempt` : ''}
                </p>
                <div className="flex gap-2">
                    <a className="btn-secondary" href={`/exams/${exam.id}/marks/export`}>Export CSV</a>
                    <label className="btn-secondary cursor-pointer">
                        Import CSV
                        <input
                            type="file"
                            accept=".csv,text/csv"
                            className="hidden"
                            onChange={(e) => {
                                const file = e.target.files?.[0];
                                if (!file) {
                                    return;
                                }
                                const reader = new FileReader();
                                reader.onload = () => {
                                    importForm.transform(() => ({ rows: parseCsv(String(reader.result || '')) }));
                                    importForm.post(`/exams/${exam.id}/marks/import`, { preserveScroll: true });
                                };
                                reader.readAsText(file);
                            }}
                        />
                    </label>
                    <a className="btn-secondary" href="/exams/schedule">Back to exams</a>
                </div>
            </div>
            <div className="mb-3 h-2 overflow-hidden rounded bg-gray-200">
                <div
                    className="h-full bg-[#7C2D37]"
                    style={{ width: `${progress.total ? Math.round((progress.entered / progress.total) * 100) : 0}%` }}
                />
            </div>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Student</th>
                            <th className="px-3 py-2">Marks</th>
                            <th className="px-3 py-2">Absent</th>
                            <th className="px-3 py-2">Exempt</th>
                            <th className="px-3 py-2">Remarks</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={6}>No students on the roster for this exam date.</td></tr>
                        )}
                        {rows.map((row, index) => (
                            <MarkRow key={row.student_id} exam={exam} row={row} index={index} />
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}

function MarkRow({ exam, row, index }) {
    const form = useForm({
        student_id: row.student_id,
        marks: row.marks ?? '',
        is_absent: row.is_absent,
        is_exempt: row.is_exempt,
        remarks: row.remarks || '',
    });
    const marksRef = useRef(null);

    const save = () => {
        form.transform((data) => ({
            ...data,
            marks: data.is_absent || data.is_exempt ? null : data.marks,
        }));
        form.put(`/exams/${exam.id}/marks`, { preserveScroll: true });
    };

    return (
        <tr className={`border-t ${row.anomaly ? 'bg-red-50' : ''}`}>
            <td className="px-3 py-2">
                {row.name}
                {row.student_number && <span className="block text-xs text-gray-500">{row.student_number}</span>}
                {row.anomaly && <span className="block text-xs text-red-600">Above max</span>}
            </td>
            <td className="px-3 py-2">
                <input
                    ref={marksRef}
                    className="form-input w-24"
                    data-mark-row={index}
                    value={form.data.marks}
                    disabled={form.data.is_absent || form.data.is_exempt}
                    onChange={(e) => form.setData('marks', e.target.value)}
                    onBlur={save}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter' || e.key === 'ArrowDown') {
                            e.preventDefault();
                            document.querySelector(`[data-mark-row="${index + 1}"]`)?.focus();
                        }
                        if (e.key === 'ArrowUp') {
                            e.preventDefault();
                            document.querySelector(`[data-mark-row="${index - 1}"]`)?.focus();
                        }
                    }}
                />
                {form.errors.marks && <span className="block text-xs text-red-600">{form.errors.marks}</span>}
            </td>
            <td className="px-3 py-2">
                <input
                    type="checkbox"
                    checked={form.data.is_absent}
                    onChange={(e) => {
                        form.setData('is_absent', e.target.checked);
                        if (e.target.checked) {
                            form.setData('is_exempt', false);
                            form.setData('marks', '');
                        }
                        router.put(`/exams/${exam.id}/marks`, {
                            student_id: row.student_id,
                            marks: e.target.checked ? null : form.data.marks,
                            is_absent: e.target.checked,
                            is_exempt: false,
                            remarks: form.data.remarks,
                        }, { preserveScroll: true });
                    }}
                />
            </td>
            <td className="px-3 py-2">
                <input
                    type="checkbox"
                    checked={form.data.is_exempt}
                    onChange={(e) => {
                        form.setData('is_exempt', e.target.checked);
                        if (e.target.checked) {
                            form.setData('is_absent', false);
                            form.setData('marks', '');
                        }
                        router.put(`/exams/${exam.id}/marks`, {
                            student_id: row.student_id,
                            marks: e.target.checked ? null : form.data.marks,
                            is_absent: false,
                            is_exempt: e.target.checked,
                            remarks: form.data.remarks,
                        }, { preserveScroll: true });
                    }}
                />
            </td>
            <td className="px-3 py-2">
                <input className="form-input w-full" value={form.data.remarks} onChange={(e) => form.setData('remarks', e.target.value)} onBlur={save} />
            </td>
            <td className="px-3 py-2">
                <button type="button" className="btn-secondary" disabled={form.processing} onClick={save}>Save</button>
            </td>
        </tr>
    );
}

function parseCsv(text) {
    const lines = text.split(/\r?\n/).filter((line) => line.trim() !== '');
    if (lines.length < 2) {
        return [];
    }
    const headers = lines[0].split(',').map((cell) => cell.trim().replace(/^"|"$/g, ''));
    return lines.slice(1).map((line) => {
        const cells = line.split(',').map((cell) => cell.trim().replace(/^"|"$/g, ''));
        const row = {};
        headers.forEach((header, index) => {
            row[header] = cells[index] ?? '';
        });
        return row;
    });
}
