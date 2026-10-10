import { Link, router, useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../Components/FormErrors';

/**
 * The accounts a person has linked to theirs, and moving between them. Every
 * word is the `account` book's (slice AC1, STATUS §5qs); a refused Switch or
 * Unlink is said under its account — it was said nowhere — or above the list
 * when the account is no longer on it, and every refusal of the link form is
 * said, not only the sign-in details'.
 */
export default function LinkedAccounts({ accounts = [], me, two_factor = null, t = {} }) {
    const form = useForm({ identifier: '', password: '' });
    const refusals = useRowRefusals(form);

    return (
        <AppShell title={t.linked_title || 'My accounts'}>
            {two_factor && (
                <Link href="/account/two-factor" className="mb-4 flex items-center justify-between gap-2 rounded-lg border bg-white p-3 text-sm hover:bg-gray-50" data-testid="two-factor-link">
                    <span className="font-medium">{two_factor.label}</span>
                    <span className={`rounded px-2 py-0.5 text-xs font-semibold ${two_factor.enabled ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'}`}>{two_factor.enabled ? two_factor.on : two_factor.off}</span>
                </Link>
            )}
            <p className="mb-4 text-sm text-gray-600">
                {t.linked_intro || 'Some people have two accounts here — a teacher who is also a parent, for instance. Link them once and you can move between them without signing out.'}
            </p>

            <h2 className="mb-2 text-sm font-semibold">{(t.linked_signed_in_as || 'Signed in as :name').replace(':name', me?.name ?? '')}</h2>
            <FormErrors errors={refusals.unplacedAmong(accounts.map((account) => `account:${account.id}`))} className="mb-2" />

            <ul className="mb-6 grid gap-2">
                {accounts.map((account) => (
                    <li key={account.id} className="rounded-lg border bg-white p-3 text-sm">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p className="font-medium">{account.name}</p>
                                <p className="text-xs text-gray-500">{account.roles}</p>
                            </div>
                            <div className="flex items-center gap-3">
                                <button
                                    type="button"
                                    className="btn-secondary text-xs"
                                    onClick={() => refusals.actOn(`account:${account.id}`, () => router.post(`/account/switch/${account.id}`))}
                                >
                                    {t.linked_switch || 'Switch to this account'}
                                </button>
                                <button
                                    type="button"
                                    className="text-xs text-[#7C2D37] underline"
                                    onClick={() => refusals.actOn(`account:${account.id}`, () => router.delete(`/account/linked/${account.id}`, { preserveScroll: true }))}
                                >
                                    {t.linked_unlink || 'Unlink'}
                                </button>
                            </div>
                        </div>
                        <FormErrors errors={refusals.errorsFor(`account:${account.id}`)} className="mt-1" />
                    </li>
                ))}
            </ul>
            {accounts.length === 0 && (
                <p className="mb-6 rounded-lg border bg-white p-4 text-sm text-gray-600">
                    {t.linked_none || 'No other accounts are linked yet.'}
                </p>
            )}

            <form
                className="grid gap-3 rounded-lg border bg-white p-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/account/linked', { preserveScroll: true, onSuccess: () => form.reset() });
                }}
            >
                <p className="text-sm font-semibold">{t.linked_add || 'Link another account'}</p>
                <p className="text-xs text-gray-600">
                    {t.linked_add_hint || 'Sign in to the other account once, here. Only somebody who knows both sets of details can make the link — nobody at the school can do it for you.'}
                </p>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.linked_identifier || 'Email, mobile or ID card number'}</span>
                    <input className="form-input w-full" autoComplete="off"
                        value={form.data.identifier} onChange={(e) => form.setData('identifier', e.target.value)} />
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.linked_password || 'That account’s password'}</span>
                    <input className="form-input w-full" type="password" autoComplete="off"
                        value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} />
                </label>
                <FormErrors errors={form.errors} />
                <button type="submit" className="btn-primary justify-self-start" disabled={form.processing}>
                    {t.linked_link || 'Link it'}
                </button>
            </form>

            <p className="mt-4 text-xs text-gray-500">
                {t.linked_footnote || 'Unlinking removes the shortcut from both accounts. Switching signs you in as the other account completely — you will be asked for your password again before anything sensitive.'}
            </p>
        </AppShell>
    );
}
