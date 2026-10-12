import { Link, router, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';
import TagCanvas from '../../Components/Print/TagCanvas';
import { normaliseDesign } from '../../Components/Print/design';

/**
 * NAME_TAGS_PLAN §4.7 (NT2): the office's templates — each drawn small
 * from its design with the sample fields, its kind, layout, fields, state
 * and uses; *New* opens the designer; a state moves draft → active →
 * retired, never deleted.
 */
export default function PrintTemplates({ t = {}, templates = [], fonts = {}, font_css: fontCss = '', images = [], sample = {} }) {
    const { errors } = usePage().props;
    const imageUrls = useMemo(() => Object.fromEntries(images.map((i) => [i.media, i.url])), [images]);
    const setStatus = (id, status) => router.post(`/admin/bookshop/print/templates/${id}/status`, { status }, { preserveScroll: true });

    return (
        <AppShell title={t.templates_title}>
            <style>{fontCss}</style>
            <FormErrors errors={errors} className="mb-4" />
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm text-gray-600">{t.templates_intro}</p>
                <div className="flex flex-wrap items-center gap-2">
                    <Link href="/admin/bookshop/print" className="text-sm underline">{t.back_to_desk}</Link>
                    <a href="/admin/bookshop/print/templates/export" className="btn-secondary text-sm" data-testid="templates-export">{t.export_csv}</a>
                    <Link href="/admin/bookshop/print/templates/new" className="btn-primary text-sm" data-testid="templates-new">{t.new_template}</Link>
                </div>
            </div>
            {templates.length === 0 ? <p className="text-sm text-gray-600">{t.templates_none}</p> : (
                <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" data-testid="templates-list">
                    {templates.map((tpl) => {
                        const design = normaliseDesign(tpl.design);
                        return (
                            <li key={tpl.id} className="rounded-lg border bg-white p-3" data-testid={`template-${tpl.id}`} data-status={tpl.status}>
                                <div className="mb-2 flex justify-center overflow-hidden rounded bg-gray-50 p-2" dir="ltr">
                                    <TagCanvas design={design} fonts={fonts} images={imageUrls} row={sample} scale={Math.min(2.2, 200 / design.tag.w)} testId={`template-thumb-${tpl.id}`} />
                                </div>
                                <h2 className="font-semibold" dir="auto">{tpl.title}{tpl.title_dv && <span className="ms-2 text-sm font-normal text-gray-600" dir="rtl">{tpl.title_dv}</span>}</h2>
                                <p className="text-xs text-gray-600">
                                    {t[`kind_${tpl.kind}`]} · {tpl.layout || t.layout_none} · <span data-testid={`template-status-${tpl.id}`}>{t[`template_status_${tpl.status}`]}</span> · {t.uses}: {tpl.uses_count}
                                </p>
                                <p className="text-xs text-gray-600">{t.fields}: {tpl.fields.length ? tpl.fields.map((f) => `{${f}}`).join(' ') : '—'}</p>
                                <div className="mt-2 flex flex-wrap items-center gap-3 text-sm">
                                    <Link href={`/admin/bookshop/print/templates/${tpl.id}`} className="text-blue-700 underline" data-testid={`template-edit-${tpl.id}`}>{t.edit}</Link>
                                    <a href={`/admin/bookshop/print/templates/${tpl.id}/sheet`} target="_blank" rel="noreferrer" className="underline">{t.sample_sheet}</a>
                                    {tpl.status !== 'active' && <button type="button" className="underline" onClick={() => setStatus(tpl.id, 'active')} data-testid={`template-activate-${tpl.id}`}>{t.make_active}</button>}
                                    {tpl.status === 'active' && <button type="button" className="underline" onClick={() => setStatus(tpl.id, 'draft')}>{t.make_draft}</button>}
                                    {tpl.status !== 'retired' && <button type="button" className="text-gray-600 underline" onClick={() => setStatus(tpl.id, 'retired')} data-testid={`template-retire-${tpl.id}`}>{t.retire}</button>}
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}
        </AppShell>
    );
}
