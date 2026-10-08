import AppShell from '../../../Layouts/AppShell';

export default function I18nPreview({ samples, t = {} }) {
    // Each sample's language and direction, named in the page's language.
    const language = (locale) => t[`i18n_lang_${locale}`] || locale;
    const direction = (dir) => t[`i18n_dir_${dir}`] || dir;

    return (
        <AppShell title={t.i18n_title || 'Language preview'}>
            <p className="mb-4 text-sm text-gray-600">
                {t.i18n_intro || 'A check for the office: the same screen in English, Dhivehi and Arabic, left to right and right to left.'}
            </p>
            <div className="mb-6 grid gap-3 md:grid-cols-2">
                <input className="form-input" aria-label={t.i18n_sample_field || 'Sample field'} defaultValue={t.i18n_sample_field || 'Sample field'} />
                <button type="button" className="btn-primary">{t.i18n_sample_button || 'Sample button'}</button>
            </div>
            <div className="space-y-4">
                {samples.map((sample) => (
                    <article
                        key={sample.locale}
                        className={`rounded-lg border bg-white p-4 ${sample.font === 'thaana' ? 'thaana-text' : ''} ${sample.font === 'arabic' ? 'arabic-text' : ''}`}
                        dir={sample.dir}
                        lang={sample.locale}
                    >
                        <p className="mb-1 text-xs tracking-wide text-gray-500">{language(sample.locale)} · {direction(sample.dir)}</p>
                        <h2 className="mb-2 text-lg font-semibold">{sample.heading}</h2>
                        <p className="text-sm font-normal">{sample.body}</p>
                        <p className="mt-2 text-base font-medium">{sample.body}</p>
                        <aside className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm">
                            {(t.i18n_instruction || 'Instruction sample: :text').replace(':text', sample.body)}
                        </aside>
                    </article>
                ))}
            </div>
        </AppShell>
    );
}
