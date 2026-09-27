import { router, useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * The role and access screen under Manage users (ADR-040 slice 4, BACKLOG
 * C8): the roles a person holds, as the owner names them, and whether they
 * can sign in. The two protections — the actor's own System admin role and
 * the last System admin's — arrive as `locked` and are shown, not hidden.
 */
export default function UserRoles({ t, user, roles, locked = [], back }) {
    const form = useForm({ roles: user.roles });

    const toggle = (key) => {
        form.setData('roles', form.data.roles.includes(key) ? form.data.roles.filter((r) => r !== key) : [...form.data.roles, key]);
    };

    return (
        <AppShell title={t.roles_title}>
            <p className="mb-1 text-sm text-gray-600">
                <a href={back} className="underline" data-testid="roles-back">{t.roles_back}</a>
            </p>
            <h2 className="text-lg font-semibold text-gray-900" data-testid="roles-person">{user.name}</h2>
            <p className="mb-6 text-sm text-gray-600">
                {[user.email, user.phone].filter(Boolean).join(' · ')}
                {' — '}
                <span data-testid="roles-current">{user.labels}</span>
            </p>

            <section className="mb-8 rounded-lg border border-[#E6D9C8] bg-white p-4" data-testid="roles-form">
                <h3 className="mb-1 text-base font-semibold text-gray-800">{t.roles_heading}</h3>
                <p className="mb-3 text-sm text-gray-600">{t.roles_intro}</p>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put(`/admin/users/${user.id}/roles`, { preserveScroll: true });
                    }}
                >
                    <ul className="mb-4 grid gap-2 sm:grid-cols-2">
                        {roles.map((role) => {
                            const isLocked = locked.includes(role.key);
                            return (
                                <li key={role.key}>
                                    <label className={`flex items-center gap-2 rounded border px-3 py-2 text-sm ${isLocked ? 'border-gray-200 bg-gray-50 text-gray-500' : 'border-[#E6D9C8]'}`}>
                                        <input
                                            type="checkbox"
                                            data-testid={`role-${role.key}`}
                                            checked={form.data.roles.includes(role.key)}
                                            disabled={isLocked}
                                            onChange={() => toggle(role.key)}
                                        />
                                        <span>{role.label}</span>
                                        {isLocked && <span className="ms-auto text-xs">{t.roles_locked}</span>}
                                    </label>
                                </li>
                            );
                        })}
                    </ul>
                    {locked.length > 0 && <p className="mb-3 text-xs text-gray-500">{user.is_self ? t.roles_protect_self : t.roles_protect_last}</p>}
                    {(form.errors.roles || Object.keys(form.errors).length > 0) && (
                        <p className="mb-3 text-sm text-red-700" role="alert">{form.errors.roles || Object.values(form.errors)[0]}</p>
                    )}
                    <button type="submit" className="btn-primary" disabled={form.processing} data-testid="roles-save">{t.roles_save}</button>
                </form>
            </section>

            <section className="rounded-lg border border-[#E6D9C8] bg-white p-4" data-testid="access-form">
                <h3 className="mb-1 text-base font-semibold text-gray-800">{t.access_heading}</h3>
                <p className="mb-3 text-sm" data-testid="access-state">
                    {user.is_active
                        ? <span className="text-green-800">{t.access_active}</span>
                        : <span className="text-red-800">{t.access_inactive}</span>}
                </p>
                {user.is_self ? (
                    <p className="text-xs text-gray-500">{t.access_protect_self}</p>
                ) : (
                    <button
                        type="button"
                        className={user.is_active ? 'btn-secondary' : 'btn-primary'}
                        data-testid="access-toggle"
                        onClick={() => router.post(`/admin/users/${user.id}/active`, { active: user.is_active ? 0 : 1 }, { preserveScroll: true })}
                    >
                        {user.is_active ? t.access_deactivate : t.access_activate}
                    </button>
                )}
            </section>
        </AppShell>
    );
}
