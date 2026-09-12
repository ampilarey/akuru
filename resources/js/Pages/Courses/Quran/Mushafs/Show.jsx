import { Link, router, useForm } from '@inertiajs/react';
import AppShell from '../../../../Layouts/AppShell';

export default function MushafShow({ mushaf, can_manage = false, can_approve = false, can_lock = false }) {
    const importForm = useForm({
        surah_number: '',
        ayah_number: '',
        page_number: '',
        text_uthmani: '',
        words: ['', ''],
    });

    const setWord = (index, value) => {
        const next = [...importForm.data.words];
        next[index] = value;
        importForm.setData('words', next);
    };

    const submitImport = (event) => {
        event.preventDefault();
        importForm.post(`/quran/mushafs/${mushaf.id}/import-ayah`, {
            preserveScroll: true,
            onSuccess: () => importForm.reset('ayah_number', 'text_uthmani', 'words'),
        });
    };

    return (
        <AppShell title={mushaf.name}>
            <h1 className="mb-2 text-2xl font-bold">{mushaf.name}</h1>
            <p className="mb-4 text-sm text-gray-600">
                Hash: {mushaf.source_hash ?? '—'} · Pages: {mushaf.pages_count} · Ayahs: {mushaf.ayahs_count} ·
                Words: {mushaf.words_count}
                {mushaf.is_active && <span className="ms-2 rounded bg-green-100 px-2 py-0.5 text-green-800">Active</span>}
                {mushaf.locked && <span className="ms-2 rounded bg-gray-200 px-2 py-0.5">Locked</span>}
            </p>

            <div className="mb-6 flex flex-wrap gap-2">
                {can_approve && (
                    <button
                        type="button"
                        className="btn-primary"
                        onClick={() => router.post(`/quran/mushafs/${mushaf.id}/approve`, {}, { preserveScroll: true })}
                    >
                        Approve &amp; activate
                    </button>
                )}
                {can_lock && (
                    <button
                        type="button"
                        className="btn-secondary"
                        onClick={() => router.post(`/quran/mushafs/${mushaf.id}/lock`, {}, { preserveScroll: true })}
                    >
                        Lock
                    </button>
                )}
                <Link className="btn-secondary" href={`/quran/mushafs/${mushaf.id}/pages/1`}>Map page 1</Link>
            </div>

            {can_manage && (
                <form onSubmit={submitImport} className="max-w-2xl space-y-3 rounded-lg border bg-white p-4">
                    <h2 className="font-semibold">Import ayah / words</h2>
                    <div className="grid grid-cols-3 gap-2">
                        <input
                            className="form-input" type="number" min="1" max="114" placeholder="Surah" required
                            value={importForm.data.surah_number}
                            onChange={(e) => importForm.setData('surah_number', e.target.value)}
                        />
                        <input
                            className="form-input" type="number" min="1" placeholder="Ayah" required
                            value={importForm.data.ayah_number}
                            onChange={(e) => importForm.setData('ayah_number', e.target.value)}
                        />
                        <input
                            className="form-input" type="number" min="1" placeholder="Page"
                            value={importForm.data.page_number}
                            onChange={(e) => importForm.setData('page_number', e.target.value)}
                        />
                    </div>
                    <textarea
                        className="form-input w-full" rows={2} dir="rtl" required placeholder="Ayah text (Uthmani)"
                        value={importForm.data.text_uthmani}
                        onChange={(e) => importForm.setData('text_uthmani', e.target.value)}
                    />
                    {importForm.data.words.map((word, index) => (
                        <input
                            key={index}
                            className="form-input w-full" dir="rtl" placeholder={`Word ${index + 1}`}
                            value={word}
                            onChange={(e) => setWord(index, e.target.value)}
                        />
                    ))}
                    <div className="flex gap-2">
                        <button
                            type="button" className="btn-secondary"
                            onClick={() => importForm.setData('words', [...importForm.data.words, ''])}
                        >
                            Add word
                        </button>
                        <button type="submit" className="btn-primary" disabled={importForm.processing}>Import</button>
                    </div>
                    {Object.values(importForm.errors).map((message) => (
                        <p key={message} className="text-sm text-red-600">{message}</p>
                    ))}
                </form>
            )}
        </AppShell>
    );
}
