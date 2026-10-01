import { Link, useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * New or edit a prayer recipient group (C9 slice 12, STATUS §5jn): the
 * name in three languages, a description, the member refs (user ids or
 * JSON) and the active switch. Keyed on the group so new and edit never
 * share state (STATUS §5jj): a save of a new group lands on its edit page.
 */
export default function GroupForm(props) {
    return <GroupFormBody key={props.group?.id ?? 'new'} {...props} />;
}

function GroupFormBody({ group = null, t = {} }) {
        const editing = group !== null;
    const form = useForm({
        name_en: group?.name_en || '',
        name_dv: group?.name_dv || '',
        name_ar: group?.name_ar || '',
        description: group?.description || '',
        member_refs: group?.member_refs || '',
        is_active: editing ? !!group.is_active : true,
    });
    const submit = (e) => {
        e.preventDefault();
        if (editing) form.put(`/admin/prayer-times/groups/${group.id}`, { preserveScroll: true });
        else form.post('/admin/prayer-times/groups', { preserveScroll: true });
    };
    const field = (name, label, props = {}) => (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700" htmlFor={`group-${name}`}>{label}</label>
            <input id={`group-${name}`} name={name} className="form-input w-full" value={form.data[name]} onChange={(e) => form.setData(name, e.target.value)} {...props} />
            {form.errors[name] && <p className="mt-1 text-xs text-red-700">{form.errors[name]}</p>}
        </div>
    );

    return (
        <AppShell title={editing ? (t.prayer_group_edit_title || 'Edit group') : (t.prayer_group_new_title || 'New group')}>
            <p className="mb-4 text-sm"><Link href="/admin/prayer-times/groups" className="text-gray-500 underline" data-testid="group-back">{t.prayer_back_groups || '← Recipient groups'}</Link></p>
            <form onSubmit={submit} action={editing ? `/admin/prayer-times/groups/${group.id}` : '/admin/prayer-times/groups'} method="post" className="max-w-2xl space-y-4 rounded-lg border bg-white p-6" data-testid="group-form">
                {field('name_en', t.prayer_name_en || 'Name (EN)', { required: true, type: 'text' })}
                {field('name_dv', t.prayer_name_dv || 'Name (DV)', { type: 'text', dir: 'rtl' })}
                {field('name_ar', t.prayer_name_ar || 'Name (AR)', { type: 'text', dir: 'rtl' })}
                <div>
                    <label className="mb-1 block text-sm font-medium text-gray-700" htmlFor="group-description">{t.prayer_description || 'Description'}</label>
                    <textarea id="group-description" name="description" className="form-input w-full" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                </div>
                <div>
                    <label className="mb-1 block text-sm font-medium text-gray-700" htmlFor="group-member_refs">{t.prayer_member_refs || 'Member user IDs (comma or JSON refs)'}</label>
                    <textarea id="group-member_refs" name="member_refs" rows="4" className="form-input w-full font-mono text-xs" dir="ltr" value={form.data.member_refs} onChange={(e) => form.setData('member_refs', e.target.value)} />
                </div>
                <label className="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="is_active" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} className="rounded border-gray-300" data-testid="group-active" />
                    {t.prayer_active || 'Active'}
                </label>
                <div className="flex items-center gap-3">
                    <button type="submit" className="btn-primary" disabled={form.processing} data-testid="group-save">{t.prayer_save || 'Save'}</button>
                    <Link href="/admin/prayer-times/groups" className="text-sm text-gray-500 underline">{t.prayer_back || 'Back'}</Link>
                </div>
            </form>
        </AppShell>
    );
}
