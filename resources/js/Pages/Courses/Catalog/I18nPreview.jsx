import AppShell from '../../../Layouts/AppShell';

export default function I18nPreview({ samples }) {
    return (
        <AppShell title="i18n preview">
            <p className="mb-4 text-sm text-gray-600">Hidden admin check for EN / DV / AR and LTR / RTL.</p>
            <div className="mb-6 grid gap-3 md:grid-cols-2">
                <input className="form-input" defaultValue="Sample field" />
                <button type="button" className="btn-primary">Sample button</button>
            </div>
            <div className="space-y-4">
                {samples.map((sample) => (
                    <article key={sample.locale} className="rounded-lg border bg-white p-4" dir={sample.dir} lang={sample.locale}>
                        <p className="mb-1 text-xs uppercase tracking-wide text-gray-500">{sample.locale} · {sample.dir}</p>
                        <h2 className="mb-2 text-lg font-semibold">{sample.heading}</h2>
                        <p className="text-sm font-normal">{sample.body}</p>
                        <p className="mt-2 text-base font-medium">{sample.body}</p>
                        <aside className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm">Instruction sample: {sample.body}</aside>
                    </article>
                ))}
            </div>
        </AppShell>
    );
}
