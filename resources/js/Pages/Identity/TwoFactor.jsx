import { useEffect, useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import QRCode from 'qrcode';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * STATUS §5lk — the signed-in person's own two-step sign-in: turn it on with
 * an authenticator app (scan, then a first code), keep the recovery codes
 * (shown once), make new ones or turn it off with the password.
 */
function Qr({ uri, t }) {
    const [src, setSrc] = useState(null);
    useEffect(() => {
        QRCode.toDataURL(uri, { margin: 1, width: 220 }).then(setSrc).catch(() => setSrc(null));
    }, [uri]);

    return src ? <img src={src} alt={t.qr_alt} className="h-[220px] w-[220px] rounded border bg-white p-2" data-testid="two-factor-qr" /> : null;
}

export default function TwoFactor({ t = {}, status, pending = null, recovery_codes = null }) {
    const { errors } = usePage().props;
    const confirm = useForm({ code: '' });
    const password = useForm({ password: '' });

    return (
        <AppShell title={t.title}>
            <FormErrors errors={errors} className="mb-4" />
            <p className="mb-4 max-w-2xl text-sm text-gray-600">{t.intro}</p>

            <p className="mb-4 text-sm" data-testid="two-factor-state">
                <span className={`rounded px-2 py-0.5 font-semibold ${status.enabled ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'}`}>{status.enabled ? t.state_on : t.state_off}</span>
                {status.enabled && <>{' '}<span className="ms-1 text-gray-600">{(t.recovery_left || '').replace(':count', status.recovery_left)}</span></>}
            </p>

            {recovery_codes && (
                <section className="mb-6 max-w-xl rounded-lg border border-amber-300 bg-amber-50 p-4" data-testid="recovery-codes">
                    <h2 className="font-semibold text-amber-900">{t.codes_title}</h2>
                    <p className="mb-2 text-sm text-amber-900">{t.codes_intro}</p>
                    <ul className="grid grid-cols-2 gap-1 font-mono text-sm" dir="ltr">
                        {recovery_codes.map((code) => <li key={code} className="rounded bg-white px-2 py-1">{code}</li>)}
                    </ul>
                </section>
            )}

            {!status.enabled && !pending && (
                <button type="button" className="btn-primary" onClick={() => router.post('/account/two-factor/start', {}, { preserveScroll: true })} data-testid="two-factor-start">{t.start}</button>
            )}

            {!status.enabled && pending && (
                <section className="max-w-xl rounded-lg border bg-white p-4" data-testid="two-factor-setup">
                    <ol className="mb-3 list-decimal space-y-1 ps-5 text-sm">
                        <li>{t.step_app}</li>
                        <li>{t.step_scan}</li>
                        <li>{t.step_code}</li>
                    </ol>
                    <div className="flex flex-wrap items-start gap-4">
                        <Qr uri={pending.uri} t={t} />
                        <div className="text-sm">
                            <p className="text-gray-600">{t.manual}</p>
                            <p className="mt-1 break-all font-mono font-semibold" dir="ltr" data-testid="two-factor-secret">{pending.secret}</p>
                        </div>
                    </div>
                    <form className="mt-4 flex flex-wrap items-end gap-2" onSubmit={(e) => { e.preventDefault(); confirm.post('/account/two-factor/confirm', { preserveScroll: true, onSuccess: () => confirm.reset() }); }}>
                        <label className="text-sm">{t.code_label}
                            <input className="form-input mt-1 block w-40 text-center tracking-widest" inputMode="numeric" autoComplete="one-time-code" maxLength={6} dir="ltr" value={confirm.data.code} onChange={(e) => confirm.setData('code', e.target.value)} data-testid="two-factor-confirm-code" />
                        </label>
                        <button type="submit" className="btn-primary" disabled={confirm.processing} data-testid="two-factor-confirm">{t.confirm}</button>
                    </form>
                </section>
            )}

            {status.enabled && (
                <section className="max-w-xl rounded-lg border bg-white p-4">
                    <p className="mb-2 text-sm text-gray-600">{t.manage_intro}</p>
                    <div className="flex flex-wrap items-end gap-2">
                        <label className="text-sm">{t.password_label}
                            <input type="password" className="form-input mt-1 block w-56" autoComplete="current-password" value={password.data.password} onChange={(e) => password.setData('password', e.target.value)} data-testid="two-factor-password" />
                        </label>
                        <button type="button" className="btn-secondary" onClick={() => password.post('/account/two-factor/recovery-codes', { preserveScroll: true, onSuccess: () => password.reset() })} data-testid="two-factor-new-codes">{t.new_codes}</button>
                        <button type="button" className="text-sm text-red-700 underline" onClick={() => password.post('/account/two-factor/disable', { preserveScroll: true, onSuccess: () => password.reset() })} data-testid="two-factor-disable">{t.disable}</button>
                    </div>
                </section>
            )}
        </AppShell>
    );
}
