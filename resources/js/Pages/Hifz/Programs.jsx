import { Link } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * Hifz programmes (the Hifz port, slice 1, STATUS §5jv): the programmes
 * this person may see — every one for a dean, the assigned ones for
 * anyone else — with class, supervisor and status, and the door to a new
 * one. Every string is a key in the admin tranche.
 */
export default function Programs({ programs = { data: [] }, can_create = false, t = {} }) {
    const status = (value) => t[`hifz_status_${value}`] || value;

    return (
        <AppShell title={t.hifz_programs_title || 'Hifz Programs'}>
            <div className="mb-4 flex flex-wrap items-center gap-3 text-sm">
                <Link href="/hifz" className="underline" data-testid="hifz-hub-link">{t.hifz_back_hub || '← Hifz'}</Link>
                {can_create && <Link href="/hifz/programs/create" className="btn-primary ms-auto" data-testid="program-new">{t.hifz_program_new || 'New Program'}</Link>}
            </div>

            <div className="relative overflow-x-auto rounded-lg border bg-white" data-testid="programs-table">
                <table className="table-stack min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.hifz_col_name || 'Name'}</th>
                            <th className="px-3 py-2">{t.hifz_col_class || 'Class'}</th>
                            <th className="px-3 py-2">{t.hifz_col_supervisor || 'Supervisor'}</th>
                            <th className="px-3 py-2">{t.hifz_col_status || 'Status'}</th>
                            <th className="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {programs.data.length === 0 && (
                            <tr><td className="px-3 py-6 text-center text-gray-500" colSpan="5">{t.hifz_programs_none || 'No programs yet.'}</td></tr>
                        )}
                        {programs.data.map((program) => (
                            <tr key={program.id} className="border-t" data-testid="program-row">
                                <td className="px-3 py-2" data-label={t.hifz_col_name || 'Name'}>{program.name}</td>
                                <td className="px-3 py-2" data-label={t.hifz_col_class || 'Class'}>{program.class || '—'}</td>
                                <td className="px-3 py-2" data-label={t.hifz_col_supervisor || 'Supervisor'}>{program.supervisor || '—'}</td>
                                <td className="px-3 py-2" data-label={t.hifz_col_status || 'Status'}><span className="rounded bg-gray-100 px-2 py-1">{status(program.status)}</span></td>
                                <td className="table-actions px-3 py-2"><Link href={`/hifz/programs/${program.id}`} className="chip-link" data-testid="program-view">{t.hifz_view || 'View'}</Link></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {(programs.prev_page_url || programs.next_page_url) && (
                <div className="mt-3 flex gap-2 text-sm">
                    {programs.prev_page_url && <Link href={programs.prev_page_url} className="chip-link">{t.hifz_prev || '‹ Previous'}</Link>}
                    {programs.next_page_url && <Link href={programs.next_page_url} className="chip-link">{t.hifz_next || 'Next ›'}</Link>}
                </div>
            )}
        </AppShell>
    );
}
