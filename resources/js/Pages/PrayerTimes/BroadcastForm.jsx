import { Link, router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * A prayer broadcast draft (C9 slice 12, STATUS §5jn): mode, island, the
 * dates, the language, a recipient group or ad-hoc refs; then, once saved,
 * Preview (which writes the snapshot: who is included and excluded, the
 * cost, the messages in three languages, whether a range must be split)
 * and Confirm & queue, which the snapshot gates. Keyed on the broadcast so
 * a new draft's save, which lands on its edit page, starts the form afresh.
 */
export default function BroadcastForm(props) {
    return <BroadcastFormBody key={props.broadcast?.id ?? 'new'} {...props} />;
}

function BroadcastFormBody({ broadcast = null, islands = [], groups = [], modes = [], languages = [], t = {} }) {
    const { flash = {}, errors = {} } = usePage().props;
    const editing = broadcast !== null;
    const snapshot = broadcast?.snapshot ?? null;
    const form = useForm({
        mode: broadcast?.mode || 'daily',
        island_id: broadcast?.island_id ?? (islands[0]?.id ?? ''),
        date_from: broadcast?.date_from || '',
        date_to: broadcast?.date_to || '',
        language: broadcast?.language || 'en',
        recipient_group_id: broadcast?.recipient_group_id ?? '',
        recipient_refs: broadcast?.recipient_refs || '',
    });
    const firstError = Object.values(errors)[0];
    const submit = (e) => {
        e.preventDefault();
        if (editing) form.put(`/admin/prayer-times/broadcasts/${broadcast.id}`, { preserveScroll: true });
        else form.post('/admin/prayer-times/broadcasts', { preserveScroll: true });
    };
    const set = (name) => (e) => form.setData(name, e.target.value);
    const label = (name, text) => <label className="mb-1 block text-sm font-medium text-gray-700" htmlFor={`broadcast-${name}`}>{text}</label>;
    const canConfirm = snapshot !== null && snapshot.included_count > 0 && !snapshot.needs_split;

    return (
        <AppShell title={editing ? (t.prayer_broadcast_title || 'Broadcast :id').replace(':id', broadcast.id) : (t.prayer_broadcast_new_title || 'New broadcast')}>
            <p className="mb-4 text-sm"><Link href="/admin/prayer-times/broadcasts" className="text-gray-500 underline" data-testid="broadcast-back">{t.prayer_back_broadcasts || '← Prayer broadcasts'}</Link></p>
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700" data-testid="prayer-flash">✓ {flash.success}</p>}
            {firstError && <p className="mb-4 rounded bg-red-50 p-3 text-sm text-red-700" data-testid="prayer-error">✗ {firstError}</p>}
            {editing && <p className="mb-4 text-sm text-gray-600">{t.prayer_col_status || 'Status'}: <span className="font-semibold" data-testid="broadcast-status">{t[`prayer_status_${broadcast.status}`] || broadcast.status}</span></p>}

            <form onSubmit={submit} action={editing ? `/admin/prayer-times/broadcasts/${broadcast.id}` : '/admin/prayer-times/broadcasts'} method="post" className="max-w-3xl space-y-4 rounded-lg border bg-white p-6" data-testid="broadcast-form">
                <div>
                    {label('mode', t.prayer_mode || 'Mode')}
                    <select id="broadcast-mode" name="mode" className="form-input w-full" value={form.data.mode} onChange={set('mode')}>
                        {modes.map((value) => <option key={value} value={value}>{t[`prayer_mode_${value}`] || value}</option>)}
                    </select>
                </div>
                <div>
                    {label('island_id', t.prayer_island || 'Island')}
                    <select id="broadcast-island_id" name="island_id" required className="form-input w-full" value={form.data.island_id} onChange={set('island_id')}>
                        {islands.map((island) => <option key={island.id} value={island.id}>{island.name}</option>)}
                    </select>
                    {errors.island_id && <p className="mt-1 text-xs text-red-700">{errors.island_id}</p>}
                </div>
                <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>{label('date_from', t.prayer_date_from || 'Date from')}<input id="broadcast-date_from" type="date" name="date_from" className="form-input w-full" dir="ltr" value={form.data.date_from} onChange={set('date_from')} /></div>
                    <div>{label('date_to', t.prayer_date_to || 'Date to')}<input id="broadcast-date_to" type="date" name="date_to" className="form-input w-full" dir="ltr" value={form.data.date_to} onChange={set('date_to')} /></div>
                </div>
                <div>
                    {label('language', t.prayer_language || 'Language')}
                    <select id="broadcast-language" name="language" className="form-input w-full" value={form.data.language} onChange={set('language')}>
                        {languages.map((value) => <option key={value} value={value}>{t[`courses_lang_${value}`] || value}</option>)}
                    </select>
                </div>
                <div>
                    {label('recipient_group_id', t.prayer_group || 'Recipient group')}
                    <select id="broadcast-recipient_group_id" name="recipient_group_id" className="form-input w-full" value={form.data.recipient_group_id} onChange={set('recipient_group_id')}>
                        <option value="">{t.prayer_adhoc_only || 'Ad-hoc only'}</option>
                        {groups.map((group) => <option key={group.id} value={group.id}>{group.name}</option>)}
                    </select>
                </div>
                <div>
                    {label('recipient_refs', t.prayer_recipient_refs || 'Ad-hoc user IDs / JSON refs')}
                    <textarea id="broadcast-recipient_refs" name="recipient_refs" rows="3" className="form-input w-full font-mono text-xs" dir="ltr" value={form.data.recipient_refs} onChange={set('recipient_refs')} />
                </div>
                <div className="flex items-center gap-3">
                    <button type="submit" className="btn-primary" disabled={form.processing} data-testid="broadcast-save">{t.prayer_save_draft || 'Save draft'}</button>
                    <Link href="/admin/prayer-times/broadcasts" className="text-sm text-gray-500 underline">{t.prayer_back || 'Back'}</Link>
                </div>
            </form>

            {editing && (
                <div className="mt-6 max-w-3xl space-y-4">
                    <form action={`/admin/prayer-times/broadcasts/${broadcast.id}/preview`} method="post" onSubmit={(e) => { e.preventDefault(); router.post(`/admin/prayer-times/broadcasts/${broadcast.id}/preview`, {}, { preserveScroll: true }); }}>
                        <button type="submit" className="btn-secondary" data-testid="broadcast-preview">{t.prayer_preview || 'Preview'}</button>
                    </form>
                    {snapshot && (
                        <div className="space-y-2 rounded-lg border bg-white p-4 text-sm" data-testid="broadcast-snapshot">
                            <p>
                                <strong>{t.prayer_included || 'Included'}:</strong> <span data-testid="snapshot-included">{snapshot.included_count}</span>
                                {' · '}<strong>{t.prayer_excluded || 'Excluded'}:</strong> {snapshot.excluded_count}
                                {' · '}<strong>{t.prayer_cost || 'Cost'}:</strong> <span dir="ltr">{snapshot.estimated_cost} MVR</span>
                            </p>
                            {snapshot.needs_split && (
                                <>
                                    <p className="text-red-700" data-testid="snapshot-split">{t.prayer_needs_split || 'Times change in this range. Split into the suggested blocks before confirm.'}</p>
                                    <pre className="overflow-x-auto bg-gray-50 p-2 text-xs" dir="ltr">{snapshot.blocks}</pre>
                                </>
                            )}
                            {['en', 'dv', 'ar'].map((locale) => (
                                <div key={locale}>
                                    <p className="font-medium">{t[`courses_lang_${locale}`] || locale.toUpperCase()}</p>
                                    <pre className="whitespace-pre-wrap" dir={locale === 'en' ? 'ltr' : 'rtl'}>{snapshot.messages[locale]}</pre>
                                </div>
                            ))}
                            <form action={`/admin/prayer-times/broadcasts/${broadcast.id}/confirm`} method="post" onSubmit={(e) => { e.preventDefault(); router.post(`/admin/prayer-times/broadcasts/${broadcast.id}/confirm`, {}, { preserveScroll: true }); }}>
                                <button type="submit" className="btn-primary" disabled={!canConfirm} data-testid="broadcast-confirm">{t.prayer_confirm || 'Confirm & queue'}</button>
                            </form>
                        </div>
                    )}
                </div>
            )}
        </AppShell>
    );
}
