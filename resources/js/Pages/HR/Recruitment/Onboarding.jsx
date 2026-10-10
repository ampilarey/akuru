import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../../Components/FormErrors';

/**
 * A member of staff's onboarding or offboarding checklist. Every word is the
 * `hr` book's (slice HR2, STATUS §5qm); a checklist's items are the school's
 * (HR settings). A refused checklist or tick is said where it was asked for —
 * they were said nowhere.
 */
export default function Onboarding({ kind, staff, rows, t = {} }) {
    const offboarding = kind === 'offboarding';
    const seed = useForm({
        staff_profile_id: staff[0]?.id || '',
        kind,
    });
    const refusals = useRowRefusals(seed);

    return (
        <AppShell title={offboarding ? (t.offboarding_title || 'Offboarding') : (t.onboarding_title || 'Onboarding')}>
            <div className="mb-4 flex flex-wrap justify-between gap-3">
                <div className="flex gap-3">
                    <a className="btn-secondary" href="/hr/onboarding?kind=onboarding">{t.onboarding_title || 'Onboarding'}</a>
                    <a className="btn-secondary" href="/hr/onboarding?kind=offboarding">{t.offboarding_title || 'Offboarding'}</a>
                </div>
                <a className="btn-secondary" href={`/hr/onboarding/export?kind=${kind}`}>{t.export_csv || 'Export CSV'}</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    seed.post('/hr/onboarding/seed', { preserveScroll: true });
                }}
                className="mb-4 flex flex-wrap gap-3 rounded-lg border bg-white p-4"
            >
                <select className="form-input" aria-label={t.staff_member || 'Staff member'} value={seed.data.staff_profile_id} onChange={(e) => seed.setData('staff_profile_id', e.target.value)}>
                    {staff.map((row) => <option key={row.id} value={row.id}>{row.first_name} {row.last_name}</option>)}
                </select>
                <button type="submit" className="btn-primary" disabled={seed.processing}>{t.onboarding_open || 'Open checklist'}</button>
                <FormErrors errors={seed.errors} className="w-full" />
            </form>
            <FormErrors errors={refusals.unplaced} className="mb-4" />
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.staff || 'Staff'}</th>
                            <th className="px-3 py-2">{t.onboarding_item || 'Item'}</th>
                            <th className="px-3 py-2">{t.onboarding_done || 'Done'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={3}>{t.onboarding_none || 'No checklist open.'}</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.staff_name}</td>
                                <td className="px-3 py-2">{row.item}</td>
                                <td className="px-3 py-2">
                                    <button
                                        type="button"
                                        className="btn-secondary"
                                        onClick={() => refusals.actOn(`item:${row.id}`, () => router.post(`/hr/onboarding/${row.id}/toggle`, { done: !row.done }, { preserveScroll: true }))}
                                    >
                                        {row.done ? (t.onboarding_done || 'Done') : (t.onboarding_mark_done || 'Mark done')}
                                    </button>
                                    <FormErrors errors={refusals.errorsFor(`item:${row.id}`)} className="mt-1" />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
