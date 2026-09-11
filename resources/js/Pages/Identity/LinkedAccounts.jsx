import { router, useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

export default function LinkedAccounts({ accounts = [], me }) {
    const form = useForm({ identifier: '', password: '' });

    return (
        <AppShell title="My accounts">
            <p className="mb-4 text-sm text-gray-600">
                Some people have two accounts here — a teacher who is also a parent, for
                instance. Link them once and you can move between them without signing out.
            </p>

            <h2 className="mb-2 text-sm font-semibold">Signed in as {me?.name}</h2>

            <ul className="mb-6 grid gap-2">
                {accounts.map((account) => (
                    <li key={account.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border bg-white p-3 text-sm">
                        <div>
                            <p className="font-medium">{account.name}</p>
                            <p className="text-xs text-gray-500">{account.roles}</p>
                        </div>
                        <div className="flex items-center gap-3">
                            <button
                                className="btn-secondary text-xs"
                                onClick={() => router.post(`/account/switch/${account.id}`)}
                            >
                                Switch to this account
                            </button>
                            <button
                                className="text-xs text-[#7C2D37] underline"
                                onClick={() => router.delete(`/account/linked/${account.id}`, { preserveScroll: true })}
                            >
                                Unlink
                            </button>
                        </div>
                    </li>
                ))}
            </ul>
            {accounts.length === 0 && (
                <p className="mb-6 rounded-lg border bg-white p-4 text-sm text-gray-600">
                    No other accounts are linked yet.
                </p>
            )}

            <form
                className="grid gap-3 rounded-lg border bg-white p-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/account/linked', { preserveScroll: true, onSuccess: () => form.reset() });
                }}
            >
                <p className="text-sm font-semibold">Link another account</p>
                <p className="text-xs text-gray-600">
                    Sign in to the other account once, here. Only somebody who knows both sets of
                    details can make the link — nobody at the school can do it for you.
                </p>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">Email, mobile or ID card number</span>
                    <input className="form-input w-full" autoComplete="off"
                        value={form.data.identifier} onChange={(e) => form.setData('identifier', e.target.value)} />
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">That account’s password</span>
                    <input className="form-input w-full" type="password" autoComplete="off"
                        value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} />
                </label>
                {form.errors.identifier && <p className="text-sm text-red-600">{form.errors.identifier}</p>}
                <button type="submit" className="btn-primary justify-self-start" disabled={form.processing}>
                    Link it
                </button>
            </form>

            <p className="mt-4 text-xs text-gray-500">
                Unlinking removes the shortcut from both accounts. Switching signs you in as the
                other account completely — you will be asked for your password again before
                anything sensitive.
            </p>
        </AppShell>
    );
}
