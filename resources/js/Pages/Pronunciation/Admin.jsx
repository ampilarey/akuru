import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../Components/FormErrors';

export default function Admin({ pending_samples: pendingSamples, model_versions: modelVersions, stats, ai_enabled: aiEnabled, t = {} }) {
    const [reasons, setReasons] = useState({});
    const versionForm = useForm({
        version_name: '',
        model_path: '',
        training_sample_count: '',
        validation_letter_accuracy: '',
        validation_haraka_accuracy: '',
        notes: '',
    });
    // A decision or an activation posts with `router`, so its refusal comes
    // back as the page's errors with no form to own it; the row it was made
    // on says it (slice CT6b-2b).
    const refusals = useRowRefusals(versionForm);

    const decide = (id, approve) => refusals.actOn(`sample:${id}`, () => router.post(
        `/admin/pronunciation/samples/${id}/decide`,
        { approve, reason: reasons[id] || undefined },
        { preserveScroll: true },
    ));
    const totals = Object.entries(stats.totals)
        .map(([status, total]) => `${t[`pron_sample_status_${status}`] || status.replaceAll('_', ' ')} ${total}`)
        .join(' · ');

    return (
        <AppShell title={t.pron_admin_title || 'Pronunciation AI admin'}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2 text-sm">
                <span className={aiEnabled ? 'text-green-700' : 'text-amber-700'} data-testid="pron-ai-state">
                    {aiEnabled ? t.pron_ai_on || 'AI checking is on' : t.pron_ai_off || 'AI checking is off'}
                    {!aiEnabled && <> ({t.pron_ai_flag || 'turned on by the flag'} <code>{'AI_PRONUNCIATION_ENABLED'}</code>)</>}
                    {' · '}{t.pron_totals || 'Samples so far'}: {totals || t.pron_no_samples || 'no samples yet'}
                </span>
                <button type="button" className="btn-secondary" onClick={() => router.post('/admin/pronunciation/export', {}, { preserveScroll: true })}>
                    {t.pron_export || 'Export approved samples'}
                </button>
            </div>
            <FormErrors errors={refusals.unplaced} className="mb-4" />

            {pendingSamples.length > 0 && (
                <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                    <table className="min-w-full text-sm">
                        <thead className="bg-[#F3EBE0] text-start">
                            <tr>
                                <th className="px-3 py-2">{t.pron_pending_sample || 'Pending sample'}</th>
                                <th className="px-3 py-2">{t.pron_decision || 'Decision'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {pendingSamples.map((sample) => (
                                <tr key={sample.id} className="border-t" data-testid={`pron-sample-${sample.id}`}>
                                    <td className="px-3 py-2">
                                        {sample.letter} + {sample.haraka} · {sample.created_at}
                                        {sample.notes && <p className="text-xs text-gray-500">{sample.notes}</p>}
                                    </td>
                                    <td className="px-3 py-2">
                                        <input
                                            className="form-input mb-1 w-48"
                                            placeholder={t.pron_rejection_reason || 'Rejection reason'} aria-label={t.pron_rejection_reason || 'Rejection reason'}
                                            value={reasons[sample.id] || ''}
                                            onChange={(e) => setReasons({ ...reasons, [sample.id]: e.target.value })}
                                        />
                                        <span className="flex gap-2">
                                            <button type="button" className="btn-primary" onClick={() => decide(sample.id, true)}>{t.pron_approve || 'Approve'}</button>
                                            <button type="button" className="text-sm text-red-600" onClick={() => decide(sample.id, false)}>{t.pron_reject || 'Reject'}</button>
                                        </span>
                                        <FormErrors errors={refusals.errorsFor(`sample:${sample.id}`)} className="mt-1" />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    versionForm.post('/admin/pronunciation/versions', { preserveScroll: true, onSuccess: () => versionForm.reset() });
                }}
                className="mb-6 grid gap-2 rounded-lg border bg-white p-4 md:grid-cols-7"
            >
                <input className="form-input" placeholder={t.pron_version_name || 'Version name (v2)'} aria-label={t.pron_version_name || 'Version name (v2)'} value={versionForm.data.version_name} onChange={(e) => versionForm.setData('version_name', e.target.value)} />
                <input className="form-input md:col-span-2" placeholder={t.pron_model_path || 'Model path (.h5)'} aria-label={t.pron_model_path || 'Model path (.h5)'} value={versionForm.data.model_path} onChange={(e) => versionForm.setData('model_path', e.target.value)} />
                <input className="form-input" placeholder={t.pron_samples || 'Samples'} aria-label={t.pron_samples || 'Samples'} value={versionForm.data.training_sample_count} onChange={(e) => versionForm.setData('training_sample_count', e.target.value)} />
                <input className="form-input" placeholder={t.pron_letter_accuracy || 'Letter accuracy (0–1)'} aria-label={t.pron_letter_accuracy || 'Letter accuracy (0–1)'} value={versionForm.data.validation_letter_accuracy} onChange={(e) => versionForm.setData('validation_letter_accuracy', e.target.value)} />
                {/* The table shows a haraka accuracy beside the letter's; nothing could enter one (STATUS §5ps). */}
                <input className="form-input" placeholder={t.pron_haraka_accuracy || 'Haraka accuracy (0–1)'} aria-label={t.pron_haraka_accuracy || 'Haraka accuracy (0–1)'} value={versionForm.data.validation_haraka_accuracy} onChange={(e) => versionForm.setData('validation_haraka_accuracy', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={versionForm.processing}>{t.pron_register_version || 'Register version'}</button>
                <FormErrors errors={versionForm.errors} />
            </form>

            <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.pron_model_version || 'Model version'}</th>
                            <th className="px-3 py-2">{t.pron_samples || 'Samples'}</th>
                            <th className="px-3 py-2">{t.pron_accuracy || 'Accuracy (letter / haraka)'}</th>
                            <th className="px-3 py-2">{t.pron_active || 'Active'}</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {modelVersions.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>{t.pron_no_versions || 'No model versions registered.'}</td></tr>
                        )}
                        {modelVersions.map((version) => (
                            <tr key={version.id} className="border-t">
                                <td className="px-3 py-2">{version.version_name} <span className="text-xs text-gray-500">({t[`pron_model_type_${version.model_type}`] || version.model_type})</span></td>
                                <td className="px-3 py-2">{version.training_sample_count}</td>
                                <td className="px-3 py-2">{version.letter_accuracy ?? '—'} / {version.haraka_accuracy ?? '—'}</td>
                                <td className="px-3 py-2">{version.is_active ? t.pron_is_active || '✓ active' : ''}</td>
                                <td className="px-3 py-2 text-end">
                                    {!version.is_active && (
                                        <button type="button" className="btn-secondary" onClick={() => refusals.actOn(`version:${version.id}`, () => router.post(`/admin/pronunciation/versions/${version.id}/activate`, {}, { preserveScroll: true }))}>
                                            {t.pron_activate || 'Activate'}
                                        </button>
                                    )}
                                    <FormErrors errors={refusals.errorsFor(`version:${version.id}`)} className="mt-1" />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {stats.cells.length > 0 && (
                <div className="overflow-x-auto rounded-lg border bg-white">
                    <table className="min-w-full text-sm">
                        <thead className="bg-[#F3EBE0] text-start">
                            <tr>
                                <th className="px-3 py-2">{t.pron_sound || 'Sound (letter + haraka)'}</th>
                                <th className="px-3 py-2">{t.pron_approved_samples || 'Approved samples'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {stats.cells.map((cell) => (
                                <tr key={`${cell.letter}-${cell.haraka}`} className="border-t">
                                    <td className="px-3 py-2">{cell.letter} + {cell.haraka}</td>
                                    <td className="px-3 py-2">{cell.samples}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </AppShell>
    );
}
