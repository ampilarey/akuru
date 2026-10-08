import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';
import { createRecorder, describeRecordingFailure, recordingSupport } from '../../../Platform/recorder';

const range = (row) =>
    row.start_ayah_number ? `${row.start_ayah_number}–${row.end_ayah_number ?? row.start_ayah_number}` : '—';

// A code the server sends, named from the `quran` book (slice CT5a).
const named = (q, family, code) => (code ? q[`${family}${code}`] || code.replaceAll('_', ' ') : '—');

// A status, coloured by its code and named in the page's language.
const statusBadge = (q, family, status) => {
    const tone = {
        passed: 'bg-green-100 text-green-800',
        strong: 'bg-green-100 text-green-800',
        needs_repeat: 'bg-amber-100 text-amber-800',
        needs_revision: 'bg-amber-100 text-amber-800',
        failed: 'bg-red-100 text-red-800',
        weak: 'bg-red-100 text-red-800',
    }[status] || 'bg-gray-100 text-gray-700';
    return <span className={`rounded px-2 py-0.5 text-xs ${tone}`}>{named(q, family, status)}</span>;
};

/**
 * SPEC §52.9, the manual recording mode: record, replay, submit.
 *
 * The same platform recorder `/learn/pronounce` uses (§6.3 — one replaceable
 * interface, so a Capacitor plugin can take over without touching a page), and
 * the same honesty about failing: the button is disabled with a reason on it
 * when the environment cannot record, rather than doing nothing when pressed.
 */
function RecitationRecorder({ surahs, assignments, t, surahOf }) {
    const [surahId, setSurahId] = useState(String(surahs[0]?.id ?? ''));
    const [from, setFrom] = useState('1');
    const [to, setTo] = useState('1');
    const [assignmentId, setAssignmentId] = useState('');
    const [recording, setRecording] = useState(false);
    const [blob, setBlob] = useState(null);
    const [error, setError] = useState(null);
    const [sending, setSending] = useState(false);
    const recorderRef = useRef(null);
    const [playbackUrl, setPlaybackUrl] = useState(null);

    const support = useMemo(() => recordingSupport(), []);
    const surah = surahs.find((row) => String(row.id) === String(surahId));

    useEffect(() => {
        if (blob === null) {
            setPlaybackUrl(null);

            return undefined;
        }

        const url = URL.createObjectURL(blob);
        setPlaybackUrl(url);

        return () => URL.revokeObjectURL(url);
    }, [blob]);

    const start = async () => {
        setError(null);
        const recorder = createRecorder();
        try {
            await recorder.start();
            recorderRef.current = recorder;
            setRecording(true);
        } catch (failure) {
            setError(describeRecordingFailure(failure?.reason, t));
            setRecording(false);
        }
    };

    const stop = async () => {
        setRecording(false);
        try {
            setBlob(await recorderRef.current?.stop());
        } catch (failure) {
            setError(describeRecordingFailure(failure?.reason, t));
        }
        recorderRef.current = null;
    };

    const submit = () => {
        if (!blob) {
            return;
        }

        const form = new FormData();
        form.append('surah_id', surahId);
        form.append('start_ayah_number', from);
        form.append('end_ayah_number', to || from);
        if (assignmentId) {
            form.append('quran_hifz_assignment_id', assignmentId);
        }
        form.append('audio', new File([blob], 'recitation.webm', { type: blob.type || 'audio/webm' }));

        setSending(true);
        router.post('/learn/quran/recitations', form, {
            preserveScroll: true,
            onSuccess: () => setBlob(null),
            onFinish: () => setSending(false),
        });
    };

    // Answerable assignments only. A submitted or reviewed one is not something
    // to record against again, and offering it invites a refusal.
    const openAssignments = assignments.filter((row) => ['assigned', 'in_progress'].includes(row.status));

    return (
        <div className="mb-6 rounded-lg border bg-white p-4">
            <h2 className="mb-1 text-lg font-semibold">{t.quran_record || 'Record a recitation'}</h2>
            <p className="mb-3 text-sm text-gray-600">
                {t.quran_record_intro || 'Read the ayahs aloud, listen back, then send it to your teacher.'}
            </p>

            <FormErrors errors={usePage().props.errors} className="mb-3" />

            {!support.supported && (
                <p className="mb-3 rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                    {describeRecordingFailure(support.reason, t)}
                </p>
            )}
            {error && (
                <p className="mb-3 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">{error}</p>
            )}

            <div className="mb-3 grid gap-3 md:grid-cols-4">
                <label className="text-xs text-gray-700">
                    {t.quran_col_surah || 'Surah'}
                    <select className="form-input mt-1 w-full" value={surahId} onChange={(e) => setSurahId(e.target.value)}>
                        {surahs.map((row) => <option key={row.id} value={row.id}>{surahOf(row)}</option>)}
                    </select>
                </label>
                <label className="text-xs text-gray-700">
                    {t.quran_from_ayah || 'From ayah'}
                    <input className="form-input mt-1 w-full" type="number" min="1" max={surah?.ayah_count ?? undefined} value={from} onChange={(e) => setFrom(e.target.value)} />
                </label>
                <label className="text-xs text-gray-700">
                    {t.quran_to_ayah || 'To ayah'}
                    <input className="form-input mt-1 w-full" type="number" min="1" max={surah?.ayah_count ?? undefined} value={to} onChange={(e) => setTo(e.target.value)} />
                </label>
                {openAssignments.length > 0 && (
                    <label className="text-xs text-gray-700">
                        {t.quran_for_assignment || 'For assignment'}
                        <select className="form-input mt-1 w-full" value={assignmentId} onChange={(e) => setAssignmentId(e.target.value)}>
                            <option value="">{t.quran_no_assignment || 'Not for an assignment'}</option>
                            {openAssignments.map((row) => (
                                <option key={row.id} value={row.id}>
                                    {surahOf(row)} {range(row)}{row.due_date ? ` · ${(t.quran_due_on || 'due :date').replace(':date', row.due_date)}` : ''}
                                </option>
                            ))}
                        </select>
                    </label>
                )}
            </div>

            <div className="flex flex-wrap items-center gap-3">
                {!recording && !blob && (
                    <button type="button" className="btn-primary" disabled={!support.supported} onClick={start}>
                        {t.pronounce_record || 'Record'}
                    </button>
                )}
                {recording && (
                    <button type="button" className="rounded bg-red-600 px-4 py-2 text-white" onClick={stop}>
                        {t.pronounce_stop || 'Stop'}
                    </button>
                )}
                {blob && !recording && (
                    <>
                        <button type="button" className="btn-primary" onClick={start}>{t.pronounce_rerecord || 'Record again'}</button>
                        <button type="button" className="btn-secondary" disabled={sending} onClick={submit}>
                            {t.quran_submit_recitation || 'Send to my teacher'}
                        </button>
                    </>
                )}
            </div>

            {playbackUrl && !recording && (
                <div className="mt-3">
                    <p className="mb-1 text-sm text-gray-600">{t.pronounce_replay || 'Replay'}</p>
                    <audio className="w-full max-w-md" controls src={playbackUrl} preload="metadata" />
                </div>
            )}
        </div>
    );
}

export default function Quran({ student, submissions, progress, schedules, assignments = [], surahs = [], q = {} }) {
    const { i18n, locale = 'en' } = usePage().props;
    const t = i18n?.learn || {};
    // A surah by its Arabic name on a Dhivehi or Arabic page (slice CT5b).
    const surahOf = (row) => (locale === 'en' ? (row.surah ?? row.name) : (row.surah_arabic ?? row.arabic_name ?? row.surah ?? row.name)) ?? '—';

    return (
        <AppShell title={t.quran_dashboard || 'My Qur’an'}>
            {!student && (
                <p className="mb-4 text-sm text-gray-600">{t.no_profile || 'No student profile is linked to this account.'}</p>
            )}
            {student && surahs.length > 0 && (
                <RecitationRecorder surahs={surahs} assignments={assignments} t={t} surahOf={surahOf} />
            )}
            {student && surahs.length === 0 && (
                // Say so, rather than leave the recorder out in silence: on a
                // host with no surah reference the student saw no way to record
                // and no reason why (STATUS §5fz).
                <p className="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-2 text-sm">
                    {t.quran_no_surahs || 'Recording is not available yet: this site has no surah reference. Ask the office to load the Qur\'an data.'}
                </p>
            )}

            <h2 className="mb-2 text-lg font-semibold">{t.quran_assignments || 'My assignments'}</h2>
            <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.quran_col_type || 'Type'}</th>
                            <th className="px-3 py-2">{t.quran_col_surah || 'Surah'}</th>
                            <th className="px-3 py-2">{t.quran_col_ayahs || 'Ayahs'}</th>
                            <th className="px-3 py-2">{t.quran_col_due || 'Due'}</th>
                            <th className="px-3 py-2">{t.quran_col_status || 'Status'}</th>
                            <th className="px-3 py-2">{t.quran_col_teacher || 'Teacher'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {assignments.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={6}>{t.quran_no_assignments || 'No assignments yet.'}</td></tr>
                        )}
                        {assignments.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{named(q, 'assignment_type_', row.assignment_type)}</td>
                                <td className="px-3 py-2">{surahOf(row)}</td>
                                <td className="px-3 py-2">{range(row)}</td>
                                <td className="px-3 py-2">{row.due_date ?? '—'}</td>
                                <td className="px-3 py-2">{statusBadge(q, 'status_', row.status)}</td>
                                <td className="px-3 py-2">{row.teacher ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <h2 className="mb-2 text-lg font-semibold">{t.quran_progress || 'Memorization progress'}</h2>
            <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.quran_col_surah || 'Surah'}</th>
                            <th className="px-3 py-2">{t.quran_col_ayahs || 'Ayahs'}</th>
                            <th className="px-3 py-2">{t.quran_col_status || 'Status'}</th>
                            <th className="px-3 py-2">{t.quran_col_strength || 'Strength'}</th>
                            <th className="px-3 py-2">{t.quran_col_mistakes || 'Mistakes'}</th>
                            <th className="px-3 py-2">{t.quran_col_last_reviewed || 'Last reviewed'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {progress.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={6}>{t.quran_no_progress || 'No memorization progress yet.'}</td></tr>
                        )}
                        {progress.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{surahOf(row)}</td>
                                <td className="px-3 py-2">{range(row)}</td>
                                <td className="px-3 py-2">{statusBadge(q, 'progress_', row.status)}</td>
                                <td className="px-3 py-2">{row.strength_score ?? '—'}</td>
                                <td className="px-3 py-2">{row.mistake_count ?? '—'}</td>
                                <td className="px-3 py-2">{row.last_reviewed_at ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <h2 className="mb-2 text-lg font-semibold">{t.quran_submissions || 'My recitation submissions'}</h2>
            <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.quran_col_surah || 'Surah'}</th>
                            <th className="px-3 py-2">{t.quran_col_ayahs || 'Ayahs'}</th>
                            <th className="px-3 py-2">{t.quran_col_submitted || 'Submitted'}</th>
                            <th className="px-3 py-2">{t.quran_col_status || 'Status'}</th>
                            <th className="px-3 py-2">{t.quran_col_mistakes || 'Mistakes'}</th>
                            <th className="px-3 py-2">{t.quran_col_teacher_note || 'Teacher note'}</th>
                            {/* §36: a correction the student cannot play is not feedback. */}
                            <th className="px-3 py-2">{t.quran_col_listen || 'Listen'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {submissions.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={7}>{t.quran_no_submissions || 'No submissions yet.'}</td></tr>
                        )}
                        {submissions.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{surahOf(row)}</td>
                                <td className="px-3 py-2">{range(row)}</td>
                                <td className="px-3 py-2">{row.submitted_at}</td>
                                <td className="px-3 py-2">{statusBadge(q, 'status_', row.status)}</td>
                                <td className="px-3 py-2">{row.mistake_count}</td>
                                <td className="px-3 py-2">{row.review_note ?? '—'}</td>
                                <td className="px-3 py-2">
                                    <div className="grid gap-1">
                                        {row.has_audio && (
                                            <audio controls preload="none" className="h-8 w-52" src={`/recitations/${row.id}/audio/submission`}>
                                                {t.quran_audio_unsupported || 'Your browser cannot play audio.'}
                                            </audio>
                                        )}
                                        {row.has_correction_audio && (
                                            <div>
                                                <span className="text-xs font-medium text-brandMaroon-700">
                                                    {t.quran_teacher_correction || 'Teacher’s correction'}
                                                </span>
                                                <audio controls preload="none" className="h-8 w-52" src={`/recitations/${row.id}/audio/correction`}>
                                                    {t.quran_audio_unsupported || 'Your browser cannot play audio.'}
                                                </audio>
                                            </div>
                                        )}
                                        {!row.has_audio && !row.has_correction_audio && <span className="text-gray-400">—</span>}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <h2 className="mb-2 text-lg font-semibold">{t.quran_revision || 'Upcoming revision'}</h2>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.quran_col_date || 'Date'}</th>
                            <th className="px-3 py-2">{t.quran_col_surah || 'Surah'}</th>
                            <th className="px-3 py-2">{t.quran_col_ayahs || 'Ayahs'}</th>
                            <th className="px-3 py-2">{t.quran_col_frequency || 'Frequency'}</th>
                            <th className="px-3 py-2">{t.quran_col_status || 'Status'}</th>
                            <th className="px-3 py-2">{t.quran_col_notes || 'Notes'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {schedules.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={6}>{t.quran_no_revision || 'No revision scheduled.'}</td></tr>
                        )}
                        {schedules.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.scheduled_date}</td>
                                <td className="px-3 py-2">{surahOf(row)}</td>
                                <td className="px-3 py-2">{range(row)}</td>
                                <td className="px-3 py-2">{named(q, 'frequency_', row.frequency)}</td>
                                <td className="px-3 py-2">{statusBadge(q, 'revision_', row.status)}</td>
                                <td className="px-3 py-2">{row.notes ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
