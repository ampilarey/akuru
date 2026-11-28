import { useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import SettingsCrumb from '../../Components/Settings/SettingsCrumb';

/**
 * ST4 (admin audit 2026-11-27, STATUS §5uw): one product's business
 * settings — the values that used to be changed only in `.env`. One small
 * form per group, each with its own Save; every field says what it does and
 * what the server's own value is.
 */
function Group({ group, saveUrl, t }) {
    const form = useForm({ group: group.key, values: Object.fromEntries(group.knobs.map((k) => [k.name, k.type === 'bool' ? !!k.value : k.value ?? ''])) });
    const set = (name, value) => form.setData('values', { ...form.data.values, [name]: value });
    const shown = (knob, value) => (knob.type === 'bool' ? (value ? t.on : t.off) : String(value ?? ''));

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post(saveUrl, { preserveScroll: true });
            }}
            className="mb-4 rounded-lg border bg-white p-4"
            data-testid={`knobs-group-${group.key}`}
        >
            <h2 className="mb-3 text-lg font-semibold">{group.title}</h2>
            <div className="space-y-4">
                {group.knobs.map((knob) => (
                    <div key={knob.name} data-testid={`knob-${knob.name}`}>
                        {knob.type === 'bool' ? (
                            <label className="flex min-h-[2.75rem] items-center gap-3 font-medium text-gray-900">
                                <input type="checkbox" className="h-5 w-5" checked={!!form.data.values[knob.name]} onChange={(e) => set(knob.name, e.target.checked)} />
                                <span>{knob.label}</span>
                            </label>
                        ) : (
                            <label className="block">
                                <span className="mb-1 block font-medium text-gray-900">{knob.label}</span>
                                <input
                                    type={knob.type === 'text' ? 'text' : 'number'}
                                    inputMode={knob.type === 'int' ? 'numeric' : knob.type === 'decimal' ? 'decimal' : undefined}
                                    step={knob.type === 'decimal' ? '0.01' : knob.type === 'int' ? '1' : undefined}
                                    min={knob.type === 'text' ? undefined : knob.min ?? undefined}
                                    max={knob.type === 'text' ? undefined : knob.max ?? undefined}
                                    dir="ltr"
                                    className="min-h-[2.75rem] w-full rounded border px-3 sm:max-w-xs"
                                    value={form.data.values[knob.name]}
                                    onChange={(e) => set(knob.name, e.target.value)}
                                />
                            </label>
                        )}
                        <p className="mt-1 text-sm text-gray-600">{knob.hint}</p>
                        <p className="text-xs text-gray-500">
                            {t.server_value} <bdi dir="ltr">{shown(knob, knob.default)}</bdi>
                        </p>
                        {form.errors[knob.name] && (
                            <p role="alert" className="mt-1 text-sm text-red-700">
                                {form.errors[knob.name]}
                            </p>
                        )}
                    </div>
                ))}
            </div>
            <button type="submit" disabled={form.processing} className="mt-4 min-h-[2.75rem] w-full rounded bg-blue-700 px-5 font-semibold text-white disabled:opacity-60 sm:w-auto" data-testid={`knobs-save-${group.key}`}>
                {t.save}
            </button>
        </form>
    );
}

export default function Knobs({ product, title, intro, groups = [], save_url: saveUrl, t = {}, admin_t: adminT = {} }) {
    return (
        <AppShell title={title}>
            <SettingsCrumb t={adminT} />
            <p className="mb-4 text-sm text-gray-600">{intro}</p>
            <div data-phone-main data-testid={`knobs-${product}`}>
                {groups.map((group) => (
                    <Group key={group.key} group={group} saveUrl={saveUrl} t={t} />
                ))}
            </div>
        </AppShell>
    );
}
