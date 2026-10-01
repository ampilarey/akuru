import { useForm, usePage } from '@inertiajs/react';
import { IdentityCardFields } from '../../Components/IdentityCard';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * BOOKSHOP_PLAN slice B9a — "Open a shop in the Akuru Bookstore": a
 * signed-in person tells the office about their shop and accepts the
 * Vendor Agreement; the office decides. Shows the latest application's
 * state, and the way into the portal once a shop exists.
 */

function Status({ application, t }) {
    const tone = { pending: 'bg-amber-50 text-amber-900', approved: 'bg-green-50 text-green-800', declined: 'bg-red-50 text-red-800' }[application.status] || 'bg-gray-50';

    return (
        <div className={`mb-6 rounded p-4 ${tone}`} data-testid="application-status" data-status={application.status}>
            <p className="font-semibold">{t[`application_status_${application.status}`]}</p>
            <p className="text-sm">{t.application_for} {application.shop_name} · {application.submitted_at}</p>
            {application.status === 'declined' && application.decision_note && <p className="mt-2 text-sm" dir="auto">{application.decision_note}</p>}
            {application.status === 'approved' && application.vendor && (
                <a href="/vendor" className="btn-primary mt-3 inline-block" data-testid="open-portal">{t.open_your_portal}</a>
            )}
        </div>
    );
}

export default function VendorApply({ t, open, application, shops = [], agreement_url, defaults, id_l = {} }) {
    const { errors } = usePage().props;
    const form = useForm({ kind: 'shop', shop_name: '', legal_name: '', tin: '', contact_email: defaults.contact_email || '', contact_phone: defaults.contact_phone || '', island: '', what_they_sell: '', link: '', agreement: false, id_front: null, id_back: null });
    const set = (name) => (e) => form.setData(name, e.target.value);
    const waiting = application?.status === 'pending';

    return (
        <AppShell title={t.apply_title}>
            <FormErrors errors={errors} className="mb-4" />
            <h1 className="mb-1 text-2xl font-bold" data-testid="apply-heading">{t.apply_title}</h1>
            <p className="mb-6 max-w-2xl text-gray-600">{t.apply_intro}</p>

            {shops.length > 0 && (
                <p className="mb-4 rounded bg-blue-50 p-3 text-sm text-blue-900" data-testid="already-a-shop">
                    {t.apply_already_member.replace(':shops', shops.map((s) => s.name).join(', '))} <a href="/vendor" className="font-semibold underline">{t.open_your_portal}</a>
                </p>
            )}
            {application && <Status application={application} t={t} />}

            {!open ? (
                <p className="rounded border bg-white p-4 text-gray-700" data-testid="applications-closed">{t.applications_closed}</p>
            ) : waiting ? null : (
                <form
                    className="grid max-w-3xl gap-4 rounded-lg border bg-white p-4 md:grid-cols-2"
                    onSubmit={(e) => { e.preventDefault(); form.post('/vendor/apply', { forceFormData: true }); }}
                    data-testid="apply-form"
                >
                    {/* LENDING_AND_USED_BOOKS_PLAN U1 (D1): a shop, or a person selling their own used books. */}
                    <fieldset className="grid gap-2 md:col-span-2 sm:grid-cols-2" data-testid="apply-kind">
                        <legend className="mb-1 text-sm font-medium">{t.apply_kind}</legend>
                        {['shop', 'personal'].map((k) => (
                            <label key={k} className={`flex cursor-pointer items-start gap-2 rounded border p-3 text-sm ${form.data.kind === k ? 'border-brandMaroon-600 bg-brandBeige-50' : 'border-gray-200'}`}>
                                <input type="radio" name="kind" value={k} checked={form.data.kind === k} onChange={() => form.setData('kind', k)} className="mt-1" data-testid={`apply-kind-${k}`} />
                                <span><span className="block font-semibold">{t[`apply_kind_${k}`]}</span><span className="block text-xs text-gray-600">{t[`apply_kind_${k}_hint`]}</span></span>
                            </label>
                        ))}
                    </fieldset>
                    <label className="text-sm">{form.data.kind === 'personal' ? t.apply_seller_name : t.apply_shop_name}<input className="form-input w-full" value={form.data.shop_name} onChange={set('shop_name')} required maxLength={120} data-testid="apply-shop-name" /></label>
                    <label className="text-sm">{t.apply_island}<input className="form-input w-full" value={form.data.island} onChange={set('island')} required maxLength={120} placeholder={t.apply_island_hint} data-testid="apply-island" /></label>
                    {form.data.kind === 'shop' && <label className="text-sm">{t.apply_legal_name}<input className="form-input w-full" value={form.data.legal_name} onChange={set('legal_name')} maxLength={255} data-testid="apply-legal-name" /></label>}
                    {form.data.kind === 'shop' && <label className="text-sm">{t.apply_tin}<input className="form-input w-full" value={form.data.tin} onChange={set('tin')} maxLength={40} data-testid="apply-tin" /></label>}
                    <label className="text-sm">{t.apply_email}<input className="form-input w-full" type="email" value={form.data.contact_email} onChange={set('contact_email')} required data-testid="apply-email" /></label>
                    <label className="text-sm">{t.apply_phone}<input className="form-input w-full" value={form.data.contact_phone} onChange={set('contact_phone')} required maxLength={40} data-testid="apply-phone" /></label>
                    <label className="text-sm md:col-span-2">{form.data.kind === 'personal' ? t.apply_what_personal : t.apply_what_they_sell}
                        <textarea className="form-input w-full" rows={4} value={form.data.what_they_sell} onChange={set('what_they_sell')} required minLength={20} maxLength={2000} placeholder={form.data.kind === 'personal' ? t.apply_what_personal_hint : t.apply_what_hint} data-testid="apply-what" />
                    </label>
                    {form.data.kind === 'shop' && <label className="text-sm md:col-span-2">{t.apply_link}<input className="form-input w-full" type="url" value={form.data.link} onChange={set('link')} placeholder="https://" data-testid="apply-link" /></label>}
                    <IdentityCardFields form={form} l={id_l} />
                    <label className="flex items-start gap-2 text-sm md:col-span-2">
                        <input type="checkbox" checked={form.data.agreement} onChange={(e) => form.setData('agreement', e.target.checked)} required data-testid="apply-agreement" />
                        <span>{t.apply_agreement} <a href={agreement_url} target="_blank" rel="noreferrer" className="text-blue-700 underline">{t.vendor_agreement}</a></span>
                    </label>
                    <p className="text-xs text-gray-500 md:col-span-2">{t.apply_what_next}</p>
                    <div className="md:col-span-2"><button type="submit" className="btn-primary" disabled={form.processing} data-testid="apply-submit">{t.apply_submit}</button></div>
                </form>
            )}
        </AppShell>
    );
}
