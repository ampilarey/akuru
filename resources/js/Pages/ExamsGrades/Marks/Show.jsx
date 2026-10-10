import { useForm } from '@inertiajs/react';
import { useRef } from 'react';
import AppShell from '../../../Layouts/AppShell';

/**
 * An exam's marks, one row a pupil. Every word is the `exams` book's (slice
 * EG1, STATUS §5qj), and a refused import is said above the table — it was
 * said nowhere.
 */
export default function Show({ exam, rows, progress, t = {} }) {
    const importForm = useForm({ rows: [] });
    const refused = importForm.errors.rows;
    const parts = [
        (t.marks_summary || 'Status :status · max :max · :entered/:total entered')
            .replace(':status', t[`exam_status_${exam.status}`] || exam.status)
            .replace(':max', exam.max_marks)
            .replace(':entered', progress.entered)
            .replace(':total', progress.total),
        progress.blank > 0 ? (t.marks_blank || ':count blank').replace(':count', progress.blank) : null,
        progress.absent ? (t.marks_absent_count || ':count absent').replace(':count', progress.absent) : null,
        progress.exempt ? (t.marks_exempt_count || ':count exempt').replace(':count', progress.exempt) : null,
    ].filter(Boolean);

    return (
        <AppShell title={(t.marks_title || 'Marks — :name').replace(':name', exam.name)}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm text-gray-600">{parts.join(' · ')}</p>
                <div className="flex gap-2">
                    <a className="btn-secondary" href={`/exams/${exam.id}/marks/export`}>{t.export_csv || 'Export CSV'}</a>
                    <label className="btn-secondary cursor-pointer">
                        {t.marks_import_csv || 'Import CSV'}
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
                    <a className="btn-secondary" href="/exams/schedule">{t.marks_back || 'Back to exams'}</a>
                </div>
            </div>
            {refused && <p className="mb-3 rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700" role="alert">{refused}</p>}
            <div className="mb-3 h-2 overflow-hidden rounded bg-gray-200">
                <div
                    className="h-full bg-[#7C2D37]"
                    style={{ width: `${progress.total ? Math.round((progress.entered / progress.total) * 100) : 0}%` }}
                />
            </div>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.student || 'Student'}</th>
                            <th className="px-3 py-2">{t.marks_col_marks || 'Marks'}</th>
                            <th className="px-3 py-2">{t.marks_col_absent || 'Absent'}</th>
                            <th className="px-3 py-2">{t.marks_col_exempt || 'Exempt'}</th>
                            <th className="px-3 py-2">{t.marks_col_remarks || 'Remarks'}</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={6}>{t.marks_none || 'No students on the roster for this exam date.'}</td></tr>
                        )}
                        {rows.map((row, index) => (
                            <MarkRow key={row.student_id} exam={exam} row={row} index={index} t={t} />
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}

function MarkRow({ exam, row, index, t }) {
    const form = useForm({
        student_id: row.student_id,
        marks: row.marks ?? '',
        is_absent: row.is_absent,
        is_exempt: row.is_exempt,
        remarks: row.remarks || '',
    });
    const marksRef = useRef(null);
    const forPupil = (phrase) => phrase.replace(':name', row.name);
    const refused = form.errors.marks || form.errors.is_absent || form.errors.student_id || form.errors.status;

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
                {row.anomaly && <span className="block text-xs text-red-600">{t.marks_above_max || 'Above max'}</span>}
            </td>
            <td className="px-3 py-2">
                <input
                    ref={marksRef}
                    className="form-input w-24"
                    data-mark-row={index}
                    aria-label={forPupil(t.marks_for || 'Marks for :name')}
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
                {refused && <span className="block text-xs text-red-600">{refused}</span>}
            </td>
            <td className="px-3 py-2">
                <input
                    type="checkbox"
                    aria-label={forPupil(t.marks_absent_for || 'Absent: :name')}
                    checked={form.data.is_absent}
                    onChange={(e) => {
                        const checked = e.target.checked;
                        form.setData('is_absent', checked);
                        if (checked) {
                            form.setData('is_exempt', false);
                            form.setData('marks', '');
                        }
                        form.transform(() => ({
                            student_id: row.student_id,
                            marks: checked ? null : form.data.marks,
                            is_absent: checked,
                            is_exempt: false,
                            remarks: form.data.remarks,
                        }));
                        form.put(`/exams/${exam.id}/marks`, { preserveScroll: true });
                    }}
                />
            </td>
            <td className="px-3 py-2">
                <input
                    type="checkbox"
                    aria-label={forPupil(t.marks_exempt_for || 'Exempt: :name')}
                    checked={form.data.is_exempt}
                    onChange={(e) => {
                        const checked = e.target.checked;
                        form.setData('is_exempt', checked);
                        if (checked) {
                            form.setData('is_absent', false);
                            form.setData('marks', '');
                        }
                        form.transform(() => ({
                            student_id: row.student_id,
                            marks: checked ? null : form.data.marks,
                            is_absent: false,
                            is_exempt: checked,
                            remarks: form.data.remarks,
                        }));
                        form.put(`/exams/${exam.id}/marks`, { preserveScroll: true });
                    }}
                />
            </td>
            <td className="px-3 py-2">
                <input
                    className="form-input w-full"
                    aria-label={forPupil(t.marks_remarks_for || 'Remarks for :name')}
                    value={form.data.remarks}
                    onChange={(e) => form.setData('remarks', e.target.value)}
                    onBlur={save}
                />
            </td>
            <td className="px-3 py-2">
                <button type="button" className="btn-secondary" disabled={form.processing} onClick={save}>{t.save || 'Save'}</button>
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
