import { router, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import AppShell from '../../Layouts/AppShell';

export default function Practice({ letters, harakas, attempts, ai_enabled: aiEnabled }) {
    const { flash = {}, i18n } = usePage().props;
    const t = i18n?.learn || {};
    const [letterId, setLetterId] = useState(letters[0]?.id || '');
    const [harakaId, setHarakaId] = useState(harakas[0]?.id || '');
    const [recording, setRecording] = useState(false);
    const [blob, setBlob] = useState(null);
    const recorderRef = useRef(null);
    const chunksRef = useRef([]);

    const startRecording = async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            const recorder = new MediaRecorder(stream);
            chunksRef.current = [];
            recorder.ondataavailable = (e) => chunksRef.current.push(e.data);
            recorder.onstop = () => {
                setBlob(new Blob(chunksRef.current, { type: recorder.mimeType || 'audio/webm' }));
                stream.getTracks().forEach((track) => track.stop());
            };
            recorder.start();
            recorderRef.current = recorder;
            setRecording(true);
        } catch {
            setRecording(false);
        }
    };

    const stopRecording = () => {
        recorderRef.current?.stop();
        setRecording(false);
    };

    const submit = () => {
        if (!blob) return;
        const form = new FormData();
        form.append('expected_letter_id', letterId);
        form.append('expected_haraka_id', harakaId);
        form.append('audio', new File([blob], 'attempt.webm', { type: blob.type }));
        router.post('/learn/pronounce', form, { preserveScroll: true, onSuccess: () => setBlob(null) });
    };

    const letter = letters.find((row) => String(row.id) === String(letterId));
    const haraka = harakas.find((row) => String(row.id) === String(harakaId));

    return (
        <AppShell title={t.pronounce_title || 'Pronunciation practice'}>
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700">{flash.success}</p>}

            <div className="mb-6 rounded-lg border bg-white p-6 text-center">
                <div className="mb-4 flex justify-center gap-3">
                    <select className="form-input" value={letterId} onChange={(e) => setLetterId(e.target.value)}>
                        {letters.map((row) => <option key={row.id} value={row.id}>{row.char} · {row.key_name}</option>)}
                    </select>
                    <select className="form-input" value={harakaId} onChange={(e) => setHarakaId(e.target.value)}>
                        {harakas.map((row) => <option key={row.id} value={row.id}>{row.symbol} · {row.key_name}</option>)}
                    </select>
                </div>
                <p className="mb-4 text-6xl" dir="rtl">{letter?.char}{haraka?.symbol}</p>
                <div className="flex justify-center gap-3">
                    {!recording && <button type="button" className="btn-primary" onClick={startRecording}>{t.pronounce_record || 'Record'}</button>}
                    {recording && <button type="button" className="bg-red-600 text-white rounded px-4 py-2" onClick={stopRecording}>{t.pronounce_stop || 'Stop'}</button>}
                    {blob && !recording && <button type="button" className="btn-secondary" onClick={submit}>{t.pronounce_submit || 'Submit recording'}</button>}
                </div>
                <p className="mt-3 text-xs text-gray-500">
                    {aiEnabled
                        ? t.pronounce_hint_ai || 'The pronunciation checker gives instant feedback; your teacher still reviews.'
                        : t.pronounce_hint_teacher || 'Your teacher will listen and respond.'}
                </p>
            </div>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.pronounce_attempt || 'Attempt'}</th>
                            <th className="px-3 py-2">{t.pronounce_status || 'Status'}</th>
                            <th className="px-3 py-2">{t.pronounce_review || 'Teacher review'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {attempts.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={3}>{t.pronounce_empty || 'No attempts yet — record your first sound.'}</td></tr>
                        )}
                        {attempts.map((attempt) => (
                            <tr key={attempt.id} className="border-t">
                                <td className="px-3 py-2">{attempt.at}</td>
                                <td className="px-3 py-2">{attempt.status?.replaceAll('_', ' ')}</td>
                                <td className="px-3 py-2">{attempt.teacher_review_required ? t.pronounce_waiting || 'waiting' : t.pronounce_done || 'done'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
