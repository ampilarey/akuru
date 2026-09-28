import { Link, useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * Choosing a password, inside the shell (docs/SIGN_IN_PLAN.md ID3). The
 * prompt on a person's workspace home leads here, and saving leads back to
 * that home. It was a Blade form in the website's layout that sent people
 * on to the marketing home.
 *
 * The current password is asked for exactly when the server will require it:
 * an account that has only signed in with a code carries a random hash
 * nobody knows, and must not be asked for it.
 */
const field = 'mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-[#7C2D37] focus:ring-[#7C2D37]';

export default function SetPassword({ t = {}, needs_current_password = true, store_href }) {
    const form = useForm({ current_password: '', password: '', password_confirmation: '' });
    const submit = (event) => {
        event.preventDefault();
        form.post(store_href, { onFinish: () => form.reset('current_password', 'password', 'password_confirmation') });
    };

    return (
        <AppShell title={t.password_title || 'Choose a password'}>
            <form onSubmit={submit} className="max-w-md rounded-lg border bg-white p-5" data-testid="set-password-form">
                <p className="mb-4 text-sm text-gray-600">{t.password_intro}</p>
                <FormErrors errors={form.errors} className="mb-3" />
                {needs_current_password && (
                    <label className="mb-4 block text-sm font-medium text-gray-700">
                        {t.password_current}
                        <input type="password" name="current_password" autoComplete="current-password" required className={field}
                            value={form.data.current_password} onChange={(e) => form.setData('current_password', e.target.value)} />
                        <span className="mt-1 block text-xs font-normal text-gray-500">{t.password_current_hint}</span>
                    </label>
                )}
                <label className="mb-4 block text-sm font-medium text-gray-700">
                    {t.password_new}
                    <input type="password" name="password" autoComplete="new-password" required minLength={8} className={field}
                        value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} />
                    <span className="mt-1 block text-xs font-normal text-gray-500">{t.password_new_hint}</span>
                </label>
                <label className="mb-5 block text-sm font-medium text-gray-700">
                    {t.password_confirm}
                    <input type="password" name="password_confirmation" autoComplete="new-password" required className={field}
                        value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} />
                </label>
                <div className="flex flex-wrap items-center gap-4">
                    <button type="submit" disabled={form.processing} className="rounded-md bg-[#7C2D37] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5A1F28] disabled:opacity-60">
                        {t.password_save}
                    </button>
                    <Link href="/dashboard" className="text-sm text-gray-600 underline">{t.password_later}</Link>
                </div>
            </form>
        </AppShell>
    );
}
