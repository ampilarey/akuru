import { useForm, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * COMMERCE_PARITY_PLAN P6b: a driver's deliveries, on the phone. Picked up
 * tells the customer it is on its way; Delivered wants a photo from the
 * camera, which only the office and this driver can open.
 */
function Delivered({ delivery, t }) {
    const form = useForm({ photo: null, note: '', cash_received: false });

    return (
        <form className="mt-2 grid gap-2" onSubmit={(e) => { e.preventDefault(); form.post(`/deliveries/${delivery.id}/delivered`, { forceFormData: true, preserveScroll: true }); }} data-testid={`deliver-form-${delivery.id}`}>
            <label className="text-sm">{t.driver_photo}
                <input type="file" accept="image/*" capture="environment" className="mt-1 block w-full text-sm" onChange={(e) => form.setData('photo', e.target.files[0] ?? null)} required data-testid={`proof-${delivery.id}`} />
            </label>
            <input className="form-input text-sm" placeholder={t.driver_note} aria-label={t.driver_note} value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} maxLength={255} />
            {delivery.cash_due && (
                <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.cash_received} onChange={(e) => form.setData('cash_received', e.target.checked)} /> {(t.cash_to_collect || '').replace(':amount', delivery.cash_due)}</label>
            )}
            {(form.errors.photo || form.errors.status) && <span className="text-xs text-red-700">{form.errors.photo || form.errors.status}</span>}
            <button type="submit" className="btn-primary" disabled={form.processing} data-testid={`delivered-${delivery.id}`}>{t.driver_delivered}</button>
        </form>
    );
}

function PickedUp({ delivery, t }) {
    const form = useForm({});

    return (
        <form className="mt-2" onSubmit={(e) => { e.preventDefault(); form.post(`/deliveries/${delivery.id}/picked-up`, { preserveScroll: true }); }}>
            {form.errors.status && <span className="block text-xs text-red-700">{form.errors.status}</span>}
            <button type="submit" className="btn-primary w-full" disabled={form.processing} data-testid={`picked-up-${delivery.id}`}>{t.driver_picked_up}</button>
        </form>
    );
}

export default function Deliveries({ t = {}, deliveries = [] }) {
    const { errors } = usePage().props;

    return (
        <AppShell title={t.deliveries_title}>
            <FormErrors errors={errors} className="mb-4" />
            <p className="mb-4 text-sm text-gray-600">{t.deliveries_intro}</p>
            {deliveries.length === 0 && <p className="rounded border bg-white p-3 text-sm text-gray-600" data-testid="deliveries-empty">{t.deliveries_empty}</p>}
            <ul className="grid gap-3" data-testid="deliveries">
                {deliveries.map((d) => (
                    <li key={d.id} className="rounded-lg border bg-white p-3 text-sm" data-testid={`delivery-${d.number}`} data-state={d.delivered_at ? 'delivered' : d.picked_up_at ? 'picked_up' : 'assigned'}>
                        <p className="font-semibold">{d.number} · {d.shop}</p>
                        <p>{d.recipient} · <a href={`tel:${d.phone}`} className="text-blue-700 underline">{d.phone}</a></p>
                        <p className="text-gray-700">{d.address}</p>
                        <ul className="mt-1 list-disc ps-5 text-gray-600">{d.items.map((i, k) => <li key={k}>{i}</li>)}</ul>
                        {d.delivered_at ? (
                            <p className="mt-2 text-green-800">{t.driver_delivered} {d.delivered_at}{d.proof_url && <> · <a href={d.proof_url} target="_blank" rel="noreferrer" className="underline" data-testid="proof-link">{t.proof_photo}</a></>}</p>
                        ) : d.picked_up_at ? (
                            <>
                                <p className="mt-1 text-sky-800">{t.out_for_delivery} · {d.picked_up_at}</p>
                                <Delivered delivery={d} t={t} />
                            </>
                        ) : <PickedUp delivery={d} t={t} />}
                    </li>
                ))}
            </ul>
        </AppShell>
    );
}
