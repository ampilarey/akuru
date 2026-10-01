import { Link } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * Prayer recipient groups (C9 slice 12, STATUS §5jn): the groups by name
 * with their member count and whether they are active, a CSV and the door
 * to a new one. Every string is a key in the admin tranche.
 */
export default function Groups({ groups = [], t = {} }) {
    
    return (
        <AppShell title={t.prayer_groups_title || 'Recipient groups'}>
            <div className="mb-4 flex flex-wrap items-center gap-3 text-sm">
                <a href="/admin/prayer-times/groups/export" className="btn-secondary text-sm" data-testid="export-csv">{t.prayer_export || 'Export CSV'}</a>
                <Link href="/admin/prayer-times/islands" className="underline" data-testid="prayer-islands-link">{t.prayer_link_islands || 'Islands →'}</Link>
                <Link href="/admin/prayer-times/groups/create" className="btn-primary ms-auto" data-testid="group-new">{t.prayer_group_new || 'New group'}</Link>
            </div>
            <div className="overflow-x-auto rounded-lg border bg-white" data-testid="groups-table">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.prayer_col_name || 'Name'}</th>
                            <th className="px-3 py-2">{t.prayer_col_members || 'Members'}</th>
                            <th className="px-3 py-2">{t.prayer_col_active || 'Active'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {groups.length === 0 && (
                            <tr><td className="px-3 py-6 text-center text-gray-500" colSpan="3">{t.prayer_groups_none || 'No groups yet.'}</td></tr>
                        )}
                        {groups.map((group) => (
                            <tr key={group.id} className="border-t" data-testid="group-row">
                                <td className="px-3 py-2"><Link href={`/admin/prayer-times/groups/${group.id}/edit`} className="text-[#1D4E89] underline" data-testid="group-edit">{group.name}</Link></td>
                                <td className="px-3 py-2">{group.members}</td>
                                <td className="px-3 py-2">{group.is_active ? (t.prayer_yes || 'yes') : (t.prayer_no || 'no')}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
