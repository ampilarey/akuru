import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';

/**
 * Manage users (docs/ADMIN_PANEL.md; C9 slice 2, STATUS §5jd): the account
 * roster — who can sign in, as what, how to reach them, whether they still
 * can — with a search, a role filter, the Roles & access door on every row,
 * the delete that keeps history, a CSV and pages. Every string is a key in
 * the admin tranche, so the screen reads in Dhivehi and Arabic too.
 */
const ROLE_TONES = {
    super_admin: 'bg-red-100 text-red-800',
    admin: 'bg-amber-100 text-amber-800',
    teacher: 'bg-violet-100 text-violet-800',
    student: 'bg-green-50 text-green-800',
    parent: 'bg-blue-50 text-blue-800',
};

export default function Users({ users = [], pagination, total = 0, filters = {}, roles = [], t = {} }) {
        const [search, setSearch] = useState(filters.search || '');
    const [role, setRole] = useState(filters.role || '');
    const query = (extra = {}) => {
        const params = new URLSearchParams({ ...(search ? { search } : {}), ...(role ? { role } : {}), ...extra });
        const s = params.toString();
        return s ? `?${s}` : '';
    };
    const submit = (e) => {
        e.preventDefault();
        router.get('/admin/users', { ...(search ? { search } : {}), ...(role ? { role } : {}) }, { preserveState: true, preserveScroll: true });
    };
    const remove = (user) => {
        if (!window.confirm((t.users_delete_confirm || 'Delete :name? This removes their enrolments and data permanently.').replace(':name', user.name))) return;
        router.delete(`/admin/users/${user.id}`, { preserveScroll: true });
    };

    return (
        <AppShell title={t.users_title || 'User Management'}>
            <div className="mb-4 flex flex-wrap items-center gap-3 text-sm">
                <p className="text-gray-600" data-testid="users-total">{(t.users_total || ':count users total').replace(':count', total)}</p>
                <a href={`/admin/users/export${query()}`} className="ms-auto underline" data-testid="users-export">{t.users_export || 'Export CSV'}</a>
                {/* Not in the nav bar: a list of who has been hammering the OTP endpoints should take a decision to open. */}
                <Link href="/admin/users/otp-abuse" className="underline">{t.users_otp_abuse || 'OTP abuse events'}</Link>
            </div>
            <form onSubmit={submit} className="mb-4 flex flex-wrap gap-2" data-testid="users-filter">
                <input className="form-input min-w-[14rem] flex-1" name="search" placeholder={t.users_search_placeholder || 'Search name, ID card, mobile, email…'} value={search} onChange={(e) => setSearch(e.target.value)} />
                <select className="form-input" name="role" value={role} onChange={(e) => setRole(e.target.value)} aria-label={t.users_col_role || 'Role'}>
                    <option value="">{t.users_all_roles || 'All roles'}</option>
                    {roles.map((r) => <option key={r.key} value={r.key}>{r.label}</option>)}
                </select>
                <button type="submit" className="btn-primary">{t.users_search || 'Search'}</button>
                {(filters.search || filters.role) && <Link href="/admin/users" className="btn-secondary">{t.users_clear || 'Clear'}</Link>}
            </form>

            {/* overflow-x-auto, not hidden: on a phone the right-hand columns were cut off with no way to reach them (STATUS §5hu). */}
            <div className="overflow-x-auto rounded-lg border bg-white" data-testid="users-table">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">#</th>
                            <th className="px-3 py-2">{t.users_col_name || 'Name'}</th>
                            <th className="px-3 py-2">{t.users_col_contact || 'Contact'}</th>
                            <th className="px-3 py-2">{t.users_col_identity || 'ID card'}</th>
                            <th className="px-3 py-2">{t.users_col_role || 'Role'}</th>
                            <th className="px-3 py-2">{t.users_col_registered || 'Registered'}</th>
                            <th className="px-3 py-2">{t.users_col_action || 'Action'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {users.length === 0 && <tr><td className="px-3 py-8 text-center text-gray-500" colSpan="7">{t.users_none || 'No users found.'}</td></tr>}
                        {users.map((u) => (
                            <tr key={u.id} className={`border-t align-top ${u.is_self ? 'bg-[#FFFBF0]' : ''}`}>
                                <td className="px-3 py-2 text-gray-400">{u.id}</td>
                                <td className="px-3 py-2">
                                    <p className="font-medium">{u.name}</p>
                                    {u.is_self && <p className="text-xs font-semibold text-amber-700">{t.users_you || 'YOU'}</p>}
                                    {!u.is_active && <p className="text-xs font-semibold text-red-700" data-testid="user-inactive">{t.users_inactive || 'Deactivated'}</p>}
                                </td>
                                <td className="px-3 py-2 text-gray-700">
                                    {u.mobile && <p>📱 {u.mobile}</p>}
                                    {u.email && <p className="text-xs text-gray-500">✉ {u.email}</p>}
                                    {!u.mobile && !u.email && <span className="text-gray-300">—</span>}
                                </td>
                                <td className="px-3 py-2 text-gray-700">{u.identity || '—'}</td>
                                <td className="px-3 py-2">
                                    {u.role
                                        ? <span data-testid="role-badge" className={`rounded-full px-2 py-0.5 text-xs font-bold ${ROLE_TONES[u.role] || 'bg-gray-100 text-gray-700'}`}>{u.role_label}</span>
                                        : <span className="text-gray-300">—</span>}
                                </td>
                                <td className="whitespace-nowrap px-3 py-2 text-gray-400">{u.registered}</td>
                                <td className="whitespace-nowrap px-3 py-2">
                                    {/* The role and access screen (ADR-040 slice 4): every row, the protected ones included. */}
                                    <Link href={`/admin/users/${u.id}/roles`} className="me-2 inline-block rounded border border-[#E6D9C8] bg-[#FFFBF0] px-2 py-1 text-xs font-semibold text-[#7C2D37]" data-testid="user-roles-link">{t.users_roles_link || 'Roles & access'}</Link>
                                    {u.is_protected
                                        ? <span className="text-xs text-gray-300">{t.users_protected || 'Protected'}</span>
                                        : <button type="button" className="rounded border border-red-200 bg-red-50 px-2 py-1 text-xs font-semibold text-red-800" onClick={() => remove(u)} data-testid="user-delete">{t.users_delete || 'Delete'}</button>}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {pagination && pagination.last_page > 1 && (
                <nav className="mt-4 flex items-center gap-3 text-sm" aria-label={t.users_pages || 'Pages'} data-testid="users-pagination">
                    {pagination.prev ? <Link href={pagination.prev} className="btn-secondary">{t.users_page_prev || '‹ Previous'}</Link> : <span className="btn-secondary opacity-50">{t.users_page_prev || '‹ Previous'}</span>}
                    <span className="text-gray-600">{(t.users_page_of || 'Page :page of :pages').replace(':page', pagination.current_page).replace(':pages', pagination.last_page)}</span>
                    {pagination.next ? <Link href={pagination.next} className="btn-secondary">{t.users_page_next || 'Next ›'}</Link> : <span className="btn-secondary opacity-50">{t.users_page_next || 'Next ›'}</span>}
                </nav>
            )}
        </AppShell>
    );
}
