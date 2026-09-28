import { Link, useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * New or edit a Hifz programme (the Hifz port, slice 1, STATUS §5jv): the
 * name and description, the class, the supervisor and the default teacher,
 * and — once it exists — its status. Keyed on the programme so new and
 * edit never share state.
 */
export default function ProgramForm(props) {
    return <ProgramFormBody key={props.program?.id ?? 'new'} {...props} />;
}

function ProgramFormBody({ program = null, classes = [], supervisors = [], teachers = [], t = {} }) {
    const editing = program !== null;
    const form = useForm({
        name: program?.name || '',
        description: program?.description || '',
        status: program?.status || 'active',
        class_id: program?.class_id ? String(program.class_id) : '',
        supervisor_id: program?.supervisor_id ? String(program.supervisor_id) : '',
        default_teacher_id: program?.default_teacher_id ? String(program.default_teacher_id) : '',
    });
    const submit = (e) => {
        e.preventDefault();
        // The pickers post nothing when nothing is picked; the request's rules
        // say `nullable|exists`, and an empty string is not a row.
        const clean = (data) => Object.fromEntries(Object.entries(data).map(([key, value]) => [key, value === '' ? null : value]));
        // Two statements: `transform()` returns undefined in @inertiajs/react v3
        // (InertiaFormTransformTest).
        form.transform(clean);
        if (editing) form.put(`/hifz/programs/${program.id}`, { preserveScroll: true });
        else form.post('/hifz/programs', { preserveScroll: true });
    };
    const error = (name) => form.errors[name] && <p className="mt-1 text-xs text-red-700">{form.errors[name]}</p>;
    const picker = (name, label, options, none) => (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700" htmlFor={`program-${name}`}>{label}</label>
            <select id={`program-${name}`} name={name} className="form-input w-full" value={form.data[name]} onChange={(e) => form.setData(name, e.target.value)}>
                <option value="">{none}</option>
                {options.map((option) => <option key={option.id} value={String(option.id)}>{option.name}</option>)}
            </select>
            {error(name)}
        </div>
    );

    return (
        <AppShell title={editing ? `${t.hifz_program_edit_title || 'Edit'} ${program.name}` : (t.hifz_program_new_title || 'Create Hifz Program')}>
            <p className="mb-4 text-sm"><Link href={editing ? `/hifz/programs/${program.id}` : '/hifz/programs'} className="text-gray-500 underline" data-testid="program-back">{editing ? (t.hifz_back_program || '← Program') : (t.hifz_back_programs || '← Hifz Programs')}</Link></p>

            <form onSubmit={submit} action={editing ? `/hifz/programs/${program.id}` : '/hifz/programs'} method="post" className="max-w-2xl space-y-4 rounded-lg border bg-white p-6" data-testid="program-form">
                <div>
                    <label className="mb-1 block text-sm font-medium text-gray-700" htmlFor="program-name">{t.hifz_name || 'Name'}</label>
                    <input id="program-name" name="name" className="form-input w-full" required value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    {error('name')}
                </div>
                <div>
                    <label className="mb-1 block text-sm font-medium text-gray-700" htmlFor="program-description">{t.hifz_description || 'Description'}</label>
                    <textarea id="program-description" name="description" rows="3" className="form-input w-full" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                    {error('description')}
                </div>
                {editing && (
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700" htmlFor="program-status">{t.hifz_status || 'Status'}</label>
                        <select id="program-status" name="status" className="form-input w-full" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)} data-testid="program-status">
                            {['active', 'inactive', 'completed'].map((value) => <option key={value} value={value}>{t[`hifz_status_${value}`] || value}</option>)}
                        </select>
                        {error('status')}
                    </div>
                )}
                {picker('class_id', t.hifz_class || 'Class', classes, t.hifz_none_option || '—')}
                {picker('supervisor_id', t.hifz_supervisor || 'Supervisor', supervisors, t.hifz_none_option || '—')}
                {picker('default_teacher_id', t.hifz_default_teacher || 'Default Teacher', teachers, t.hifz_none_option || '—')}
                <div className="flex items-center gap-3">
                    <button type="submit" className="btn-primary" disabled={form.processing} data-testid="program-save">{editing ? (t.hifz_save || 'Save') : (t.hifz_create || 'Create')}</button>
                </div>
            </form>
        </AppShell>
    );
}
