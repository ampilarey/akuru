import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';

function VersionForm() {
    const form = useForm({
        version_name: '',
        model_path: '',
        training_sample_count: '',
        validation_letter_accuracy: '',
        validation_haraka_accuracy: '',
        notes: '',
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post('/admin/pronunciation/versions', { preserveScroll: true, onSuccess: () => form.reset() });
            }}
            className="mb-6 grid gap-2 rounded-lg border bg-white p-4 md:grid-cols-6"
        >
            <input className="form-input" placeholder="Version name (v2)" value={form.data.version_name} onChange={(e) => form.setData('version_name', e.target.value)} />
            <input className="form-input md:col-span-2" placeholder="Model path (.h5)" value={form.data.model_path} onChange={(e) => form.setData('model_path', e.target.value)} />
            <input className="form-input" placeholder="Samples" value={form.data.training_sample_count} onChange={(e) => form.setData('training_sample_count', e.target.value)} />
            <input className="form-input" placeholder="Letter acc (0–1)" value={form.data.validation_letter_accuracy} onChange={(e) => form.setData('validation_letter_accuracy', e.target.value)} />
            <button type="submit" className="btn-primary" disabled={form.processing}>Register version</button>
        </form>
    );
}

export default function Admin({ pending_samples: pendingSamples, model_versions: modelVersions, stats, ai_enabled: aiEnabled }) {
    const flash = usePage().props.flash || {};
    const [reasons, setReasons] = useState({});

    const decide = (id, approve) =>
        router.post(`/admin/pronunciation/samples/${id}/decide`, { approve, reason: reasons[id] || undefined }, { preserveScroll: true });

    return (
        <AppShell title="Pronunciation AI admin">
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700">{flash.success}</p>}
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2 text-sm">
                <span className={aiEnabled ? 'text-green-700' : 'text-amber-700'}>
                    AI checking is {aiEnabled ? 'ON' : 'OFF (flag AI_PRONUNCIATION_ENABLED)'} · totals:{' '}
                    {Object.entries(stats.totals).map(([status, total]) => `${status.replaceAll('_', ' ')} ${total}`).join(' · ') || 'no samples yet'}
                </span>
                <button type="button" className="btn-secondary" onClick={() => router.post('/admin/pronunciation/export', {}, { preserveScroll: true })}>
                    Export approved samples
                </button>
            </div>

            {pendingSamples.length > 0 && (
                <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                    <table className="min-w-full text-sm">
                        <thead className="bg-[#F3EBE0] text-start">
                            <tr>
                                <th className="px-3 py-2">Pending sample</th>
                                <th className="px-3 py-2">Decision</th>
                            </tr>
                        </thead>
                        <tbody>
                            {pendingSamples.map((sample) => (
                                <tr key={sample.id} className="border-t">
                                    <td className="px-3 py-2">
                                        {sample.letter} + {sample.haraka} · {sample.created_at}
                                        {sample.notes && <p className="text-xs text-gray-500">{sample.notes}</p>}
                                    </td>
                                    <td className="px-3 py-2">
                                        <input
                                            className="form-input mb-1 w-48"
                                            placeholder="Rejection reason"
                                            value={reasons[sample.id] || ''}
                                            onChange={(e) => setReasons({ ...reasons, [sample.id]: e.target.value })}
                                        />
                                        <span className="flex gap-2">
                                            <button type="button" className="btn-primary" onClick={() => decide(sample.id, true)}>Approve</button>
                                            <button type="button" className="text-sm text-red-600" onClick={() => decide(sample.id, false)}>Reject</button>
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <VersionForm />

            <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Model version</th>
                            <th className="px-3 py-2">Samples</th>
                            <th className="px-3 py-2">Accuracy (L/H)</th>
                            <th className="px-3 py-2">Active</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {modelVersions.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>No model versions registered.</td></tr>
                        )}
                        {modelVersions.map((version) => (
                            <tr key={version.id} className="border-t">
                                <td className="px-3 py-2">{version.version_name} <span className="text-xs text-gray-500">({version.model_type})</span></td>
                                <td className="px-3 py-2">{version.training_sample_count}</td>
                                <td className="px-3 py-2">{version.letter_accuracy ?? '—'} / {version.haraka_accuracy ?? '—'}</td>
                                <td className="px-3 py-2">{version.is_active ? '✓ active' : ''}</td>
                                <td className="px-3 py-2 text-end">
                                    {!version.is_active && (
                                        <button type="button" className="btn-secondary" onClick={() => router.post(`/admin/pronunciation/versions/${version.id}/activate`, { rollback: 1 }, { preserveScroll: true })}>
                                            Activate
                                        </button>
                                    )}
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
                                <th className="px-3 py-2">Dataset cell</th>
                                <th className="px-3 py-2">Approved samples</th>
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
