import { Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';
import TagCanvas from '../../Components/Print/TagCanvas';
import { normaliseDesign } from '../../Components/Print/design';

/**
 * NAME_TAGS_PLAN §3.1–3.2 (NT3): the door to name tags — Akuru's designs,
 * each drawn from its own design with sample names, filtered by kind. A tap
 * starts a job from the design; a guest may design, and signs in for the
 * proof.
 */
export default function PrintDoor({ t = {}, templates = [], fonts = {}, font_css: fontCss = '', sample = {}, enabled = true, signed_in: signedIn = false }) {
    const { errors = {}, locale = 'en' } = usePage().props;
    const [kind, setKind] = useState('all');
    const [busy, setBusy] = useState(null);
    const shown = useMemo(() => templates.filter((tpl) => kind === 'all' || tpl.kind === kind || tpl.kind === 'both'), [templates, kind]);
    const start = (id) => { setBusy(id); router.post(`/${locale}/shop/print/jobs`, { template: id }, { onFinish: () => setBusy(null) }); };

    return (
        <AppShell title={t.door_title}>
            <style>{fontCss}</style>
            <FormErrors errors={errors} className="mb-4" />
            {!enabled && <p className="mb-3 rounded bg-amber-50 p-2 text-sm text-amber-900" data-testid="print-door-office">{t.door_office_preview}</p>}
            <p className="mb-2 text-gray-700">{t.door_intro}</p>
            <div className="mb-4 flex flex-wrap items-center gap-2 text-sm">
                {['all', 'name_tag', 'subject_tag'].map((k) => (
                    <button key={k} type="button" onClick={() => setKind(k)} aria-pressed={kind === k} className={`rounded-full border px-3 py-1 ${kind === k ? 'border-[#7C2D37] bg-[#7C2D37] text-white' : 'bg-white'}`} data-testid={`print-door-filter-${k}`}>
                        {k === 'all' ? t.filter_all : t[`kind_${k}`]}
                    </button>
                ))}
                {signedIn && <Link href={`/${locale}/my-designs`} className="ms-auto underline" data-testid="print-door-designs">{t.my_designs_title}</Link>}
            </div>
            {shown.length === 0 ? <p className="text-sm text-gray-600">{t.templates_none}</p> : (
                <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" data-testid="print-door-gallery">
                    {shown.map((tpl) => {
                        const design = normaliseDesign(tpl.design);
                        return (
                            <li key={tpl.id} className="flex flex-col rounded-lg border bg-white p-3" data-testid={`print-door-template-${tpl.id}`}>
                                <div className="mb-2 flex justify-center overflow-hidden rounded bg-gray-50 p-2" dir="ltr">
                                    <TagCanvas design={design} fonts={fonts} row={sample} scale={Math.min(2.4, 220 / design.tag.w)} testId={`print-door-thumb-${tpl.id}`} />
                                </div>
                                <h2 className="font-semibold" dir="auto">{tpl.title}</h2>
                                <p className="mb-3 text-xs text-gray-600">{t[`kind_${tpl.kind}`]}</p>
                                <button type="button" className="btn-primary mt-auto" disabled={busy !== null} onClick={() => start(tpl.id)} data-testid={`print-door-use-${tpl.id}`}>{t.use_design}</button>
                            </li>
                        );
                    })}
                </ul>
            )}
        </AppShell>
    );
}
